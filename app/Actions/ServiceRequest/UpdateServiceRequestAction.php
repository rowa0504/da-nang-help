<?php

namespace App\Actions\ServiceRequest;

use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TranslationStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidServiceRequestTransitionException;
use App\Exceptions\UnprocessablePhotoException;
use App\Jobs\TranslateServiceRequestJob;
use App\Models\Offer;
use App\Models\RequestPhoto;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Photos\ProcessUploadedPhoto;
use App\Support\ServiceRequestHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateServiceRequestAction
{
    private const MAX_PHOTOS = 5;

    public function __construct(
        private readonly ProcessUploadedPhoto $processUploadedPhoto,
    ) {}

    /**
     * @param  array{title: string, description: string, category_id: int, area_id: int, address_text: string, urgency: string}  $data
     * @param  array<int, \Illuminate\Http\UploadedFile>  $newPhotoFiles
     * @param  array<int, int>  $removePhotoIds
     */
    public function handle(User $customer, ServiceRequest $serviceRequest, array $data, array $newPhotoFiles, array $removePhotoIds): UpdateServiceRequestResult
    {
        // Step 1 (per plan): new photos are processed and stored to Storage
        // *before* the DB transaction even opens — the same "outside the
        // transaction" ordering CreateServiceRequestAction already uses.
        // Unprocessable ones are skipped, not fatal (FR-17).
        $newlyStoredPhotos = [];
        $warnings = [];
        foreach (array_values($newPhotoFiles) as $file) {
            try {
                $objectKey = $this->processUploadedPhoto->handle($file);
                $newlyStoredPhotos[] = $objectKey;
            } catch (UnprocessablePhotoException $e) {
                $warnings[] = $e->getMessage();
            }
        }

        $dispatchLocales = [];
        $newSourceHash = null;
        $objectKeysToDeleteAfterCommit = [];

        try {
            $updated = DB::transaction(function () use (
                $customer,
                $serviceRequest,
                $data,
                $newlyStoredPhotos,
                $removePhotoIds,
                &$dispatchLocales,
                &$newSourceHash,
                &$objectKeysToDeleteAfterCommit,
            ) {
                $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

                // Re-verified after acquiring the lock — every condition
                // Policy::update() already checked, re-checked here against
                // the now-current row so a race (e.g. an Offer arriving
                // between the edit page loading and this submit landing)
                // is caught rather than silently overwritten. CreateOfferAction
                // locks this same ServiceRequest row before creating an
                // Offer, so the two are properly serialized by MySQL's row
                // lock — whichever commits first wins, the other re-reads
                // fresh state here and fails.
                if ($customer->role !== UserRole::Customer
                    || $locked->customer_id !== $customer->id
                    || $locked->status !== ServiceRequestStatus::Open
                    || $locked->moderation_status !== ServiceRequestModerationStatus::Visible
                    || Offer::where('service_request_id', $locked->id)->exists()) {
                    throw new InvalidServiceRequestTransitionException(
                        'This service request can no longer be edited.'
                    );
                }

                // Re-derive from the freshly locked photo set, not the
                // pre-lock argument: authoritative source for both "which
                // ids are actually removable" and the photo-count cap.
                $existingPhotos = $locked->photos()->get();
                $removeIds = $existingPhotos->pluck('id')->intersect($removePhotoIds)->values();

                $keptCount = $existingPhotos->count() - $removeIds->count();
                if ($keptCount + count($newlyStoredPhotos) > self::MAX_PHOTOS) {
                    throw new InvalidServiceRequestTransitionException(
                        'A service request may have at most '.self::MAX_PHOTOS.' photos.'
                    );
                }

                $titleChanged = $data['title'] !== $locked->title;
                $descriptionChanged = $data['description'] !== $locked->description;

                $locked->fill($data);
                $locked->save();

                // DB rows for removed photos are deleted now, inside the
                // transaction; the underlying Storage objects are only
                // deleted *after* commit (step 7) — never before, so a
                // mid-transaction failure can't leave a photo referenced by
                // a (rolled-back) DB row pointing at an already-deleted file.
                $photosToRemove = $existingPhotos->whereIn('id', $removeIds);
                foreach ($photosToRemove as $photo) {
                    $objectKeysToDeleteAfterCommit[] = $photo->object_key;
                }
                if ($removeIds->isNotEmpty()) {
                    RequestPhoto::where('service_request_id', $locked->id)->whereIn('id', $removeIds)->delete();
                }

                $nextSortOrder = ($existingPhotos->max('sort_order') ?? -1) + 1;
                foreach (array_values($newlyStoredPhotos) as $index => $objectKey) {
                    $locked->photos()->create([
                        'object_key' => $objectKey,
                        'sort_order' => $nextSortOrder + $index,
                    ]);
                }

                // Only title/description changes re-trigger translation —
                // category/area/address/urgency/photo-only edits leave
                // existing translations untouched. Locale set and
                // reset-to-pending shape mirror UpdateOfferAction exactly:
                // updateOrCreate absorbs "already had a row" and "needs a
                // new one" uniformly, and the row's own text is overwritten
                // with the new source text (not nulled) while status flips
                // back to pending.
                if ($titleChanged || $descriptionChanged) {
                    $newSourceHash = ServiceRequestHasher::hash($data['title'], $data['description']);
                    $dispatchLocales = array_diff(['en', 'ja', 'vi'], [$locked->source_locale]);
                    foreach ($dispatchLocales as $locale) {
                        $locked->translations()->updateOrCreate(
                            ['locale' => $locale],
                            [
                                'title' => $data['title'],
                                'description' => $data['description'],
                                'source_hash' => $newSourceHash,
                                'translation_status' => TranslationStatus::Pending->value,
                                'translated_at' => null,
                            ]
                        );
                    }
                }

                return $locked;
            });
        } catch (Throwable $e) {
            // Only the *newly* stored photos are compensated here: they
            // were never referenced by any row that made it into a commit.
            // The to-be-removed photos were never physically touched at
            // this point (their Storage deletion only happens after a
            // successful commit, below), so there is nothing to restore.
            foreach ($newlyStoredPhotos as $objectKey) {
                $this->deleteFromStorage(
                    $objectKey,
                    'Failed to clean up an orphaned service request photo after a DB failure during an edit.'
                );
            }
            throw $e;
        }

        // Commit succeeded: now, and only now, physically delete the
        // Storage objects for the photos that were removed. A failure here
        // must never roll back the already-committed DB state (there is
        // nothing left to roll back to) — log and move on.
        foreach ($objectKeysToDeleteAfterCommit as $objectKey) {
            $this->deleteFromStorage(
                $objectKey,
                'Failed to delete a removed service request photo from storage after the edit committed.'
            );
        }

        foreach ($dispatchLocales as $locale) {
            TranslateServiceRequestJob::dispatch($updated->id, $locale, $newSourceHash);
        }

        return new UpdateServiceRequestResult($updated, $warnings);
    }

    private function deleteFromStorage(string $objectKey, string $logMessage): void
    {
        try {
            $deleted = Storage::disk(config('filesystems.default'))->delete($objectKey);
            if ($deleted === false) {
                Log::error($logMessage.' (delete() returned false).', ['object_key' => $objectKey]);
            }
        } catch (Throwable $cleanupError) {
            Log::error($logMessage, ['object_key' => $objectKey, 'cleanup_error' => $cleanupError->getMessage()]);
        }
    }
}
