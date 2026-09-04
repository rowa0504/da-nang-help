<?php

namespace App\Actions\ServiceRequest;

use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TranslationStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidServiceRequestTransitionException;
use App\Exceptions\UnprocessablePhotoException;
use App\Jobs\TranslateServiceRequestJob;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Photos\ProcessUploadedPhoto;
use App\Support\ServiceRequestHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateServiceRequestAction
{
    public function __construct(
        private readonly ProcessUploadedPhoto $processUploadedPhoto,
    ) {}

    /**
     * @param  array{title: string, description: string, category_id: int, area_id: int, address_text: string, lat: float, lng: float, urgency: string, source_locale: string}  $data
     * @param  array<int, \Illuminate\Http\UploadedFile>  $photoFiles
     */
    public function handle(User $customer, array $data, array $photoFiles): CreateServiceRequestResult
    {
        if ($customer->role !== UserRole::Customer) {
            // Defence in depth: reachable only if this Action is ever
            // called outside the normal Policy-guarded HTTP path.
            throw new InvalidServiceRequestTransitionException(
                'Only Customer-role users may create a service request.'
            );
        }

        $sourceHash = ServiceRequestHasher::hash($data['title'], $data['description']);
        $translationLocales = array_diff(['en', 'ja', 'vi'], [$data['source_locale']]);

        // Photo processing happens outside the DB transaction. A photo that
        // cannot be processed is skipped (its reason collected as a
        // warning) rather than failing the whole submission (FR-17).
        $storedPhotos = [];
        $warnings = [];
        foreach (array_values($photoFiles) as $index => $file) {
            try {
                $objectKey = $this->processUploadedPhoto->handle($file);
                $storedPhotos[] = ['object_key' => $objectKey, 'sort_order' => $index];
            } catch (UnprocessablePhotoException $e) {
                $warnings[] = $e->getMessage();
            }
        }

        try {
            $serviceRequest = DB::transaction(function () use ($customer, $data, $storedPhotos, $sourceHash, $translationLocales) {
                $serviceRequest = new ServiceRequest($data);
                $serviceRequest->customer_id = $customer->id;
                $serviceRequest->status = ServiceRequestStatus::Open;
                $serviceRequest->moderation_status = ServiceRequestModerationStatus::Visible;
                $serviceRequest->save();

                foreach ($storedPhotos as $photo) {
                    $serviceRequest->photos()->create($photo);
                }

                foreach ($translationLocales as $locale) {
                    $serviceRequest->translations()->create([
                        'locale' => $locale,
                        'title' => $data['title'],
                        'description' => $data['description'],
                        'source_hash' => $sourceHash,
                        'translation_status' => TranslationStatus::Pending,
                    ]);
                }

                return $serviceRequest;
            });
        } catch (\Throwable $e) {
            // The DB save failed after photos were already stored: clean up
            // the orphaned files instead of leaving them behind, and never
            // hide the original failure behind a cleanup failure.
            foreach ($storedPhotos as $photo) {
                try {
                    $deleted = Storage::disk(config('filesystems.default'))->delete($photo['object_key']);
                    if ($deleted === false) {
                        Log::error('Failed to clean up orphaned service request photo after a DB failure (delete() returned false).', [
                            'object_key' => $photo['object_key'],
                        ]);
                    }
                } catch (\Throwable $cleanupError) {
                    Log::error('Failed to clean up orphaned service request photo after a DB failure.', [
                        'object_key' => $photo['object_key'],
                        'cleanup_error' => $cleanupError->getMessage(),
                    ]);
                }
            }
            throw $e;
        }

        foreach ($translationLocales as $locale) {
            TranslateServiceRequestJob::dispatch($serviceRequest->id, $locale, $sourceHash);
        }

        return new CreateServiceRequestResult($serviceRequest, $warnings);
    }
}
