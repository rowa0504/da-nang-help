<?php

namespace Tests\Unit;

use App\Enums\OfferStatus;
use App\Enums\TranslationStatus;
use App\Models\Offer;
use App\Models\OfferTranslation;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_are_correct_types(): void
    {
        $offer = Offer::factory()->create(['price' => 123.45]);
        $fresh = $offer->fresh();

        $this->assertSame(OfferStatus::Pending, $fresh->status);
        $this->assertSame('123.45', $fresh->price);
    }

    public function test_relations_resolve(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $provider = User::factory()->provider()->create();
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();
        $translation = OfferTranslation::factory()->create(['offer_id' => $offer->id]);

        $this->assertTrue($offer->serviceRequest->is($serviceRequest));
        $this->assertTrue($offer->provider->is($provider));
        $this->assertTrue($offer->translations->contains($translation));
    }

    public function test_factory_guarded_status_persists_after_fresh(): void
    {
        $offer = Offer::factory()->accepted()->create();

        $this->assertSame(OfferStatus::Accepted, $offer->fresh()->status);
    }

    public function test_translated_message_falls_back_to_source(): void
    {
        $offer = Offer::factory()->create([
            'message' => 'Original message',
            'source_locale' => 'en',
        ]);

        $this->assertSame('Original message', $offer->translatedMessageFor('ja'));

        OfferTranslation::factory()->create([
            'offer_id' => $offer->id,
            'locale' => 'ja',
            'message' => '[ja] should not be used yet',
            'translation_status' => TranslationStatus::Pending,
        ]);
        $fresh = $offer->fresh();
        $this->assertSame('Original message', $fresh->translatedMessageFor('ja'));

        $fresh->translations->first()->update(['translation_status' => TranslationStatus::Failed]);
        $fresh = $offer->fresh();
        $this->assertSame('Original message', $fresh->translatedMessageFor('ja'));

        $fresh->translations->first()->update(['translation_status' => TranslationStatus::Completed]);
        $fresh = $offer->fresh();
        $this->assertSame('[ja] should not be used yet', $fresh->translatedMessageFor('ja'));

        // Source locale itself always returns the original.
        $this->assertSame('Original message', $fresh->translatedMessageFor('en'));
    }
}
