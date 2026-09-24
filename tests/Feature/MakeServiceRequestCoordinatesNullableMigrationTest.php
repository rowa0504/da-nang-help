<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class MakeServiceRequestCoordinatesNullableMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, int> */
    private array $createdServiceRequestIds = [];

    /** @var array<int, int> */
    private array $createdCategoryIds = [];

    /** @var array<int, int> */
    private array $createdAreaIds = [];

    /** @var array<int, int> */
    private array $createdUserIds = [];

    /**
     * Migration files return an anonymous class instance directly from
     * `require` — this loads the exact same migration `artisan migrate`
     * would run, without going through the full migrator.
     */
    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_23_000001_make_service_request_coordinates_nullable.php');
    }

    /**
     * Builds a row and tracks the User/Category/Area ids it references, so
     * tearDown() can delete exactly these rows — never the official 8
     * launch categories/24 translations that RefreshDatabase's own
     * 2026_09_16_000001 data migration seeds as shared baseline data.
     */
    private function rawRow(array $overrides = []): array
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();

        $this->createdUserIds[] = $user->id;
        $this->createdCategoryIds[] = $category->id;
        $this->createdAreaIds[] = $area->id;

        return array_merge([
            'customer_id' => $user->id,
            'category_id' => $category->id,
            'area_id' => $area->id,
            'title' => 'Test request',
            'description' => 'Test description',
            'source_locale' => 'en',
            'address_text' => '123 Test Street',
            'lat' => 16.05,
            'lng' => 108.2,
            'urgency' => 'normal',
            'status' => 'open',
            'moderation_status' => 'visible',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    private function insertRow(array $overrides = []): int
    {
        $id = DB::table('service_requests')->insertGetId($this->rawRow($overrides));
        $this->createdServiceRequestIds[] = $id;

        return $id;
    }

    private function assertColumnsAreNullable(): void
    {
        $id = $this->insertRow(['lat' => null, 'lng' => null]);
        $row = DB::table('service_requests')->find($id);

        $this->assertNull($row->lat);
        $this->assertNull($row->lng);
    }

    /**
     * DDL (ALTER TABLE, run by up()/down()) implicitly commits the current
     * transaction on MySQL, which defeats RefreshDatabase's per-test
     * rollback: anything already committed at that point becomes permanent
     * in the shared test database, not just this test's private state. A
     * blanket "delete everything from categories/areas/users" here would
     * therefore permanently destroy the 8 official launch categories / 24
     * translations that RefreshDatabase's own data migration seeds as
     * baseline data for every test in the suite — so this deletes ONLY the
     * specific rows each test created (tracked by id, in FK-safe order:
     * service_requests before the rows it references; deleting a tracked
     * category also cascades its own translations, but these test
     * categories never have any), and always restores the nullable schema
     * regardless of pass/fail.
     */
    protected function tearDown(): void
    {
        DB::table('service_requests')->whereIn('id', $this->createdServiceRequestIds)->delete();
        DB::table('categories')->whereIn('id', $this->createdCategoryIds)->delete();
        DB::table('areas')->whereIn('id', $this->createdAreaIds)->delete();
        DB::table('users')->whereIn('id', $this->createdUserIds)->delete();

        $this->migration()->up();

        parent::tearDown();
    }

    public function test_up_allows_null_coordinates(): void
    {
        // RefreshDatabase already ran this migration's up() as part of the
        // normal migrate cycle, so the columns are already nullable here —
        // this just confirms it.
        $this->assertColumnsAreNullable();
    }

    public function test_up_preserves_existing_non_null_coordinates(): void
    {
        $migration = $this->migration();

        try {
            $migration->down();

            $id = $this->insertRow(['lat' => 16.0544, 'lng' => 108.2022]);

            $migration->up();

            $row = DB::table('service_requests')->find($id);
            $this->assertEqualsWithDelta(16.0544, (float) $row->lat, 0.0001);
            $this->assertEqualsWithDelta(108.2022, (float) $row->lng, 0.0001);
        } finally {
            $migration->up();
        }

        $this->assertColumnsAreNullable();
    }

    public function test_down_refuses_to_run_when_a_null_coordinate_row_exists(): void
    {
        $serviceRequest = ServiceRequest::factory()->create(); // lat/lng default to null
        $this->createdServiceRequestIds[] = $serviceRequest->id;
        $this->createdUserIds[] = $serviceRequest->customer_id;
        $this->createdCategoryIds[] = $serviceRequest->category_id;
        $this->createdAreaIds[] = $serviceRequest->area_id;

        $migration = $this->migration();

        try {
            $migration->down();
            $this->fail('Expected down() to throw when a null-coordinate row exists.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('null lat or lng', $e->getMessage());
        } finally {
            // The guard runs before any schema change, so this is a no-op
            // in practice — kept unconditional so the restoration
            // guarantee doesn't depend on down()'s internal ordering.
            $migration->up();
        }

        $this->assertColumnsAreNullable();
    }

    public function test_down_restores_the_not_null_constraint_when_no_null_coordinates_exist(): void
    {
        $migration = $this->migration();

        try {
            $migration->down();

            try {
                DB::table('service_requests')->insert($this->rawRow(['lat' => null, 'lng' => null]));
                $this->fail('Expected inserting a null coordinate to violate the restored NOT NULL constraint.');
            } catch (QueryException $e) {
                // expected — the column is NOT NULL again.
            }
        } finally {
            $migration->up();
        }

        $this->assertColumnsAreNullable();
    }
}
