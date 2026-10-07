<?php

namespace Tests\Feature\Requests;

use App\Actions\ServiceRequest\CancelServiceRequestAction;
use App\Actions\ServiceRequest\CreateServiceRequestAction;
use App\Actions\ServiceRequest\UpdateServiceRequestAction;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TranslationStatus;
use App\Exceptions\InvalidServiceRequestTransitionException;
use App\Jobs\TranslateServiceRequestJob;
use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\RequestPhoto;
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
use PHPUnit\Framework\Attributes\DataProvider;
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
            'urgency' => '',
            'source_locale' => '',
        ]);

        $response->assertInvalid(['title', 'description', 'category_id', 'area_id', 'address_text', 'urgency', 'source_locale']);
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

    /**
     * description has both a character limit (max:5000, checked
     * elsewhere) and this separate byte limit (MaxUtf8Bytes(10_000)):
     * Amazon Translate's TranslateText API rejects input over 10,000
     * UTF-8 bytes, and Japanese/Vietnamese text can run 3-4 bytes per
     * character, so a submission well under 5,000 characters can still
     * exceed 10,000 bytes.
     */
    public function test_description_at_exactly_ten_thousand_utf8_bytes_succeeds(): void
    {
        $customer = User::factory()->create();
        // 'é' is 2 bytes in UTF-8: 5,000 characters (right at the
        // pre-existing max:5000 character limit) x 2 bytes = exactly
        // 10,000 bytes (right at the new byte limit) - both boundaries at
        // once, deliberately.
        $description = str_repeat('é', 5_000);
        $this->assertSame(5_000, mb_strlen($description));
        $this->assertSame(10_000, strlen($description));

        $response = $this->actingAs($customer)->post('/requests', $this->payload(['description' => $description]));

        $serviceRequest = ServiceRequest::query()->where('customer_id', $customer->id)->firstOrFail();
        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertSame(10_000, strlen($serviceRequest->fresh()->description));
    }

    public function test_description_one_byte_over_ten_thousand_utf8_bytes_is_rejected(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->post('/requests', $this->payload(['description' => str_repeat('a', 10_001)]));

        $response->assertInvalid(['description']);
    }

    public function test_description_with_multibyte_characters_under_the_character_limit_but_over_the_byte_limit_is_rejected(): void
    {
        $customer = User::factory()->create();

        // 3,334 Japanese characters: well under max:5000 characters, but
        // "あ" is 3 bytes in UTF-8, so this is 10,002 bytes - over the
        // byte limit despite passing the character limit easily.
        $description = str_repeat('あ', 3_334);
        $this->assertLessThan(5000, mb_strlen($description));
        $this->assertSame(10_002, strlen($description));

        $response = $this->actingAs($customer)->post('/requests', $this->payload(['description' => $description]));

        $response->assertInvalid(['description']);
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

    public function test_a_newly_created_request_has_null_coordinates_not_zero(): void
    {
        // Customers no longer submit lat/lng, and the Resource must not
        // (float) 0.0 them just because the column allows null — that would
        // silently render as "0, 0" (a real place) instead of "not yet
        // geocoded".
        $customer = User::factory()->create();
        $createResponse = $this->actingAs($customer)->post('/requests', $this->payload());
        $location = $createResponse->headers->get('Location');

        $this->actingAs($customer)->get($location)->assertInertia(
            fn (Assert $page) => $page->where('request.lat', null)->where('request.lng', null)
        );
    }

    public function test_lat_and_lng_are_returned_as_numbers_not_strings_when_present(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->withCoordinates()->create();

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page->where('request.lat', fn ($lat) => is_float($lat) || is_int($lat))
                ->where('request.lng', fn ($lng) => is_float($lng) || is_int($lng))
        );
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
            fn (Assert $page) => $page->missing('request.address_text')
                ->missing('request.customer')
                ->missing('request.moderation_status')
                ->where('request.match_level', 'full')
        );
    }

    /**
     * Category/area match is a ranking/display signal only — it no longer
     * gates single-request view access, so an approved-but-mismatched
     * Provider can view (still without private fields), with
     * match_level reflecting the mismatch.
     */
    public function test_mismatched_approved_provider_can_view_with_a_non_full_match_level(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $otherCategory = Category::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($otherCategory, $area);

        $this->actingAs($provider)->get("/requests/{$serviceRequest->id}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->missing('request.address_text')
                ->missing('request.customer')
                ->where('request.match_level', 'partial')
        );
    }

    public function test_pending_provider_cannot_view(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);

        $unapprovedProvider = User::factory()->provider()->create();
        ProviderProfile::factory()->forUser($unapprovedProvider)->create(); // pending

        $this->actingAs($unapprovedProvider)->get("/requests/{$serviceRequest->id}")->assertForbidden();
    }

    public function test_customer_and_admin_viewers_have_no_match_level(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();

        foreach ([$customer, $admin] as $viewer) {
            $this->actingAs($viewer)->get("/requests/{$serviceRequest->id}")->assertInertia(
                fn (Assert $page) => $page->missing('request.match_level')
            );
        }
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

    /**
     * moderation_status is exposed to the owning Customer only — never to
     * Admin (which already has its own dedicated Resource shape via
     * Admin\ServiceRequestModerationController) and never to a Provider
     * (see test_matching_approved_provider_can_view_but_not_see_private_fields
     * below for the Provider-omission assertion).
     */
    public function test_owner_sees_moderation_status_reflecting_the_actual_value(): void
    {
        $customer = User::factory()->create();
        $visibleRequest = ServiceRequest::factory()->forCustomer($customer)->create();
        $hiddenRequest = ServiceRequest::factory()->forCustomer($customer)->hidden()->create();

        $this->actingAs($customer)->get("/requests/{$visibleRequest->id}")->assertInertia(
            fn (Assert $page) => $page->where('request.moderation_status', 'visible')
        );
        $this->actingAs($customer)->get("/requests/{$hiddenRequest->id}")->assertInertia(
            fn (Assert $page) => $page->where('request.moderation_status', 'hidden')
        );
    }

    public function test_hidden_request_still_shows_address_text_to_its_owner(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->hidden()->create();

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page->where('request.moderation_status', 'hidden')
                ->has('request.address_text')
        );
    }

    public function test_own_request_list_exposes_moderation_status_per_row(): void
    {
        $customer = User::factory()->create();
        ServiceRequest::factory()->forCustomer($customer)->hidden()->create();

        $this->actingAs($customer)->get('/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.data.0.moderation_status', 'hidden')
        );
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
                ->where('request.title_translation.status', null)
                ->where('request.title_translation.source_locale', 'en')
                ->where('request.title_translation.original', 'Fix my leaking AC')
                ->where('request.description_translation.status', null)
                ->where('request.description_translation.original', 'Water is dripping.')
        );
    }

    public function test_translation_metadata_status_is_null_when_no_translation_row_exists_for_the_viewer_locale(): void
    {
        // A plain factory-created ServiceRequest has no translation rows at
        // all (only CreateServiceRequestAction creates them, synchronously,
        // via the real HTTP flow) — this is a defensive edge case, not a
        // reachable real-world path, but the Resource must still degrade
        // to "nothing to show" rather than error or report a misleading
        // status.
        $customer = User::factory()->create();
        $customer->locale = 'vi';
        $customer->save();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create([
            'title' => 'Fix my leaking AC',
            'description' => 'Water is dripping.',
            'source_locale' => 'en',
        ]);

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page
                ->where('request.title_translation.status', null)
                ->where('request.description_translation.status', null)
        );
    }

    public function test_translation_metadata_reports_pending_while_translation_is_not_yet_complete(): void
    {
        $customer = User::factory()->create();
        $customer->locale = 'ja';
        $customer->save();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create([
            'title' => 'Fix my leaking AC',
            'description' => 'Water is dripping.',
            'source_locale' => 'en',
        ]);
        // Pending is the factory default — reflects the row's real state
        // immediately after creation, before the translation job runs.
        ServiceRequestTranslation::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'locale' => 'ja',
        ]);

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page
                ->where('request.title', 'Fix my leaking AC')
                ->where('request.title_translation.status', 'pending')
                ->where('request.description_translation.status', 'pending')
        );
    }

    public function test_translation_metadata_reports_failed_when_translation_failed(): void
    {
        $customer = User::factory()->create();
        $customer->locale = 'ja';
        $customer->save();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create([
            'title' => 'Fix my leaking AC',
            'description' => 'Water is dripping.',
            'source_locale' => 'en',
        ]);
        ServiceRequestTranslation::factory()->failed()->create([
            'service_request_id' => $serviceRequest->id,
            'locale' => 'ja',
        ]);

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page
                ->where('request.title', 'Fix my leaking AC')
                ->where('request.title_translation.status', 'failed')
                ->where('request.description_translation.status', 'failed')
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
                ->where('request.title_translation.status', 'completed')
                ->where('request.title_translation.original', 'Fix my leaking AC')
                ->where('request.description_translation.status', 'completed')
                ->where('request.description_translation.original', 'Water is dripping.')
        );
    }

    public function test_deactivated_category_and_area_disappear_from_the_create_form(): void
    {
        $customer = User::factory()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();

        $category->is_active = false;
        $category->save();
        $area->is_active = false;
        $area->save();

        $this->actingAs($customer)->get('/requests/create')->assertInertia(
            fn (Assert $page) => $page
                ->where('categories', fn ($categories) => ! collect($categories)->pluck('id')->contains($category->id))
                ->where('areas', fn ($areas) => ! collect($areas)->pluck('id')->contains($area->id))
        );
    }

    public function test_an_existing_requests_category_name_still_resolves_after_deactivation(): void
    {
        $category = Category::factory()->create();
        CategoryTranslation::factory()->for($category)->create(['locale' => 'en', 'name' => 'Electrical Work']);
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);

        $category->is_active = false;
        $category->save();

        // is_active only affects whether the category is *offered* on the
        // create/edit pickers (test above) — it must not affect name
        // resolution for a request that already references it.
        $this->actingAs($serviceRequest->customer)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page->where('request.category.name', 'Electrical Work')
        );
    }

    public function test_deactivating_a_category_does_not_remove_existing_provider_category_associations(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);
        $countBefore = DB::table('provider_categories')->count();

        $category->is_active = false;
        $category->save();

        $this->assertSame($countBefore, DB::table('provider_categories')->count());
        $this->assertTrue($provider->providerProfile->fresh()->categories->contains($category));
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

    private function editPayload(ServiceRequest $serviceRequest, array $overrides = []): array
    {
        return array_merge([
            'title' => $serviceRequest->title,
            'description' => $serviceRequest->description,
            'category_id' => $serviceRequest->category_id,
            'area_id' => $serviceRequest->area_id,
            'address_text' => $serviceRequest->address_text,
            'urgency' => $serviceRequest->urgency->value,
        ], $overrides);
    }

    public function test_owner_can_view_the_edit_form_with_db_original_text_and_fixed_source_locale(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create([
            'title' => 'Original title',
            'description' => 'Original description',
            'source_locale' => 'ja',
        ]);
        // A completed translation exists for 'en' — the edit form must
        // still show the DB original (Japanese), never this English text,
        // regardless of the viewer's own UI locale.
        ServiceRequestTranslation::factory()->completed()->create([
            'service_request_id' => $serviceRequest->id,
            'locale' => 'en',
            'title' => 'Translated title',
            'description' => 'Translated description',
        ]);
        $customer->locale = 'en';
        $customer->save();

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}/edit")->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Requests/Edit')
                ->where('serviceRequest.title', 'Original title')
                ->where('serviceRequest.description', 'Original description')
                ->where('serviceRequest.source_locale', 'ja')
        );
    }

    public function test_update_succeeds_and_redirects_to_show_page(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();

        $response = $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest, ['title' => 'Updated title'])
        );

        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertSame('Updated title', $serviceRequest->fresh()->title);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        return [
            'en' => ['en'],
            'ja' => ['ja'],
            'vi' => ['vi'],
        ];
    }

    #[DataProvider('localeProvider')]
    public function test_update_flash_message_is_localized(string $locale): void
    {
        $expected = [
            'en' => 'Your request has been updated.',
            'ja' => '依頼内容を更新しました。',
            'vi' => 'Yêu cầu của bạn đã được cập nhật.',
        ];
        $customer = User::factory()->create(['locale' => $locale]);
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();

        $this->actingAs($customer)
            ->patch("/requests/{$serviceRequest->id}", $this->editPayload($serviceRequest))
            ->assertSessionHas('status', $expected[$locale]);
    }

    public function test_source_locale_cannot_be_changed_via_edit(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create(['source_locale' => 'en']);

        // UpdateServiceRequestRequest doesn't even accept a source_locale
        // field, so submitting one is simply ignored, not rejected.
        $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest) + ['source_locale' => 'vi']
        )->assertRedirect(route('requests.show', $serviceRequest));

        $this->assertSame('en', $serviceRequest->fresh()->source_locale);
    }

    public function test_title_change_resets_translations_to_pending_and_dispatches_jobs(): void
    {
        Queue::fake();
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create(['source_locale' => 'en']);
        ServiceRequestTranslation::factory()->completed()->create(['service_request_id' => $serviceRequest->id, 'locale' => 'ja']);
        ServiceRequestTranslation::factory()->completed()->create(['service_request_id' => $serviceRequest->id, 'locale' => 'vi']);

        $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest, ['title' => 'A brand new title'])
        )->assertRedirect(route('requests.show', $serviceRequest));

        foreach (['ja', 'vi'] as $locale) {
            $translation = ServiceRequestTranslation::where('service_request_id', $serviceRequest->id)->where('locale', $locale)->firstOrFail();
            $this->assertSame(TranslationStatus::Pending, $translation->translation_status);
            $this->assertNull($translation->translated_at);
            // Matches UpdateOfferAction's own convention exactly: the row's
            // text is overwritten with the new source text (not nulled)
            // while status flips back to pending.
            $this->assertSame('A brand new title', $translation->title);
        }
        Queue::assertPushed(TranslateServiceRequestJob::class, 2);
    }

    public function test_description_change_alone_also_resets_translations(): void
    {
        Queue::fake();
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create(['source_locale' => 'en']);
        ServiceRequestTranslation::factory()->completed()->create(['service_request_id' => $serviceRequest->id, 'locale' => 'ja']);

        $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest, ['description' => 'A brand new description'])
        );

        $translation = ServiceRequestTranslation::where('service_request_id', $serviceRequest->id)->where('locale', 'ja')->firstOrFail();
        $this->assertSame(TranslationStatus::Pending, $translation->translation_status);
        Queue::assertPushed(TranslateServiceRequestJob::class);
    }

    public function test_update_description_one_byte_over_ten_thousand_utf8_bytes_is_rejected(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();

        $response = $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest, ['description' => str_repeat('a', 10_001)])
        );

        $response->assertInvalid(['description']);
    }

    public function test_update_description_at_exactly_ten_thousand_utf8_bytes_succeeds(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();
        // See test_description_at_exactly_ten_thousand_utf8_bytes_succeeds
        // for why 'é' x 5,000 hits both the character and byte limits at
        // once.
        $description = str_repeat('é', 5_000);

        $response = $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest, ['description' => $description])
        );

        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertSame($description, $serviceRequest->fresh()->description);
    }

    public function test_category_area_address_urgency_only_changes_do_not_touch_translations(): void
    {
        Queue::fake();
        $customer = User::factory()->create();
        $newCategory = Category::factory()->create();
        $newArea = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create(['source_locale' => 'en']);
        $translation = ServiceRequestTranslation::factory()->completed()->create([
            'service_request_id' => $serviceRequest->id,
            'locale' => 'ja',
            'title' => 'Already translated title',
        ]);
        $translatedAt = $translation->translated_at;

        $this->actingAs($customer)->patch("/requests/{$serviceRequest->id}", $this->editPayload($serviceRequest, [
            'category_id' => $newCategory->id,
            'area_id' => $newArea->id,
            'address_text' => 'A brand new address',
            'urgency' => 'urgent',
        ]));

        $fresh = $translation->fresh();
        $this->assertSame(TranslationStatus::Completed, $fresh->translation_status);
        $this->assertSame('Already translated title', $fresh->title);
        $this->assertEquals($translatedAt, $fresh->translated_at);
        Queue::assertNotPushed(TranslateServiceRequestJob::class);
    }

    public function test_keeps_the_current_category_and_area_even_after_they_are_deactivated(): void
    {
        $customer = User::factory()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create([
            'category_id' => $category->id,
            'area_id' => $area->id,
        ]);
        $category->update(['is_active' => false]);
        $area->update(['is_active' => false]);

        $response = $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest, ['category_id' => $category->id, 'area_id' => $area->id])
        );

        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertSame($category->id, $serviceRequest->fresh()->category_id);
    }

    public function test_edit_form_includes_the_inactive_current_category_and_area_as_extra_options(): void
    {
        $customer = User::factory()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create([
            'category_id' => $category->id,
            'area_id' => $area->id,
        ]);
        $category->update(['is_active' => false]);
        $area->update(['is_active' => false]);

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}/edit")->assertInertia(
            fn (Assert $page) => $page
                ->where('categories', fn ($categories) => collect($categories)->pluck('id')->contains($category->id))
                ->where('areas', fn ($areas) => collect($areas)->pluck('id')->contains($area->id))
        );
    }

    public function test_cannot_switch_to_a_different_inactive_category_or_area(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();
        $neverUsedInactiveCategory = Category::factory()->inactive()->create();
        $neverUsedInactiveArea = Area::factory()->inactive()->create();

        $responseA = $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest, ['category_id' => $neverUsedInactiveCategory->id])
        );
        $responseA->assertInvalid(['category_id']);

        $responseB = $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest, ['area_id' => $neverUsedInactiveArea->id])
        );
        $responseB->assertInvalid(['area_id']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function canEditFalseCaseProvider(): array
    {
        return [
            'hidden' => ['hidden'],
            'assigned' => ['assigned'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[DataProvider('canEditFalseCaseProvider')]
    public function test_owner_cannot_edit_when_the_request_is_not_open_and_visible(string $case): void
    {
        $customer = User::factory()->create();
        $serviceRequest = match ($case) {
            'hidden' => ServiceRequest::factory()->forCustomer($customer)->hidden()->create(),
            'assigned' => ServiceRequest::factory()->forCustomer($customer)->create(['status' => ServiceRequestStatus::Assigned]),
            'cancelled' => ServiceRequest::factory()->forCustomer($customer)->cancelled()->create(),
        };

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}/edit")->assertForbidden();
        $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest)
        )->assertForbidden();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function anyOfferStatusProvider(): array
    {
        return [
            'pending' => ['pending'],
            'accepted' => ['accepted'],
            'rejected' => ['rejected'],
            'withdrawn' => ['withdrawn'],
            'cancelled' => ['cancelled'],
        ];
    }

    /**
     * Editing must be blocked for as long as ANY Offer exists, in ANY
     * status — not only a still-pending one.
     */
    #[DataProvider('anyOfferStatusProvider')]
    public function test_owner_cannot_edit_once_any_offer_exists_regardless_of_its_status(string $offerStatus): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $factory = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider);
        match ($offerStatus) {
            'pending' => $factory->create(),
            'accepted' => $factory->accepted()->create(),
            'rejected' => $factory->rejected()->create(),
            'withdrawn' => $factory->withdrawn()->create(),
            'cancelled' => $factory->cancelled()->create(),
        };

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}/edit")->assertForbidden();
        $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest)
        )->assertForbidden();
    }

    public function test_other_customer_provider_and_admin_cannot_edit(): void
    {
        $owner = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($owner)->create();
        $otherCustomer = User::factory()->create();
        $provider = User::factory()->provider()->create();
        $admin = User::factory()->admin()->create();

        foreach ([$otherCustomer, $provider, $admin] as $user) {
            $this->actingAs($user)->get("/requests/{$serviceRequest->id}/edit")->assertForbidden();
            $this->actingAs($user)->patch(
                "/requests/{$serviceRequest->id}",
                $this->editPayload($serviceRequest)
            )->assertForbidden();
        }
    }

    public function test_can_edit_prop_is_true_for_the_owner_on_an_open_visible_offer_free_request(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page->where('can_edit', true)
        );
    }

    public function test_can_edit_prop_is_false_once_an_offer_exists(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page->where('can_edit', false)
        );
    }

    public function test_can_edit_prop_is_false_for_admin_even_though_admin_can_view(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page->where('can_edit', false)
        );
    }

    public function test_remove_photo_ids_rejects_an_id_belonging_to_another_request(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();
        $otherRequest = ServiceRequest::factory()->create();
        $otherPhoto = RequestPhoto::factory()->create(['service_request_id' => $otherRequest->id]);

        $response = $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest, ['remove_photo_ids' => [$otherPhoto->id]])
        );

        $response->assertInvalid(['remove_photo_ids.0']);
        $this->assertDatabaseHas('request_photos', ['id' => $otherPhoto->id, 'service_request_id' => $otherRequest->id]);
    }

    public function test_remove_photo_ids_rejects_a_duplicate_id(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();
        $photo = RequestPhoto::factory()->create(['service_request_id' => $serviceRequest->id]);

        $response = $this->actingAs($customer)->patch(
            "/requests/{$serviceRequest->id}",
            $this->editPayload($serviceRequest, ['remove_photo_ids' => [$photo->id, $photo->id]])
        );

        $response->assertInvalid(['remove_photo_ids.0']);
    }

    public function test_five_photos_remove_two_and_add_two_succeeds(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();
        $photos = RequestPhoto::factory()->count(5)->sequence(fn ($sequence) => ['sort_order' => $sequence->index])->create([
            'service_request_id' => $serviceRequest->id,
        ]);
        $toRemove = $photos->take(2)->pluck('id')->all();

        $response = $this->actingAs($customer)->patch("/requests/{$serviceRequest->id}", array_merge(
            $this->editPayload($serviceRequest),
            [
                'remove_photo_ids' => $toRemove,
                'photos' => [$this->realJpegFile('a.jpg'), $this->realJpegFile('b.jpg')],
            ],
        ));

        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertSame(5, RequestPhoto::where('service_request_id', $serviceRequest->id)->count());
        foreach ($toRemove as $id) {
            $this->assertDatabaseMissing('request_photos', ['id' => $id]);
        }
    }

    public function test_five_photos_remove_one_and_add_two_is_rejected(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();
        $photos = RequestPhoto::factory()->count(5)->sequence(fn ($sequence) => ['sort_order' => $sequence->index])->create([
            'service_request_id' => $serviceRequest->id,
        ]);
        $toRemove = $photos->take(1)->pluck('id')->all();

        $response = $this->actingAs($customer)->patch("/requests/{$serviceRequest->id}", array_merge(
            $this->editPayload($serviceRequest),
            [
                'remove_photo_ids' => $toRemove,
                'photos' => [$this->realJpegFile('a.jpg'), $this->realJpegFile('b.jpg')],
            ],
        ));

        $response->assertInvalid(['photos']);
        $this->assertSame(5, RequestPhoto::where('service_request_id', $serviceRequest->id)->count());
    }

    public function test_db_failure_during_update_removes_only_the_new_photo_and_keeps_existing_ones(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();
        $existingPhoto = RequestPhoto::factory()->create(['service_request_id' => $serviceRequest->id, 'object_key' => 'service-requests/existing.jpg']);
        Storage::disk(config('filesystems.default'))->put('service-requests/existing.jpg', 'fake-existing-contents');

        $thrown = null;
        try {
            app(UpdateServiceRequestAction::class)->handle(
                $customer,
                $serviceRequest,
                [
                    'title' => $serviceRequest->title,
                    'description' => $serviceRequest->description,
                    'category_id' => 999_999, // FK violation inside the transaction
                    'area_id' => $serviceRequest->area_id,
                    'address_text' => $serviceRequest->address_text,
                    'urgency' => $serviceRequest->urgency->value,
                ],
                [$this->realJpegFile()],
                []
            );
        } catch (QueryException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Expected the original DB exception to be re-thrown.');
        // Only the one pre-existing photo remains — the newly uploaded one
        // was never attached to a committed row, and was compensated away.
        $this->assertSame(1, RequestPhoto::where('service_request_id', $serviceRequest->id)->count());
        $this->assertDatabaseHas('request_photos', ['id' => $existingPhoto->id]);
        $newlyStoredFiles = array_filter(
            Storage::disk(config('filesystems.default'))->allFiles('service-requests'),
            fn ($path) => $path !== 'service-requests/existing.jpg'
        );
        $this->assertEmpty($newlyStoredFiles, 'The newly uploaded photo should have been cleaned up.');
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists('service-requests/existing.jpg'), 'The pre-existing photo must be untouched.');
    }

    /**
     * CreateOfferAction already locks the same ServiceRequest row before
     * creating an Offer (confirmed by reading that Action directly), so —
     * in a real concurrent scenario — this row lock is what serializes an
     * edit submission against an Offer arriving in between. This test
     * cannot reproduce true concurrency (PHPUnit is single-threaded); it
     * verifies the half that actually matters: once an Offer has already
     * committed, the edit Action's own re-check after acquiring the lock
     * correctly rejects a stale submission, including compensating away
     * any newly uploaded photo.
     */
    public function test_offer_arriving_before_the_edit_action_runs_is_rejected_and_compensates_the_new_photo(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        // Simulates "an Offer arrived after the edit page loaded, but
        // before this submit reached the server".
        Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        $thrown = null;
        try {
            app(UpdateServiceRequestAction::class)->handle(
                $customer,
                $serviceRequest,
                $this->editPayload($serviceRequest),
                [$this->realJpegFile()],
                []
            );
        } catch (InvalidServiceRequestTransitionException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame(0, RequestPhoto::where('service_request_id', $serviceRequest->id)->count());
        $this->assertEmpty(
            Storage::disk(config('filesystems.default'))->allFiles('service-requests'),
            'The newly uploaded photo should have been compensated away.'
        );
    }

    public function test_match_level_recomputes_from_the_new_category_and_area_after_an_edit(): void
    {
        $originalCategory = Category::factory()->create();
        $originalArea = Area::factory()->create();
        $newCategory = Category::factory()->create();
        $newArea = Area::factory()->create();
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create([
            'category_id' => $originalCategory->id,
            'area_id' => $originalArea->id,
        ]);
        $provider = $this->approvedProviderFor($newCategory, $newArea);

        // Before the edit: the Provider is registered for neither the
        // original category nor area -> no match.
        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.data.0.match_level', 'none')
        );

        $this->actingAs($customer)->patch("/requests/{$serviceRequest->id}", $this->editPayload($serviceRequest, [
            'category_id' => $newCategory->id,
            'area_id' => $newArea->id,
        ]));

        // After the edit: no cache/denormalized column to invalidate —
        // match_level is always computed live, so the Feed reflects the
        // new category/area on its very next render.
        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.data.0.match_level', 'full')
        );
    }

    public function test_update_via_a_real_multipart_post_request_with_method_spoofing_succeeds(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();

        // The frontend can't send a native multipart PATCH body (PHP can't
        // parse one), so it POSTs with a spoofed _method field instead —
        // this exercises that exact real HTTP shape, not just a
        // Laravel-test-client ->patch() call (which handles the spoofing
        // transparently and would not by itself prove the frontend's
        // approach actually works end to end).
        $response = $this->actingAs($customer)->post(
            "/requests/{$serviceRequest->id}",
            array_merge($this->editPayload($serviceRequest, ['title' => 'Spoofed multipart update']), [
                '_method' => 'PATCH',
                'photos' => [$this->realJpegFile()],
            ])
        );

        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertSame('Spoofed multipart update', $serviceRequest->fresh()->title);
        $this->assertSame(1, RequestPhoto::where('service_request_id', $serviceRequest->id)->count());
    }
}
