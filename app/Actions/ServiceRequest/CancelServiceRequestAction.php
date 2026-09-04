<?php

namespace App\Actions\ServiceRequest;

use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidServiceRequestTransitionException;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelServiceRequestAction
{
    public function handle(User $customer, ServiceRequest $serviceRequest): ServiceRequest
    {
        return DB::transaction(function () use ($customer, $serviceRequest) {
            $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            // Re-verified after acquiring the lock: role, ownership, and
            // status are all re-checked here so that a future caller which
            // bypasses the Policy (an Artisan command, another Job) cannot
            // cancel someone else's request or double-cancel one.
            if ($customer->role !== UserRole::Customer
                || $locked->customer_id !== $customer->id
                || $locked->status !== ServiceRequestStatus::Open) {
                throw new InvalidServiceRequestTransitionException(
                    'This service request cannot be cancelled by this user right now.'
                );
            }

            $locked->status = ServiceRequestStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->save();

            return $locked;
        });
    }
}
