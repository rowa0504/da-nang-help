<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UpsertLaunchCategoriesMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Migration files return an anonymous class instance directly from
     * `require` — this loads the exact same migration `artisan migrate`
     * would run, without going through the full migrator.
     */
    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_16_000001_upsert_launch_categories.php');
    }

    /**
     * Wipes categories/category_translations and recreates the
     * pre-migration 5-category state by hand, at the exact field values
     * this migration's own previousState() expects to roll back to —
     * simulating "production before this migration has ever run".
     * RefreshDatabase already ran this migration's up() once as part of
     * the normal migrate cycle, so every test starts by clearing that.
     */
    private function seedLegacyFiveCategories(): array
    {
        DB::table('category_translations')->delete();
        DB::table('categories')->delete();

        $legacy = [
            ['slug' => 'aircon-repair', 'sort_order' => 10, 'names' => ['en' => 'Air-con Repair', 'ja' => 'エアコン修理', 'vi' => 'Sửa điều hòa']],
            ['slug' => 'plumbing', 'sort_order' => 20, 'names' => ['en' => 'Plumbing', 'ja' => '水道修理', 'vi' => 'Sửa ống nước']],
            ['slug' => 'electrical-work', 'sort_order' => 30, 'names' => ['en' => 'Electrical Work', 'ja' => '電気工事', 'vi' => 'Sửa điện']],
            ['slug' => 'cleaning', 'sort_order' => 40, 'names' => ['en' => 'Cleaning', 'ja' => '清掃', 'vi' => 'Dọn dẹp']],
            ['slug' => 'moving', 'sort_order' => 50, 'names' => ['en' => 'Moving', 'ja' => '引っ越し', 'vi' => 'Chuyển nhà']],
        ];

        $ids = [];
        foreach ($legacy as $data) {
            $id = DB::table('categories')->insertGetId([
                'slug' => $data['slug'],
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => $data['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[$data['slug']] = $id;

            foreach ($data['names'] as $locale => $name) {
                DB::table('category_translations')->insert([
                    'category_id' => $id,
                    'locale' => $locale,
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $ids;
    }

    public function test_up_creates_eight_categories_and_twenty_four_translations_from_an_empty_table(): void
    {
        DB::table('category_translations')->delete();
        DB::table('categories')->delete();
        $this->assertDatabaseCount('categories', 0);

        $this->migration()->up();

        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseCount('category_translations', 24);

        $slugsInOrder = Category::query()->orderBy('sort_order')->pluck('slug')->all();
        $this->assertSame(
            ['motorbike-repair', 'aircon-repair', 'cleaning', 'education', 'plumbing', 'electrical-work', 'moving', 'other'],
            $slugsInOrder,
        );

        $other = Category::query()->where('slug', 'other')->with('translations')->firstOrFail();
        $this->assertSame(80, $other->sort_order);
        $this->assertSame('Other', $other->nameFor('en'));
        $this->assertSame('その他', $other->nameFor('ja'));
        $this->assertSame('Khác', $other->nameFor('vi'));
    }

    public function test_up_preserves_existing_category_ids_and_updates_sort_order_and_translations(): void
    {
        $legacyIds = $this->seedLegacyFiveCategories();

        $this->migration()->up();

        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseCount('category_translations', 24);

        foreach ($legacyIds as $slug => $id) {
            $this->assertSame($id, Category::query()->where('slug', $slug)->value('id'));
        }

        $this->assertSame(20, Category::query()->where('slug', 'aircon-repair')->value('sort_order'));
        $this->assertSame(50, Category::query()->where('slug', 'plumbing')->value('sort_order'));
        $this->assertSame(60, Category::query()->where('slug', 'electrical-work')->value('sort_order'));
        $this->assertSame(30, Category::query()->where('slug', 'cleaning')->value('sort_order'));
        $this->assertSame(70, Category::query()->where('slug', 'moving')->value('sort_order'));

        $cleaning = Category::query()->where('slug', 'cleaning')->with('translations')->firstOrFail();
        $this->assertSame('Vệ sinh', $cleaning->nameFor('vi'));
        $this->assertSame('Cleaning', $cleaning->nameFor('en'));
        $this->assertSame('清掃', $cleaning->nameFor('ja'));
    }

    public function test_up_does_not_disturb_existing_pivot_or_service_request_references(): void
    {
        $legacyIds = $this->seedLegacyFiveCategories();

        $area = Area::factory()->create();
        $provider = ProviderProfile::factory()->create();
        $provider->categories()->attach($legacyIds['plumbing']);

        $customer = User::factory()->create();
        $request = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $legacyIds['plumbing'],
            'area_id' => $area->id,
        ]);

        $this->migration()->up();

        $this->assertTrue($provider->categories()->where('categories.id', $legacyIds['plumbing'])->exists());
        $this->assertSame($legacyIds['plumbing'], $request->fresh()->category_id);
    }

    public function test_down_reverts_the_five_original_categories_without_deleting_the_three_new_ones(): void
    {
        $this->seedLegacyFiveCategories();
        $this->migration()->up();

        $this->migration()->down();

        $this->assertDatabaseCount('categories', 8); // nothing physically deleted

        $this->assertSame(10, Category::query()->where('slug', 'aircon-repair')->value('sort_order'));
        $this->assertSame(20, Category::query()->where('slug', 'plumbing')->value('sort_order'));
        $this->assertSame(30, Category::query()->where('slug', 'electrical-work')->value('sort_order'));
        $this->assertSame(40, Category::query()->where('slug', 'cleaning')->value('sort_order'));
        $this->assertSame(50, Category::query()->where('slug', 'moving')->value('sort_order'));

        $cleaning = Category::query()->where('slug', 'cleaning')->with('translations')->firstOrFail();
        $this->assertSame('Dọn dẹp', $cleaning->nameFor('vi'));

        foreach (['motorbike-repair', 'education', 'other'] as $slug) {
            $this->assertFalse(Category::query()->where('slug', $slug)->firstOrFail()->is_active);
        }
    }

    public function test_down_does_not_break_a_service_request_that_references_a_new_category(): void
    {
        $this->migration()->up(); // creates all 8, including motorbike-repair

        $area = Area::factory()->create();
        $customer = User::factory()->create();
        $motorbike = Category::query()->where('slug', 'motorbike-repair')->firstOrFail();
        $request = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $motorbike->id,
            'area_id' => $area->id,
        ]);

        $this->migration()->down();

        $this->assertDatabaseCount('categories', 8);
        $this->assertSame($motorbike->id, $request->fresh()->category_id);
        $this->assertFalse($motorbike->fresh()->is_active);
    }
}
