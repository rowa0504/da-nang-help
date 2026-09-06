<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Mail\JobCompletedMail;
use App\Mail\JobCompletionReportedMail;
use App\Mail\OfferAcceptedMail;
use App\Mail\OfferReceivedMail;
use App\Mail\ReviewPostedMail;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public int $backoff = 30;

    public function __construct(public int $notificationId)
    {
    }

    public function handle(): void
    {
        $notification = Notification::with('user')->find($this->notificationId);
        if ($notification === null) {
            return;
        }

        $mailable = match ($notification->type) {
            NotificationType::OfferReceived => new OfferReceivedMail($notification),
            NotificationType::OfferAccepted => new OfferAcceptedMail($notification),
            NotificationType::JobCompletionReported => new JobCompletionReportedMail($notification),
            NotificationType::JobCompleted => new JobCompletedMail($notification),
            NotificationType::ReviewPosted => new ReviewPostedMail($notification),
        };

        Mail::to($notification->user->email)
            ->locale($notification->user->locale)
            ->send($mailable);
    }
}
