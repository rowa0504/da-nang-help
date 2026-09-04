<?php

namespace Tests\Unit;

use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_translations_relation_works(): void
    {
        $category = Category::factory()->create();
        CategoryTranslation::factory()->for($category)->create(['locale' => 'en', 'name' => 'Cleaning']);

        $this->assertCount(1, $category->translations);
        $this->assertSame('Cleaning', $category->translations->first()->name);
    }

    public function test_category_translation_locale_is_unique_per_category(): void
    {
        $category = Category::factory()->create();
        CategoryTranslation::factory()->for($category)->create(['locale' => 'en']);

        $this->expectException(QueryException::class);
        CategoryTranslation::factory()->for($category)->create(['locale' => 'en']);
    }

    public function test_category_casts_are_correct_types(): void
    {
        $category = Category::factory()->create(['is_active' => 1, 'sort_order' => '5']);
        $fresh = $category->fresh();

        $this->assertIsBool($fresh->is_active);
        $this->assertIsInt($fresh->sort_order);
    }

    public function test_area_casts_is_active_to_boolean(): void
    {
        $area = Area::factory()->create(['is_active' => 1]);

        $this->assertIsBool($area->fresh()->is_active);
    }

    public function test_area_slug_is_unique(): void
    {
        Area::factory()->create(['slug' => 'hai-chau']);

        $this->expectException(QueryException::class);
        Area::factory()->create(['slug' => 'hai-chau']);
    }

    public function test_category_slug_is_unique(): void
    {
        Category::factory()->create(['slug' => 'plumbing']);

        $this->expectException(QueryException::class);
        Category::factory()->create(['slug' => 'plumbing']);
    }
}
