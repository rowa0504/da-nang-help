<?php

namespace App\Http\Resources;

use App\Enums\MatchLevel;
use App\Enums\TranslationStatus;
use App\Enums\UserRole;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Shapes a ServiceRequest for the current viewer. Whether the viewer is
 * *allowed* to see this resource at all (moderation_status, Provider
 * approval, etc.) is decided entirely by ServiceRequestPolicy before this
 * class ever runs — this class only decides *what* to show once that's
 * already settled, so the two never drift out of sync.
 */
class ServiceRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        $viewer = $request->user();
        // Only consult the serviceJob relation if it was actually eager
        // loaded by the caller (ServiceRequestController::show()) — feed/
        // list endpoints never load it (they only ever show `open` requests,
        // which by definition have no Job yet), and touching it there would
        // trigger a lazy-loaded N+1 for every row.
        $assignedProviderId = $this->relationLoaded('serviceJob') ? $this->serviceJob?->provider_id : null;
        $canSeePrivate = $viewer !== null
            && ($viewer->id === $this->customer_id
                || $viewer->role === UserRole::Admin
                || $viewer->id === $assignedProviderId);
        $viewerLocale = $viewer->locale ?? 'en';
        $matchLevel = $this->matchLevelFor($viewer, $request);
        // title/description share one translation row per locale, so
        // "is this the machine-translated version" is the same for both.
        $isTranslated = $viewerLocale !== $this->source_locale
            && $this->translations->firstWhere('locale', $viewerLocale)?->translation_status === TranslationStatus::Completed;

        return [
            'id' => $this->id,
            'title' => $this->translatedTitleFor($viewerLocale),
            'description' => $this->translatedDescriptionFor($viewerLocale),
            'title_translation' => [
                'is_translated' => $isTranslated,
                'source_locale' => $this->source_locale,
                'original' => $this->title,
            ],
            'description_translation' => [
                'is_translated' => $isTranslated,
                'source_locale' => $this->source_locale,
                'original' => $this->description,
            ],
            'category' => [
                'id' => $this->category_id,
                'name' => $this->category->nameFor(app()->getLocale()),
            ],
            'area' => [
                'id' => $this->area_id,
                'name' => $this->area->name,
            ],
            'urgency' => $this->urgency->value,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
            'photos' => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => Storage::disk(config('filesystems.default'))->temporaryUrl($photo->object_key, now()->addMinutes(15)),
            ]),
            ...$matchLevel !== null ? ['match_level' => $matchLevel->value] : [],
            ...$canSeePrivate ? [
                'address_text' => $this->address_text,
                'lat' => $this->lat !== null ? (float) $this->lat : null,
                'lng' => $this->lng !== null ? (float) $this->lng : null,
                'customer' => [
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                ],
            ] : [],
        ];
    }

    /**
     * Computed purely from the id arrays the caller (RequestFeedController
     * or ServiceRequestController::show()) stashed onto the request via
     * `viewer_category_ids`/`viewer_area_ids` — never by querying the
     * Provider's own pivots here, which would re-introduce an N+1 for every
     * row in a Feed collection. Absent (not present in the attributes bag)
     * for any non-Provider viewer, or a Provider with no profile yet, in
     * which case this returns null and the field is omitted entirely.
     */
    private function matchLevelFor($viewer, $request): ?MatchLevel
    {
        if ($viewer === null
            || $viewer->role !== UserRole::Provider
            || ! $request->attributes->has('viewer_category_ids')) {
            return null;
        }

        $categoryIds = $request->attributes->get('viewer_category_ids');
        $areaIds = $request->attributes->get('viewer_area_ids');

        $categoryMatch = in_array($this->category_id, $categoryIds, true);
        $areaMatch = in_array($this->area_id, $areaIds, true);

        return match (true) {
            $categoryMatch && $areaMatch => MatchLevel::Full,
            $categoryMatch || $areaMatch => MatchLevel::Partial,
            default => MatchLevel::None,
        };
    }
}
