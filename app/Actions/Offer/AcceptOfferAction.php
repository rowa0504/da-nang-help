<?php

namespace App\Actions\Offer;

use App\Enums\OfferStatus;
use App\Enums\ServiceJobStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidOfferTransitionException;
use App\Models\Offer;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptOfferAction
{
    public function handle(User $customer, Offer $offer): ServiceJob
    {
        return DB::transaction(function () use ($customer, $offer) {
            // Lock Service Request before Offer, same order as UpdateOfferAction.
            $lockedRequest = ServiceRequest::query()->lockForUpdate()->findOrFail($offer->service_request_id);
            $lockedOffer = Offer::query()->lockForUpdate()->findOrFail($offer->id);

            if ($customer->role !== UserRole::Customer
                || $customer->id !== $lockedRequest->customer_id
                || $lockedOffer->service_request_id !== $lockedRequest->id
                || $lockedRequest->status !== ServiceRequestStatus::Open
                || $lockedOffer->status !== OfferStatus::Pending) {
                throw new InvalidOfferTransitionException('This offer can no longer be accepted.');
            }

            $lockedOffer->status = OfferStatus::Accepted;
            $lockedOffer->save();

            // FR-27: every other unconfirmed offer on the same request is
            // automatically rejected as part of this same transaction.
            Offer::where('service_request_id', $lockedRequest->id)
                ->where('id', '!=', $lockedOffer->id)
                ->where('status', OfferStatus::Pending->value)
                ->update(['status' => OfferStatus::Rejected->value]);

            $lockedRequest->status = ServiceRequestStatus::Assigned;
            $lockedRequest->save();

            $job = new ServiceJob();
            $job->service_request_id = $lockedRequest->id;
            $job->offer_id = $lockedOffer->id;
            $job->customer_id = $lockedRequest->customer_id;
            $job->provider_id = $lockedOffer->provider_id;
            $job->agreed_price = $lockedOffer->price;
            $job->currency = $lockedOffer->currency;
            $job->status = ServiceJobStatus::Assigned;
            $job->save();

            return $job;
        });
    }
}
