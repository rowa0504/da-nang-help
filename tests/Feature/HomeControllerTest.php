<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function categoryWithNames(array $overrides, array $names): Category
    {
        $category = Category::factory()->create($overrides);
        foreach ($names as $locale => $name) {
            CategoryTranslation::factory()->for($category)->create(['locale' => $locale, 'name' => $name]);
        }

        return $category;
    }

    /**
     * RefreshDatabase already ran the 2026_09_16_000001 data migration,
     * which seeds the real 7 launch categories on every test run — tests
     * that assert an exact category count or order need a clean slate
     * first, or those real rows count towards the result too. Deleting
     * cascades to category_translations.
     */
    private function clearLaunchCategories(): void
    {
        Category::query()->delete();
    }

    public function test_only_active_categories_are_returned(): void
    {
        $this->clearLaunchCategories();
        $this->categoryWithNames(['is_active' => true, 'sort_order' => 1], ['en' => 'Active One']);
        $this->categoryWithNames(['is_active' => false, 'sort_order' => 2], ['en' => 'Inactive One']);

        $this->get('/')->assertInertia(
            fn (Assert $page) => $page->component('Home')
                ->has('categories', 1)
                ->where('categories.0.name', 'Active One')
        );
    }

    public function test_categories_are_ordered_by_sort_order(): void
    {
        $this->clearLaunchCategories();
        $this->categoryWithNames(['is_active' => true, 'sort_order' => 20], ['en' => 'Second']);
        $this->categoryWithNames(['is_active' => true, 'sort_order' => 10], ['en' => 'First']);

        $this->get('/')->assertInertia(
            fn (Assert $page) => $page->where('categories.0.name', 'First')->where('categories.1.name', 'Second')
        );
    }

    public function test_only_the_top_four_active_categories_are_returned(): void
    {
        foreach (range(1, 5) as $sortOrder) {
            $this->categoryWithNames(['is_active' => true, 'sort_order' => $sortOrder], ['en' => "Category {$sortOrder}"]);
        }

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('categories', 4));
    }

    public function test_category_names_resolve_per_locale(): void
    {
        $this->categoryWithNames(
            ['is_active' => true, 'sort_order' => 1],
            ['en' => 'Cleaning', 'ja' => '清掃', 'vi' => 'Dọn dẹp'],
        );

        foreach (['en' => 'Cleaning', 'ja' => '清掃', 'vi' => 'Dọn dẹp'] as $locale => $expectedName) {
            $user = \App\Models\User::factory()->create(['locale' => $locale]);
            $this->actingAs($user)->get('/')->assertInertia(
                fn (Assert $page) => $page->where('categories.0.name', $expectedName)
            );
        }
    }

    public function test_home_renders_with_no_categories(): void
    {
        $this->clearLaunchCategories();
        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Home')->has('categories', 0)
        );
    }

    public function test_home_html_includes_favicon_and_ogp_tags(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('rel="icon"', false);
        $response->assertSee('apple-touch-icon', false);
        $response->assertSee('property="og:image"', false);
        $response->assertSee('name="twitter:card"', false);
    }
}
