<?php

namespace Tests\Unit;

use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestUrgency;
use App\Enums\TranslationStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\RequestPhoto;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_are_correct_types(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $fresh = $serviceRequest->fresh();

        $this->assertSame(ServiceRequestStatus::Open, $fresh->status);
        $this->assertSame(ServiceRequestUrgency::Normal, $fresh->urgency);
        $this->assertSame(ServiceRequestModerationStatus::Visible, $fresh->moderation_status);
    }

    public function test_relations_resolve(): void
    {
        $customer = User::factory()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()
            ->forCustomer($customer)
            ->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $photo = RequestPhoto::factory()->create(['service_request_id' => $serviceRequest->id]);
        $translation = ServiceRequestTranslation::factory()->create(['service_request_id' => $serviceRequest->id]);

        $this->assertTrue($serviceRequest->customer->is($customer));
        $this->assertTrue($serviceRequest->category->is($category));
        $this->assertTrue($serviceRequest->area->is($area));
        $this->assertTrue($serviceRequest->photos->contains($photo));
        $this->assertTrue($serviceRequest->translations->contains($translation));
    }

    public function test_factory_guarded_attributes_persist_after_fresh(): void
    {
        $serviceRequest = ServiceRequest::factory()->cancelled()->create();
        $fresh = $serviceRequest->fresh();

        $this->assertNotNull($fresh->customer_id);
        $this->assertSame(ServiceRequestStatus::Cancelled, $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
    }

    public function test_hidden_attributes_are_excluded_from_array_serialization(): void
    {
        $serviceRequest = ServiceRequest::factory()->create([
            'address_text' => '123 Some Street',
            'lat' => 16.05,
            'lng' => 108.2,
        ]);

        $array = $serviceRequest->fresh()->toArray();

        $this->assertArrayNotHasKey('address_text', $array);
        $this->assertArrayNotHasKey('lat', $array);
        $this->assertArrayNotHasKey('lng', $array);
    }

    public function test_translated_title_and_description_fall_back_to_source(): void
    {
        $serviceRequest = ServiceRequest::factory()->create([
            'title' => 'Original title',
            'description' => 'Original description',
            'source_locale' => 'en',
        ]);

        // No translation row at all for 'ja'.
        $this->assertSame('Original title', $serviceRequest->translatedTitleFor('ja'));
        $this->assertSame('Original description', $serviceRequest->translatedDescriptionFor('ja'));

        // A pending translation row should still fall back to source.
        ServiceRequestTranslation::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'locale' => 'ja',
            'title' => '[ja] should not be used yet',
            'description' => '[ja] should not be used yet',
            'translation_status' => TranslationStatus::Pending,
        ]);
        $fresh = $serviceRequest->fresh();
        $this->assertSame('Original title', $fresh->translatedTitleFor('ja'));

        // A failed translation should also fall back to source.
        $fresh->translations->first()->update(['translation_status' => TranslationStatus::Failed]);
        $fresh = $serviceRequest->fresh();
        $this->assertSame('Original title', $fresh->translatedTitleFor('ja'));

        // A completed translation should be used.
        $fresh->translations->first()->update(['translation_status' => TranslationStatus::Completed]);
        $fresh = $serviceRequest->fresh();
        $this->assertSame('[ja] should not be used yet', $fresh->translatedTitleFor('ja'));

        // Requesting the source_locale itself always returns the original,
        // regardless of any translation rows.
        $this->assertSame('Original title', $fresh->translatedTitleFor('en'));
    }
}
