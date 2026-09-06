<?php

namespace App\Enums;

enum NotificationType: string
{
    case OfferReceived = 'offer_received';
    case OfferAccepted = 'offer_accepted';
    case JobCompletionReported = 'job_completion_reported';
    case JobCompleted = 'job_completed';
    case ReviewPosted = 'review_posted';
}
