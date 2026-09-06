<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Only reachable once JobPolicy::view() has already confirmed the viewer is
 * the Job's Customer, its Provider, or an Admin, so it is safe to always
 * return both parties' contact info and the request's exact address — FR-34
 * requires mutual disclosure between Customer and Provider once a Job
 * exists, not an asymmetric split like OfferResource/ServiceRequestResource.
 */
class JobResource extends JsonResource
{
    public function toArray($request): array
    {
        $viewer = $request->user();
        $review = $this->review;
        // Phase 7: a hidden review stays visible to the Customer who wrote
        // it and to Admin (moderation/audit), but is withheld from the
        // Provider it's about — otherwise Admin's hide action would do
        // nothing but tweak the average.
        $hideReviewFromViewer = $review !== null
            && $review->is_hidden
            && $viewer->id === $this->provider_id
            && $viewer->role !== UserRole::Admin;

        return [
            'id' => $this->id,
            'agreed_price' => $this->agreed_price,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'provider_completed_at' => $this->provider_completed_at?->toIso8601String(),
            'customer_confirmed_at' => $this->customer_confirmed_at?->toIso8601String(),
            'auto_confirm_at' => $this->auto_confirm_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'service_request' => [
                'id' => $this->serviceRequest->id,
                'title' => $this->serviceRequest->title,
                'address_text' => $this->serviceRequest->address_text,
                'lat' => (float) $this->serviceRequest->lat,
                'lng' => (float) $this->serviceRequest->lng,
            ],
            'customer' => [
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ],
            'provider' => [
                'name' => $this->provider->name,
                'phone' => $this->provider->phone,
                'business_name' => $this->provider->providerProfile?->business_name,
            ],
            'review' => ($review !== null && ! $hideReviewFromViewer) ? [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'is_hidden' => $review->is_hidden,
                'created_at' => $review->created_at->toIso8601String(),
            ] : null,
        ];
    }
}
