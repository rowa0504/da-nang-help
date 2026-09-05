<?php

namespace Tests\Feature\Jobs;

use App\Actions\Job\CancelJobAction;
use App\Actions\Job\ConfirmJobCompletionAction;
use App\Actions\Job\ReportJobCompletionAction;
use App\Actions\Job\StartJobAction;
use App\Enums\ServiceJobStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidJobTransitionException;
use App\Models\Area;
use App\Models\Category;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobTest extends TestCase
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

    /**
     * Builds a real, assigned ServiceRequest + accepted Offer + ServiceJob
     * chain, mirroring what AcceptOfferAction produces, without going
     * through HTTP for every test that just needs a starting Job.
     */
    private function createAssignedJob(): ServiceJob
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->accepted()->create();
        $job = ServiceJob::factory()->forOffer($offer)->create();

        $serviceRequest->status = ServiceRequestStatus::Assigned;
        $serviceRequest->save();

        return $job;
    }

    public function test_provider_can_start_an_assigned_job(): void
    {
        $job = $this->createAssignedJob();

        $response = $this->actingAs($job->provider)->patch("/jobs/{$job->id}/start");

        $response->assertRedirect(route('jobs.show', $job));
        $this->assertSame(ServiceJobStatus::InProgress, $job->fresh()->status);
    }

    public function test_customer_other_provider_and_admin_cannot_start_a_job(): void
    {
        $job = $this->createAssignedJob();
        $otherProvider = User::factory()->provider()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($job->customer)->patch("/jobs/{$job->id}/start")->assertForbidden();
        $this->actingAs($otherProvider)->patch("/jobs/{$job->id}/start")->assertForbidden();
        $this->actingAs($admin)->patch("/jobs/{$job->id}/start")->assertForbidden();
        $this->assertSame(ServiceJobStatus::Assigned, $job->fresh()->status);
    }

    public function test_provider_reports_completion_and_auto_confirm_at_follows_config(): void
    {
        config(['services.auto_confirm_days' => 3]);
        $job = $this->createAssignedJob();
        app(StartJobAction::class)->handle($job->provider, $job);

        $response = $this->actingAs($job->provider)->patch("/jobs/{$job->id}/report-completion");

        $response->assertRedirect(route('jobs.show', $job));
        $fresh = $job->fresh();
        $this->assertSame(ServiceJobStatus::AwaitingConfirmation, $fresh->status);
        $this->assertNotNull($fresh->provider_completed_at);
        $this->assertEqualsWithDelta(
            now()->addDays(3)->timestamp,
            $fresh->auto_confirm_at->timestamp,
            5
        );
    }

    public function test_customer_confirms_completion_and_provider_completed_jobs_count_increments(): void
    {
        $job = $this->createAssignedJob();
        app(StartJobAction::class)->handle($job->provider, $job);
        app(ReportJobCompletionAction::class)->handle($job->provider, $job->fresh());
        $countBefore = $job->provider->providerProfile->fresh()->completed_jobs_count;

        $response = $this->actingAs($job->customer)->patch("/jobs/{$job->id}/confirm-completion");

        $response->assertRedirect(route('jobs.show', $job));
        $fresh = $job->fresh();
        $this->assertSame(ServiceJobStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->customer_confirmed_at);
        $this->assertSame($countBefore + 1, $job->provider->providerProfile->fresh()->completed_jobs_count);
    }

    public function test_assigned_and_in_progress_jobs_can_be_cancelled_by_customer_or_provider(): void
    {
        $jobA = $this->createAssignedJob();
        $this->actingAs($jobA->customer)->patch("/jobs/{$jobA->id}/cancel")->assertRedirect(route('jobs.show', $jobA));
        $this->assertSame(ServiceJobStatus::Cancelled, $jobA->fresh()->status);
        $this->assertNotNull($jobA->fresh()->cancelled_at);
        // The Service Request is never reopened.
        $this->assertSame(ServiceRequestStatus::Assigned, $jobA->serviceRequest->fresh()->status);

        $jobB = $this->createAssignedJob();
        app(StartJobAction::class)->handle($jobB->provider, $jobB);
        $this->actingAs($jobB->provider)->patch("/jobs/{$jobB->id}/cancel")->assertRedirect(route('jobs.show', $jobB));
        $this->assertSame(ServiceJobStatus::Cancelled, $jobB->fresh()->status);
    }

    public function test_awaiting_confirmation_or_later_jobs_cannot_be_cancelled(): void
    {
        $job = $this->createAssignedJob();
        app(StartJobAction::class)->handle($job->provider, $job);
        app(ReportJobCompletionAction::class)->handle($job->provider, $job->fresh());

        $this->actingAs($job->customer)->patch("/jobs/{$job->id}/cancel")->assertForbidden();
        $this->actingAs($job->provider)->patch("/jobs/{$job->id}/cancel")->assertForbidden();
        $this->assertSame(ServiceJobStatus::AwaitingConfirmation, $job->fresh()->status);
    }

    public function test_admin_can_view_jobs_but_cannot_change_their_state(): void
    {
        $job = $this->createAssignedJob();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/jobs')->assertOk();
        $this->actingAs($admin)->get("/jobs/{$job->id}")->assertOk();

        $this->actingAs($admin)->patch("/jobs/{$job->id}/start")->assertForbidden();
        $this->actingAs($admin)->patch("/jobs/{$job->id}/cancel")->assertForbidden();

        app(StartJobAction::class)->handle($job->provider, $job);
        app(ReportJobCompletionAction::class)->handle($job->provider, $job->fresh());
        $this->actingAs($admin)->patch("/jobs/{$job->id}/confirm-completion")->assertForbidden();
    }

    public function test_invalid_transitions_are_rejected_via_http_and_direct_action_calls(): void
    {
        $job = $this->createAssignedJob();

        // Cannot report completion or confirm before the job is even started.
        $this->actingAs($job->provider)->patch("/jobs/{$job->id}/report-completion")->assertForbidden();
        $this->actingAs($job->customer)->patch("/jobs/{$job->id}/confirm-completion")->assertForbidden();

        $this->expectException(InvalidJobTransitionException::class);
        app(ReportJobCompletionAction::class)->handle($job->provider, $job);
    }

    public function test_direct_action_calls_reject_the_wrong_role_even_when_ids_would_otherwise_match(): void
    {
        $job = $this->createAssignedJob();

        try {
            app(StartJobAction::class)->handle($job->customer, $job);
            $this->fail('Expected InvalidJobTransitionException.');
        } catch (InvalidJobTransitionException) {
            // expected
        }
        $this->assertSame(ServiceJobStatus::Assigned, $job->fresh()->status);

        try {
            app(ConfirmJobCompletionAction::class)->handle($job->provider, $job);
            $this->fail('Expected InvalidJobTransitionException.');
        } catch (InvalidJobTransitionException) {
            // expected
        }

        try {
            app(CancelJobAction::class)->handle(User::factory()->create(), $job);
            $this->fail('Expected InvalidJobTransitionException.');
        } catch (InvalidJobTransitionException) {
            // expected
        }
    }

    public function test_assigned_provider_sees_address_and_coordinates_but_non_assigned_provider_does_not(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $assignedProvider = $this->approvedProviderFor($category, $area);
        $rejectedProvider = $this->approvedProviderFor($category, $area);

        $acceptedOffer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($assignedProvider)->accepted()->create();
        Offer::factory()->forServiceRequest($serviceRequest)->forProvider($rejectedProvider)->rejected()->create();
        ServiceJob::factory()->forOffer($acceptedOffer)->create();
        $serviceRequest->status = ServiceRequestStatus::Assigned;
        $serviceRequest->save();

        $this->actingAs($assignedProvider)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page->has('request.address_text')->has('request.lat')->has('request.lng')
        );

        $this->actingAs($rejectedProvider)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page->missing('request.address_text')->missing('request.lat')->missing('request.lng')
        );
    }

    public function test_request_show_includes_job_prop_with_contact_info_for_customer_provider_and_admin(): void
    {
        $job = $this->createAssignedJob();
        $admin = User::factory()->admin()->create();

        foreach ([$job->customer, $job->provider, $admin] as $viewer) {
            $this->actingAs($viewer)->get("/requests/{$job->service_request_id}")->assertInertia(
                fn (Assert $page) => $page
                    ->where('job.id', $job->id)
                    ->where('job.customer.name', $job->customer->name)
                    ->where('job.provider.name', $job->provider->name)
            );
        }
    }

    public function test_unrelated_users_get_forbidden_on_job_show_with_no_contact_info_leaked(): void
    {
        $job = $this->createAssignedJob();
        $unrelatedCustomer = User::factory()->create();
        $unrelatedProvider = User::factory()->provider()->create();

        $responseA = $this->actingAs($unrelatedCustomer)->get("/jobs/{$job->id}");
        $responseA->assertForbidden();
        $responseA->assertDontSee($job->customer->phone ?? 'unused', false);

        $this->actingAs($unrelatedProvider)->get("/jobs/{$job->id}")->assertForbidden();
    }

    public function test_index_returns_only_own_jobs_while_admin_sees_all(): void
    {
        $jobA = $this->createAssignedJob();
        $jobB = $this->createAssignedJob();
        $admin = User::factory()->admin()->create();

        $this->actingAs($jobA->customer)->get('/jobs')->assertInertia(
            fn (Assert $page) => $page->has('jobs.data', 1)->where('jobs.data.0.id', $jobA->id)
        );

        $this->actingAs($admin)->get('/jobs')->assertInertia(
            fn (Assert $page) => $page->has('jobs.data', 2)
        );
    }

    public function test_provider_feed_does_not_trigger_n_plus_one_from_service_job(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);
        ServiceRequest::factory()->count(5)->create(['category_id' => $category->id, 'area_id' => $area->id]);

        // Warm up first: the very first DB interaction in a test can carry
        // one-off overhead (e.g. schema/connection setup) unrelated to the
        // N+1 behavior under test, which would otherwise skew the first
        // measurement below.
        $this->actingAs($provider)->get('/provider/requests')->assertOk();

        DB::enableQueryLog();
        $this->actingAs($provider)->get('/provider/requests')->assertOk();
        $queryCountForFive = count(DB::getQueryLog());
        DB::flushQueryLog();

        // Flush again after seeding: creating these rows issues its own
        // inserts (including a nested customer User per request), which
        // must not be counted as part of the *feed request's* query count.
        ServiceRequest::factory()->count(10)->create(['category_id' => $category->id, 'area_id' => $area->id]);
        DB::flushQueryLog();

        $this->actingAs($provider)->get('/provider/requests')->assertOk();
        $queryCountForFifteen = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Query count must not scale with the number of requests returned —
        // if serviceJob were being lazy-loaded per row, doubling+ the
        // request count would proportionally increase the query count.
        $this->assertSame($queryCountForFive, $queryCountForFifteen);
    }

    public function test_auto_confirm_batch_completes_a_job_after_the_configured_delay(): void
    {
        config(['services.auto_confirm_days' => 3]);
        $job = $this->createAssignedJob();
        app(StartJobAction::class)->handle($job->provider, $job);
        app(ReportJobCompletionAction::class)->handle($job->provider, $job->fresh());

        Carbon::setTestNow(now()->addDays(3)->addMinute());
        $this->artisan('app:auto-confirm-jobs');
        Carbon::setTestNow();

        $fresh = $job->fresh();
        $this->assertSame(ServiceJobStatus::Completed, $fresh->status);
        $this->assertNull($fresh->customer_confirmed_at);
    }

    public function test_jobs_route_is_reachable_by_customer_and_provider(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->provider()->create();

        $this->actingAs($customer)->get('/jobs')->assertOk();
        $this->actingAs($provider)->get('/jobs')->assertOk();
    }

    public function test_jobs_route_redirects_guests_to_login(): void
    {
        $this->get('/jobs')->assertRedirect('/login');
    }
}
