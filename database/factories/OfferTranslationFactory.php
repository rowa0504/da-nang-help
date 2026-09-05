<?php

namespace Database\Factories;

use App\Enums\TranslationStatus;
use App\Models\Offer;
use App\Models\OfferTranslation;
use App\Support\OfferHasher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferTranslation>
 */
class OfferTranslationFactory extends Factory
{
    public function definition(): array
    {
        $message = fake()->paragraph();

        return [
            'offer_id' => Offer::factory(),
            'locale' => 'ja',
            'message' => $message,
            'source_hash' => OfferHasher::hash($message, 'en'),
            'translation_status' => TranslationStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'translation_status' => TranslationStatus::Completed,
            'translated_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'translation_status' => TranslationStatus::Failed,
        ]);
    }
}
