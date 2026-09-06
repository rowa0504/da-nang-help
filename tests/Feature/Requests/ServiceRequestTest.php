<?php

namespace Tests\Feature\Requests;

use App\Actions\ServiceRequest\CancelServiceRequestAction;
use App\Actions\ServiceRequest\CreateServiceRequestAction;
use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TranslationStatus;
use App\Exceptions\InvalidServiceRequestTransitionException;
use App\Jobs\TranslateServiceRequestJob;
use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestTranslation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Several tests here POST real photos through the full HTTP pipeline
        // (ProcessUploadedPhoto -> Storage::disk(config('filesystems.default'))->put()),
        // which without faking would write real files under storage/app/private
        // on every run (RefreshDatabase only resets the DB, not the filesystem).
        Storage::fake(config('filesystems.default'));
    }

    private function realJpegFile(string $name = 'photo.jpg'): UploadedFile
    {
        $image = imagecreatetruecolor(100, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 150, 200));
        $path = tempnam(sys_get_temp_dir(), 'test-photo');
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private function payload(array $overrides = []): array
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();

        return array_merge([
            'title' => 'Fix my leaking AC',
            'description' => 'Water is dripping from the unit.',
            'category_id' => $category->id,
            'area_id' => $area->id,
            'address_text' => '123 Example Street',
            'lat' => 16.05,
            'lng' => 108.2,
            'urgency' => 'normal',
            'source_locale' => 'en',
        ], $overrides);
    }

    private function approvedProviderFor(Category $category, Area $area): User
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();
        $profile->categories()->attach($category);
        $profile->areas()->attach($area);

        return $provider;
    }

    public function test_customer_can_create_a_request_without_photos(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->post('/requests', $this->payload());

        $serviceRequest = ServiceRequest::query()->where('customer_id', $customer->id)->firstOrFail();
        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertSame(ServiceRequestStatus::Open, $serviceRequest->status);
    }

    public function test_non_customer_cannot_view_the_create_form_or_submit(): void
    {
        $provider = User::factory()->provider()->create();
        $admin = User::factory()->admin()->create();

        foreach ([$provider, $admin] as $user) {
            $this->actingAs($user)->get('/requests/create')->assertForbidden();
            $this->actingAs($user)->post('/requests', $this->payload())->assertForbidden();
        }
    }

    public function test_required_fields_are_validated(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->post('/requests', [
            'title' => '',
            'description' => '',
            'category_id' => '',
            'area_id' => '',
            'address_text' => '',
            'lat' => '',
            'lng' => '',
            'urgency' => '',
            'source_locale' => '',
        ]);

        $response->assertInvalid(['title', 'description', 'category_id', 'area_id', 'address_text', 'lat', 'lng', 'urgency', 'source_locale']);
    }

    public function test_lat_and_lng_out_of_range_are_rejected(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->post('/requests', $this->payload(['lat' => 91, 'lng' => 181]));

        $response->assertInvalid(['lat', 'lng']);
    }

    public function test_address_text_at_500_characters_succeeds_and_is_not_truncated(): void
    {
        $customer = User::factory()->create();
        $address = str_repeat('a', 500);

        $response = $this->actingAs($customer)->post('/requests', $this->payload(['address_text' => $address]));

        $serviceRequest = ServiceRequest::query()->where('customer_id', $customer->id)->firstOrFail();
        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertSame(500, strlen($serviceRequest->fresh()->address_text));
        $this->assertSame($address, $serviceRequest->fresh()->address_text);
    }

    public function test_address_text_over_500_characters_is_rejected(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->post('/requests', $this->payload(['address_text' => str_repeat('a', 501)]));

        $response->assertInvalid(['address_text']);
    }

    public function test_more_than_five_photos_is_rejected(): void
    {
        $customer = User::factory()->create();
        $photos = array_map(fn ($i) => UploadedFile::fake()->image("photo{$i}.jpg", 50, 50), range(1, 6));

        $response = $this->actingAs($customer)->post('/requests', $this->payload(['photos' => $photos]));

        $response->assertInvalid(['photos']);
    }

    public function test_an_unprocessable_photo_is_skipped_but_the_request_is_still_created(): void
    {
        $customer = User::factory()->create();
        $goodPhoto = UploadedFile::fake()->image('good.jpg', 200, 200);
        $badPhoto = UploadedFile::fake()->create('bad.jpg', 10, 'application/pdf');

        $response = $this->actingAs($customer)->post('/requests', $this->payload(['photos' => [$goodPhoto, $badPhoto]]));

        $serviceRequest = ServiceRequest::query()->where('customer_id', $customer->id)->firstOrFail();
        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertCount(1, $serviceRequest->photos);
    }

    public function test_all_photos_unprocessable_still_creates_the_request_with_zero_photos(): void
    {
        $customer = User::factory()->create();
        $badPhoto = UploadedFile::fake()->create('bad.jpg', 10, 'application/pdf');

        $response = $this->actingAs($customer)->post('/requests', $this->payload(['photos' => [$badPhoto]]));

        $serviceRequest = ServiceRequest::query()->where('customer_id', $customer->id)->firstOrFail();
        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertCount(0, $serviceRequest->photos);
    }

    public function test_skipped_photo_warning_is_shared_via_inertia_flash_and_contains_no_internal_paths(): void
    {
        $customer = User::factory()->create();
        $badPhoto = UploadedFile::fake()->create('bad.jpg', 10, 'application/pdf');

        $createResponse = $this->actingAs($customer)->post('/requests', $this->payload(['photos' => [$badPhoto]]));
        $location = $createResponse->headers->get('Location');

        $this->actingAs($customer)->get($location)->assertInertia(function (Assert $page) {
            $page->where('flash.warning', fn ($warning) => is_string($warning)
                && str_contains($warning, 'bad.jpg')
                && ! str_contains($warning, '/tmp')
                && ! str_contains($warning, 'storage/'));
        });
    }

    public function test_flash_warning_is_null_when_nothing_was_skipped(): void
    {
        $customer = User::factory()->create();

        $createResponse = $this->actingAs($customer)->post('/requests', $this->payload());
        $location = $createResponse->headers->get('Location');

        $this->actingAs($customer)->get($location)->assertInertia(
            fn (Assert $page) => $page->where('flash.warning', null)
        );
    }

    public function test_lat_and_lng_are_returned_as_numbers_not_strings(): void
    {
        $customer = User::factory()->create();
        $createResponse = $this->actingAs($customer)->post('/requests', $this->payload(['lat' => 16.05, 'lng' => 108.2]));
        $location = $createResponse->headers->get('Location');

        $this->actingAs($customer)->get($location)->assertInertia(function (Assert $page) {
            $page->where('request.lat', fn ($lat) => is_float($lat) || is_int($lat))
                ->where('request.lng', fn ($lng) => is_float($lng) || is_int($lng));
        });
    }

    public function test_creation_dispatches_translation_jobs_and_creates_pending_rows(): void
    {
        Queue::fake();
        $customer = User::factory()->create();

        $this->actingAs($customer)->post('/requests', $this->payload(['source_locale' => 'en']));

        $serviceRequest = ServiceRequest::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertCount(2, $serviceRequest->translations);
        $this->assertTrue($serviceRequest->translations->every(fn ($t) => $t->translation_status === TranslationStatus::Pending));

        Queue::assertPushed(TranslateServiceRequestJob::class, 2);
        Queue::assertPushed(TranslateServiceRequestJob::class, fn ($job) => $job->serviceRequestId === $serviceRequest->id && $job->targetLocale === 'ja');
        Queue::assertPushed(TranslateServiceRequestJob::class, fn ($job) => $job->serviceRequestId === $serviceRequest->id && $job->targetLocale === 'vi');
    }

    public function test_translations_complete_synchronously_end_to_end_under_the_sync_queue_connection(): void
    {
        // No Queue::fake() here: relies on phpunit.xml's QUEUE_CONNECTION=sync
        // so the dispatched jobs run inline within this HTTP request.
        $customer = User::factory()->create();

        $this->actingAs($customer)->post('/requests', $this->payload(['source_locale' => 'en']));

        $serviceRequest = ServiceRequest::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertCount(2, $serviceRequest->translations);
        $this->assertTrue($serviceRequest->translations->every(fn ($t) => $t->translation_status === TranslationStatus::Completed));
    }

    public function test_owner_and_admin_can_see_private_fields(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();

        foreach ([$customer, $admin] as $viewer) {
            $this->actingAs($viewer)->get("/requests/{$serviceRequest->id}")->assertInertia(
                fn (Assert $page) => $page->has('request.address_text')->has('request.customer')
            );
        }
    }

    public function test_matching_approved_provider_can_view_but_not_see_private_fields(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get("/requests/{$serviceRequest->id}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->missing('request.address_text')->missing('request.customer')
        );
    }

    public function test_pending_or_non_matching_provider_cannot_view(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $otherCategory = Category::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);

        $unapprovedProvider = User::factory()->provider()->create();
        ProviderProfile::factory()->forUser($unapprovedProvider)->create(); // pending

        $mismatchedProvider = $this->approvedProviderFor($otherCategory, $area);

        $this->actingAs($unapprovedProvider)->get("/requests/{$serviceRequest->id}")->assertForbidden();
        $this->actingAs($mismatchedProvider)->get("/requests/{$serviceRequest->id}")->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();

        // actingAs() persists for the rest of the test once called, so the
        // guest (unauthenticated) case is exercised in its own test method
        // rather than sharing one with an actingAs() case.
        $this->get("/requests/{$serviceRequest->id}")->assertRedirect(route('login'));
    }

    public function test_unrelated_customer_cannot_view(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)->get("/requests/{$serviceRequest->id}")->assertForbidden();
    }

    public function test_hidden_request_is_not_reachable_by_a_matching_provider(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->hidden()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get("/requests/{$serviceRequest->id}")->assertForbidden();
    }

    public function test_hidden_request_is_still_viewable_by_owner_and_admin(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->hidden()->create();

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertOk();
        $this->actingAs($admin)->get("/requests/{$serviceRequest->id}")->assertOk();
    }

    public function test_owner_can_cancel_an_open_request(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();

        $response = $this->actingAs($customer)->patch("/requests/{$serviceRequest->id}/cancel");

        $response->assertRedirect(route('requests.show', $serviceRequest));
        $fresh = $serviceRequest->fresh();
        $this->assertSame(ServiceRequestStatus::Cancelled, $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
    }

    public function test_cancelling_an_already_cancelled_request_is_forbidden(): void
    {
        // The Policy already blocks this (status is no longer open), so the
        // HTTP-level rejection is a 403, not a withErrors() redirect. The
        // Action's own lock-and-reverify defense is exercised directly by
        // test_cancel_action_called_directly_rejects_a_different_customer
        // and via a direct call below (bypassing the Policy).
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->cancelled()->create();

        $response = $this->actingAs($customer)->patch("/requests/{$serviceRequest->id}/cancel");

        $response->assertForbidden();
        $this->assertSame(ServiceRequestStatus::Cancelled, $serviceRequest->fresh()->status);
    }

    public function test_cancel_action_reverifies_status_after_lock_even_when_called_directly(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->cancelled()->create();

        $this->expectException(InvalidServiceRequestTransitionException::class);
        app(CancelServiceRequestAction::class)->handle($customer, $serviceRequest->fresh());
    }

    public function test_cancel_action_called_directly_rejects_a_different_customer(): void
    {
        $owner = User::factory()->create();
        $someoneElse = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($owner)->create();

        $this->expectException(InvalidServiceRequestTransitionException::class);
        app(CancelServiceRequestAction::class)->handle($someoneElse, $serviceRequest);

        $this->assertSame(ServiceRequestStatus::Open, $serviceRequest->fresh()->status);
    }

    public function test_create_action_called_directly_rejects_a_non_customer(): void
    {
        $provider = User::factory()->provider()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();

        $this->expectException(InvalidServiceRequestTransitionException::class);
        app(CreateServiceRequestAction::class)->handle($provider, [
            'title' => 'x',
            'description' => 'x',
            'category_id' => $category->id,
            'area_id' => $area->id,
            'address_text' => 'x',
            'lat' => 0,
            'lng' => 0,
            'urgency' => 'normal',
            'source_locale' => 'en',
        ], []);

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_db_failure_during_creation_removes_the_orphaned_photo_and_rethrows(): void
    {
        // Storage is already faked globally in setUp(); no need to re-fake here.
        $customer = User::factory()->create();
        $area = Area::factory()->create();

        $thrown = null;
        try {
            app(CreateServiceRequestAction::class)->handle($customer, [
                'title' => 'Fix my leaking AC',
                'description' => 'Water is dripping from the unit.',
                'category_id' => 999_999, // does not exist -> FK constraint violation inside the transaction
                'area_id' => $area->id,
                'address_text' => '123 Example Street',
                'lat' => 16.05,
                'lng' => 108.2,
                'urgency' => 'normal',
                'source_locale' => 'en',
            ], [$this->realJpegFile()]);
        } catch (QueryException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Expected the original DB exception to be re-thrown.');
        $this->assertDatabaseCount('service_requests', 0);
        $this->assertDatabaseCount('request_photos', 0);
        $this->assertEmpty(
            Storage::disk(config('filesystems.default'))->allFiles('service-requests'),
            'The photo stored before the DB failure should have been cleaned up.'
        );
    }

    public function test_storage_delete_failure_during_compensation_is_logged_without_hiding_the_original_exception(): void
    {
        $customer = User::factory()->create();
        $area = Area::factory()->create();

        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->once()->andReturn(true);
        Storage::shouldReceive('delete')->once()->andReturn(false);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($message, $context) => str_contains($message, 'returned false')
                && isset($context['object_key']));

        $thrown = null;
        try {
            app(CreateServiceRequestAction::class)->handle($customer, [
                'title' => 'Fix my leaking AC',
                'description' => 'Water is dripping from the unit.',
                'category_id' => 999_999,
                'area_id' => $area->id,
                'address_text' => '123 Example Street',
                'lat' => 16.05,
                'lng' => 108.2,
                'urgency' => 'normal',
                'source_locale' => 'en',
            ], [$this->realJpegFile()]);
        } catch (QueryException $e) {
            $thrown = $e;
        }

        // The Log::shouldReceive('error')->once() expectation above is verified
        // automatically on teardown; asserting the original exception still
        // surfaced confirms the cleanup failure did not swallow it.
        $this->assertNotNull($thrown, 'Expected the original DB exception to still be re-thrown.');
    }

    public function test_own_request_list_shows_only_own_requests(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        ServiceRequest::factory()->forCustomer($customer)->count(2)->create();
        ServiceRequest::factory()->forCustomer($other)->create();

        $this->actingAs($customer)->get('/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 2)
        );
    }

    public function test_get_requests_is_restricted_to_customers(): void
    {
        $provider = User::factory()->provider()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($provider)->get('/requests')->assertForbidden();
        $this->actingAs($admin)->get('/requests')->assertForbidden();
    }

    public function test_get_requests_guest_is_redirected_to_login(): void
    {
        // actingAs() persists for the rest of the test once called, so the
        // guest case gets its own test method (see the same note above).
        $this->get('/requests')->assertRedirect(route('login'));
    }

    public function test_translation_metadata_reflects_the_original_when_the_viewer_shares_the_source_locale(): void
    {
        $customer = User::factory()->create();
        $customer->locale = 'en';
        $customer->save();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create([
            'title' => 'Fix my leaking AC',
            'description' => 'Water is dripping.',
            'source_locale' => 'en',
        ]);

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page
                ->where('request.title_translation.is_translated', false)
                ->where('request.title_translation.source_locale', 'en')
                ->where('request.title_translation.original', 'Fix my leaking AC')
                ->where('request.description_translation.is_translated', false)
                ->where('request.description_translation.original', 'Water is dripping.')
        );
    }

    public function test_translation_metadata_marks_a_completed_translation_as_translated(): void
    {
        $customer = User::factory()->create();
        $customer->locale = 'ja';
        $customer->save();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create([
            'title' => 'Fix my leaking AC',
            'description' => 'Water is dripping.',
            'source_locale' => 'en',
        ]);
        ServiceRequestTranslation::factory()->completed()->create([
            'service_request_id' => $serviceRequest->id,
            'locale' => 'ja',
            'title' => 'エアコンの水漏れを修理してください',
            'description' => '水が漏れています。',
        ]);

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page
                ->where('request.title', 'エアコンの水漏れを修理してください')
                ->where('request.title_translation.is_translated', true)
                ->where('request.title_translation.original', 'Fix my leaking AC')
                ->where('request.description_translation.is_translated', true)
                ->where('request.description_translation.original', 'Water is dripping.')
        );
    }

    public function test_create_form_categories_do_not_trigger_n_plus_one_from_translations(): void
    {
        $customer = User::factory()->create();
        $categories = Category::factory()->count(3)->create();
        foreach ($categories as $category) {
            CategoryTranslation::factory()->for($category)->create(['locale' => 'en']);
        }

        // Warm up first: the very first DB interaction in a test can carry
        // one-off overhead unrelated to the N+1 behavior under test (same
        // approach as JobTest::test_provider_feed_does_not_trigger_n_plus_one_from_service_job).
        $this->actingAs($customer)->get('/requests/create')->assertOk();

        DB::enableQueryLog();
        $this->actingAs($customer)->get('/requests/create')->assertOk();
        $queryCountForThree = count(DB::getQueryLog());
        DB::flushQueryLog();

        $moreCategories = Category::factory()->count(9)->create();
        foreach ($moreCategories as $category) {
            CategoryTranslation::factory()->for($category)->create(['locale' => 'en']);
        }
        DB::flushQueryLog();

        $this->actingAs($customer)->get('/requests/create')->assertOk();
        $queryCountForTwelve = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Query count must not scale with the number of categories — if
        // `translations` were lazy-loaded per category (instead of eager
        // loaded before nameFor() resolves each name), quadrupling the
        // category count would proportionally increase the query count.
        $this->assertSame($queryCountForThree, $queryCountForTwelve);
    }
}
