<?php

namespace Tests\Feature\Notifications;

use App\Actions\Job\CompleteJobAction;
use App\Actions\Job\ReportJobCompletionAction;
use App\Actions\Offer\AcceptOfferAction;
use App\Actions\Offer\CreateOfferAction;
use App\Actions\Review\CreateReviewAction;
use App\Enums\JobCompletionMode;
use App\Enums\NotificationType;
use App\Exceptions\DuplicateOfferException;
use App\Jobs\SendNotificationEmailJob;
use App\Mail\OfferReceivedMail;
use App\Models\Area;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    private function approvedProviderFor(Category $category, Area $area): User
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();
        $profile->categories()->attach($category);
        $profile->areas()->attach($area);

        return $provider;
    }

    private function offerPayload(array $overrides = []): array
    {
        return array_merge([
            'price' => '150.00',
            'currency' => 'USD',
            'message' => 'I can help with this.',
            'available_at' => null,
            'source_locale' => 'en',
        ], $overrides);
    }

    public function test_create_offer_creates_a_notification_and_dispatches_the_email_job_after_commit(): void
    {
        Queue::fake();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $offer = app(CreateOfferAction::class)->handle($provider, $serviceRequest, $this->offerPayload());

        $notification = Notification::where('user_id', $serviceRequest->customer_id)->where('type', NotificationType::OfferReceived)->first();
        $this->assertNotNull($notification);
        $this->assertSame(['offer_id' => $offer->id, 'service_request_id' => $serviceRequest->id], $notification->data);
        Queue::assertPushed(SendNotificationEmailJob::class, fn (SendNotificationEmailJob $job) => $job->notificationId === $notification->id);
    }

    public function test_accept_offer_creates_a_notification_and_dispatches_the_email_job_after_commit(): void
    {
        Queue::fake();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        app(AcceptOfferAction::class)->handle($serviceRequest->customer, $offer);

        $notification = Notification::where('user_id', $provider->id)->where('type', NotificationType::OfferAccepted)->first();
        $this->assertNotNull($notification);
        $this->assertSame(['offer_id' => $offer->id, 'service_request_id' => $serviceRequest->id], $notification->data);
        Queue::assertPushed(SendNotificationEmailJob::class, fn (SendNotificationEmailJob $job) => $job->notificationId === $notification->id);
    }

    public function test_report_job_completion_creates_a_notification_and_dispatches_the_email_job_after_commit(): void
    {
        Queue::fake();
        $job = ServiceJob::factory()->inProgress()->create();

        app(ReportJobCompletionAction::class)->handle($job->provider, $job);

        $notification = Notification::where('user_id', $job->customer_id)->where('type', NotificationType::JobCompletionReported)->first();
        $this->assertNotNull($notification);
        $this->assertSame(['job_id' => $job->id], $notification->data);
        Queue::assertPushed(SendNotificationEmailJob::class, fn (SendNotificationEmailJob $job) => $job->notificationId === $notification->id);
    }

    public function test_complete_job_creates_a_notification_and_dispatches_the_email_job_after_commit(): void
    {
        Queue::fake();
        $job = ServiceJob::factory()->awaitingConfirmation()->create();
        ProviderProfile::factory()->forUser($job->provider)->approved()->create();

        app(CompleteJobAction::class)->handle($job, JobCompletionMode::Manual);

        $notification = Notification::where('user_id', $job->provider_id)->where('type', NotificationType::JobCompleted)->first();
        $this->assertNotNull($notification);
        $this->assertSame(['job_id' => $job->id], $notification->data);
        Queue::assertPushed(SendNotificationEmailJob::class, fn (SendNotificationEmailJob $job) => $job->notificationId === $notification->id);
    }

    public function test_create_review_creates_a_notification_and_dispatches_the_email_job_after_commit(): void
    {
        Queue::fake();
        $job = ServiceJob::factory()->completed()->create();
        ProviderProfile::factory()->forUser($job->provider)->approved()->create();

        $review = app(CreateReviewAction::class)->handle($job->customer, $job, ['rating' => 5, 'comment' => 'Great work']);

        $notification = Notification::where('user_id', $job->provider_id)->where('type', NotificationType::ReviewPosted)->first();
        $this->assertNotNull($notification);
        $this->assertSame(['review_id' => $review->id], $notification->data);
        Queue::assertPushed(SendNotificationEmailJob::class, fn (SendNotificationEmailJob $job) => $job->notificationId === $notification->id);
    }

    public function test_an_action_that_rolls_back_before_reaching_the_notification_block_dispatches_nothing(): void
    {
        // CreateOfferAction's lock-and-reverify check throws (and rolls
        // back) before it ever reaches Notification::create() — the
        // straightforward way every Action here actually rolls back.
        Queue::fake();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->cancelled()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        try {
            app(CreateOfferAction::class)->handle($provider, $serviceRequest, $this->offerPayload());
            $this->fail('Expected InvalidOfferTransitionException.');
        } catch (\App\Exceptions\InvalidOfferTransitionException) {
            // expected
        }

        $this->assertSame(0, Offer::count());
        $this->assertSame(0, Notification::count());
        Queue::assertNotPushed(SendNotificationEmailJob::class);
    }

    public function test_after_commit_dispatch_failure_still_persists_business_data_and_notification_and_retry_does_not_duplicate(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        // Simulates a queue connection that resolves successfully — so
        // afterCommit() registers its deferred push exactly as it would in
        // production — but whose actual push fails once that deferred
        // callback runs. This is deliberately different from a
        // misconfigured/missing connection name: that fails synchronously
        // at connection-resolution time (still inside the Action's own
        // transaction), which the Action's own DB::transaction() would
        // catch and roll back like any other mid-transaction failure —
        // it would not exercise the specific "commit already happened,
        // then the deferred push failed" risk documented in the Phase 8
        // plan (§13).
        $fakeQueue = \Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $fakeQueue->shouldReceive('push')->once()->andReturnUsing(function () {
            app('db.transactions')->addCallback(function () {
                throw new \RuntimeException('simulated queue push failure after commit');
            });
        });
        Queue::shouldReceive('connection')->once()->andReturn($fakeQueue);

        try {
            app(CreateOfferAction::class)->handle($provider, $serviceRequest, $this->offerPayload());
            $this->fail('Expected the post-commit queue push failure to propagate to the caller.');
        } catch (\Throwable) {
            // expected: the exception propagates even though the DB writes
            // below already succeeded.
        }

        $offer = Offer::where('service_request_id', $serviceRequest->id)->where('provider_id', $provider->id)->first();
        $this->assertNotNull($offer, 'Offer must persist despite the post-commit dispatch failure.');
        $notification = Notification::where('user_id', $serviceRequest->customer_id)->where('type', NotificationType::OfferReceived)->first();
        $this->assertNotNull($notification, 'Notification row must persist despite the post-commit dispatch failure.');

        // Re-running the same user action must not duplicate the business
        // data — existing uniqueness/state guards (here, the offer
        // request+provider unique constraint) protect against that. Uses
        // the real queue connection again (the Mockery expectation above
        // was consumed by the one call already made).
        $this->expectException(DuplicateOfferException::class);
        app(CreateOfferAction::class)->handle($provider, $serviceRequest, $this->offerPayload());
    }

    public function test_mailable_renders_with_locale_specific_subject_and_greeting_in_all_three_supported_locales(): void
    {
        Mail::fake();
        $originalLocale = App::getLocale();
        $category = Category::factory()->create();
        $area = Area::factory()->create();

        $expectations = [
            'en' => ['greeting' => 'Hi ', 'subject' => 'New offer received'],
            'ja' => ['greeting' => ' 様', 'subject' => '新しいオファーが届きました'],
            'vi' => ['greeting' => 'Chào ', 'subject' => 'Có báo giá mới'],
        ];

        foreach (array_keys($expectations) as $locale) {
            $customer = User::factory()->create();
            $customer->locale = $locale;
            $customer->save();
            $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create([
                'category_id' => $category->id,
                'area_id' => $area->id,
                'title' => 'Fix my aircon',
            ]);
            $provider = $this->approvedProviderFor($category, $area);

            app(CreateOfferAction::class)->handle($provider, $serviceRequest, $this->offerPayload());
        }

        Mail::assertSent(OfferReceivedMail::class, 3);

        foreach (Mail::sent(OfferReceivedMail::class) as $mailable) {
            $locale = $mailable->locale;
            $expected = $expectations[$locale];

            App::setLocale($locale);
            $subject = $mailable->envelope()->subject;
            App::setLocale($originalLocale);

            $this->assertStringContainsString($expected['subject'], $subject);
            $this->assertStringContainsString($expected['greeting'], $mailable->render());
        }
    }
}
