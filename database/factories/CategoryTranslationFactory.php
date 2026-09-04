<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryTranslation>
 */
class CategoryTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'locale' => 'en',
            'name' => fake()->words(2, true),
        ];
    }
}
