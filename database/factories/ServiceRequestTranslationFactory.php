<?php

namespace Database\Factories;

use App\Enums\TranslationStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestTranslation;
use App\Support\ServiceRequestHasher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequestTranslation>
 */
class ServiceRequestTranslationFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);
        $description = fake()->paragraph();

        return [
            'service_request_id' => ServiceRequest::factory(),
            'locale' => 'ja',
            'title' => $title,
            'description' => $description,
            'source_hash' => ServiceRequestHasher::hash($title, $description),
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
