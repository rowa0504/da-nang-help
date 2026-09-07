<?php

namespace App\Actions\Admin;

use App\Enums\ServiceRequestModerationStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidServiceRequestTransitionException;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HideServiceRequestAction
{
    /**
     * Moderation is non-destructive and status-independent (open/assigned/
     * cancelled may all be hidden, see Phase 9 plan §2#5): it only flips
     * moderation_status, never touches the request's own status, any
     * existing Offer, or any existing Job. No unhide ability exists,
     * mirroring HideReviewAction's one-way pattern.
     */
    public function handle(User $admin, ServiceRequest $serviceRequest): ServiceRequest
    {
        return DB::transaction(function () use ($admin, $serviceRequest) {
            $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            if ($admin->role !== UserRole::Admin
                || $locked->moderation_status !== ServiceRequestModerationStatus::Visible) {
                throw new InvalidServiceRequestTransitionException('This request cannot be hidden.');
            }

            $locked->moderation_status = ServiceRequestModerationStatus::Hidden;
            $locked->hidden_at = now();
            $locked->hidden_by = $admin->id;
            $locked->save();

            return $locked;
        });
    }
}
