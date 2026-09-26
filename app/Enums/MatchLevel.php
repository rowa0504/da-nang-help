<?php

namespace App\Enums;

/**
 * How closely a ServiceRequest matches a viewing Provider's own attached
 * categories/areas. Purely a ranking/display signal — since Phase "broaden
 * provider request discovery", it no longer gates Feed visibility or Offer
 * eligibility (see ServiceRequestPolicy/OfferPolicy/CreateOfferAction).
 */
enum MatchLevel: string
{
    case Full = 'full';
    case Partial = 'partial';
    case None = 'none';
}
