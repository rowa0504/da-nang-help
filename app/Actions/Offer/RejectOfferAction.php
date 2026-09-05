<?php

namespace App\Actions\Offer;

use App\Enums\OfferStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidOfferTransitionException;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectOfferAction
{
    public function handle(User $customer, Offer $offer): Offer
    {
        return DB::transaction(function () use ($customer, $offer) {
            $locked = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            $serviceRequest = ServiceRequest::query()->findOrFail($locked->service_request_id);

            if ($customer->role !== UserRole::Customer
                || $customer->id !== $serviceRequest->customer_id
                || $locked->status !== OfferStatus::Pending) {
                throw new InvalidOfferTransitionException('This offer cannot be rejected.');
            }

            $locked->status = OfferStatus::Rejected;
            $locked->save();

            return $locked;
        });
    }
}
