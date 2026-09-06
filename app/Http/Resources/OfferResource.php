<?php

namespace App\Http\Resources;

use App\Enums\TranslationStatus;
use App\Enums\UserRole;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Every consumer of this Resource has already passed OfferPolicy (the
 * posting Customer, the offering Provider, or an Admin), unlike
 * ServiceRequestResource which serves several audiences behind one
 * endpoint. So this class only decides which *additional* editing-related
 * fields (original_message/source_locale) to add for the offering
 * Provider/Admin — it never re-implements the access decision itself.
 */
class OfferResource extends JsonResource
{
    public function toArray($request): array
    {
        $viewer = $request->user();
        $isOwnerOrAdmin = $viewer->id === $this->provider_id || $viewer->role === UserRole::Admin;
        $viewerLocale = $viewer->locale ?? 'en';
        $isMessageTranslated = $viewerLocale !== $this->source_locale
            && $this->translations->firstWhere('locale', $viewerLocale)?->translation_status === TranslationStatus::Completed;

        return [
            'id' => $this->id,
            'price' => $this->price, // decimal:2 cast string, never floated (see model)
            'currency' => $this->currency,
            'message' => $this->translatedMessageFor($viewerLocale),
            'message_translation' => [
                'is_translated' => $isMessageTranslated,
                'source_locale' => $this->source_locale,
                'original' => $this->message,
            ],
            'available_at' => $this->available_at?->toIso8601String(),
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
            'provider' => [
                'id' => $this->provider_id,
                'business_name' => $this->provider->providerProfile?->business_name,
                'avg_rating' => $this->provider->providerProfile?->avg_rating ?? '0.00',
                'completed_jobs_count' => $this->provider->providerProfile?->completed_jobs_count ?? 0,
            ],
            ...$isOwnerOrAdmin ? [
                'original_message' => $this->message,
                'source_locale' => $this->source_locale,
            ] : [],
        ];
    }
}
