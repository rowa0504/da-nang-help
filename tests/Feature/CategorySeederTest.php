<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_seeder_creates_the_eight_launch_categories_in_order(): void
    {
        (new CategorySeeder())->run();

        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseCount('category_translations', 24);

        $slugsInOrder = Category::query()->orderBy('sort_order')->pluck('slug')->all();
        $this->assertSame(
            ['motorbike-repair', 'aircon-repair', 'cleaning', 'education', 'plumbing', 'electrical-work', 'moving', 'other'],
            $slugsInOrder,
        );
    }

    public function test_category_seeder_sets_the_expected_names_per_locale(): void
    {
        (new CategorySeeder())->run();

        $cleaning = Category::query()->where('slug', 'cleaning')->with('translations')->firstOrFail();
        $this->assertSame('Cleaning', $cleaning->nameFor('en'));
        $this->assertSame('清掃', $cleaning->nameFor('ja'));
        $this->assertSame('Vệ sinh', $cleaning->nameFor('vi'));

        $motorbike = Category::query()->where('slug', 'motorbike-repair')->with('translations')->firstOrFail();
        $this->assertSame('Motorbike Repair', $motorbike->nameFor('en'));
        $this->assertSame('バイク修理', $motorbike->nameFor('ja'));
        $this->assertSame('Sửa xe máy', $motorbike->nameFor('vi'));

        $education = Category::query()->where('slug', 'education')->with('translations')->firstOrFail();
        $this->assertSame('Lessons & Tutoring', $education->nameFor('en'));
        $this->assertSame('学習・家庭教師', $education->nameFor('ja'));
        $this->assertSame('Gia sư & Học tập', $education->nameFor('vi'));

        $other = Category::query()->where('slug', 'other')->with('translations')->firstOrFail();
        $this->assertSame('Other', $other->nameFor('en'));
        $this->assertSame('その他', $other->nameFor('ja'));
        $this->assertSame('Khác', $other->nameFor('vi'));
    }

    public function test_category_seeder_is_idempotent(): void
    {
        (new CategorySeeder())->run();
        (new CategorySeeder())->run();

        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseCount('category_translations', 24);
    }

    public function test_category_seeder_does_nothing_outside_local_or_testing(): void
    {
        // RefreshDatabase has already run the 2026_09_16_000001 data
        // migration (which has no environment guard, unlike the seeder),
        // so the table isn't empty by default — clear it first to isolate
        // what this test actually checks: the seeder's own environment
        // guard, not whatever the migration already populated.
        DB::table('category_translations')->delete();
        DB::table('categories')->delete();

        $this->app->instance('env', 'production');

        (new CategorySeeder())->run();

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_home_shows_the_top_four_seeded_categories_in_order(): void
    {
        (new CategorySeeder())->run();

        $this->get('/')->assertInertia(
            fn (Assert $page) => $page->component('Home')
                ->has('categories', 4)
                ->where('categories.0.slug', 'motorbike-repair')
                ->where('categories.1.slug', 'aircon-repair')
                ->where('categories.2.slug', 'cleaning')
                ->where('categories.3.slug', 'education')
        );
    }
}
