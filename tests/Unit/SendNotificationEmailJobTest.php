<?php

namespace Tests\Unit;

use App\Enums\NotificationType;
use App\Jobs\SendNotificationEmailJob;
use App\Mail\JobCompletedMail;
use App\Mail\JobCompletionReportedMail;
use App\Mail\OfferAcceptedMail;
use App\Mail\OfferReceivedMail;
use App\Mail\ReviewPostedMail;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendNotificationEmailJobTest extends TestCase
{
    use RefreshDatabase;

    private function notificationFor(User $user, NotificationType $type, array $data): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'data' => $data,
        ]);
    }

    public function test_offer_received_sends_the_offer_received_mailable_in_the_recipients_locale(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->locale = 'ja';
        $user->save();
        $offer = Offer::factory()->create();
        $notification = $this->notificationFor($user, NotificationType::OfferReceived, ['offer_id' => $offer->id, 'service_request_id' => $offer->service_request_id]);

        SendNotificationEmailJob::dispatchSync($notification->id);

        Mail::assertSent(OfferReceivedMail::class, function (OfferReceivedMail $mail) use ($notification, $user) {
            return $mail->notification->id === $notification->id
                && $mail->hasTo($user->email)
                && $mail->locale === 'ja';
        });
    }

    public function test_offer_accepted_sends_the_offer_accepted_mailable(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $offer = Offer::factory()->create();
        $notification = $this->notificationFor($user, NotificationType::OfferAccepted, ['offer_id' => $offer->id, 'service_request_id' => $offer->service_request_id]);

        SendNotificationEmailJob::dispatchSync($notification->id);

        Mail::assertSent(OfferAcceptedMail::class, fn (OfferAcceptedMail $mail) => $mail->hasTo($user->email));
    }

    public function test_job_completion_reported_sends_the_job_completion_reported_mailable(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $job = ServiceJob::factory()->create();
        $notification = $this->notificationFor($user, NotificationType::JobCompletionReported, ['job_id' => $job->id]);

        SendNotificationEmailJob::dispatchSync($notification->id);

        Mail::assertSent(JobCompletionReportedMail::class, fn (JobCompletionReportedMail $mail) => $mail->hasTo($user->email));
    }

    public function test_job_completed_sends_the_job_completed_mailable(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $job = ServiceJob::factory()->create();
        $notification = $this->notificationFor($user, NotificationType::JobCompleted, ['job_id' => $job->id]);

        SendNotificationEmailJob::dispatchSync($notification->id);

        Mail::assertSent(JobCompletedMail::class, fn (JobCompletedMail $mail) => $mail->hasTo($user->email));
    }

    public function test_review_posted_sends_the_review_posted_mailable(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $notification = $this->notificationFor($user, NotificationType::ReviewPosted, ['review_id' => $review->id]);

        SendNotificationEmailJob::dispatchSync($notification->id);

        Mail::assertSent(ReviewPostedMail::class, fn (ReviewPostedMail $mail) => $mail->hasTo($user->email));
    }

    public function test_missing_notification_id_sends_nothing_and_does_not_throw(): void
    {
        Mail::fake();

        SendNotificationEmailJob::dispatchSync(999_999);

        Mail::assertNothingSent();
    }
}
