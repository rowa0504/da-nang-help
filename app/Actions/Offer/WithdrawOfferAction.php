<?php

namespace App\Actions\Offer;

use App\Enums\OfferStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidOfferTransitionException;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WithdrawOfferAction
{
    public function handle(User $provider, Offer $offer): Offer
    {
        return DB::transaction(function () use ($provider, $offer) {
            $locked = Offer::query()->lockForUpdate()->findOrFail($offer->id);

            if ($provider->role !== UserRole::Provider
                || $locked->provider_id !== $provider->id
                || $locked->status !== OfferStatus::Pending) {
                throw new InvalidOfferTransitionException('This offer cannot be withdrawn.');
            }

            $locked->status = OfferStatus::Withdrawn;
            $locked->withdrawn_at = now();
            $locked->save();

            return $locked;
        });
    }
}
