<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hoa Vang',
            'slug' => 'hoa-vang',
            'is_active' => true,
        ], $overrides);
    }

    public function test_customer_and_provider_are_forbidden_from_all_routes(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->provider()->create();
        $area = Area::factory()->create();

        foreach ([$customer, $provider] as $user) {
            $this->actingAs($user)->get('/admin/areas')->assertForbidden();
            $this->actingAs($user)->get('/admin/areas/create')->assertForbidden();
            $this->actingAs($user)->post('/admin/areas', $this->payload())->assertForbidden();
            $this->actingAs($user)->get("/admin/areas/{$area->id}/edit")->assertForbidden();
            $this->actingAs($user)->patch("/admin/areas/{$area->id}", $this->payload())->assertForbidden();
        }
    }

    public function test_admin_can_create_an_area(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/areas', $this->payload());

        $response->assertRedirect(route('admin.areas.index'));
        $area = Area::where('slug', 'hoa-vang')->firstOrFail();
        $this->assertSame('Hoa Vang', $area->name);
        $this->assertTrue($area->is_active);
    }

    public function test_create_and_update_flash_messages_are_localized(): void
    {
        $expected = [
            'en' => ['created' => 'Area created.', 'updated' => 'Area updated.'],
            'ja' => ['created' => 'エリアを作成しました。', 'updated' => 'エリアを更新しました。'],
            'vi' => ['created' => 'Đã tạo khu vực.', 'updated' => 'Đã cập nhật khu vực.'],
        ];

        foreach ($expected as $locale => $messages) {
            $admin = User::factory()->admin()->create(['locale' => $locale]);

            $this->actingAs($admin)
                ->post('/admin/areas', $this->payload(['slug' => "hoa-vang-{$locale}"]))
                ->assertSessionHas('status', $messages['created']);

            $area = Area::factory()->create();
            $this->actingAs($admin)
                ->patch("/admin/areas/{$area->id}", $this->payload())
                ->assertSessionHas('status', $messages['updated']);
        }
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        Area::factory()->create(['slug' => 'hoa-vang']);

        $this->actingAs($admin)->post('/admin/areas', $this->payload())->assertInvalid(['slug']);
    }

    public function test_admin_can_update_name_and_active_status(): void
    {
        $admin = User::factory()->admin()->create();
        $area = Area::factory()->create(['name' => 'Old Name', 'is_active' => true]);

        $response = $this->actingAs($admin)->patch("/admin/areas/{$area->id}", $this->payload(['name' => 'New Name', 'is_active' => false]));

        $response->assertRedirect(route('admin.areas.index'));
        $fresh = $area->fresh();
        $this->assertSame('New Name', $fresh->name);
        $this->assertFalse($fresh->is_active);
    }

    public function test_update_ignores_a_submitted_slug_and_never_changes_it(): void
    {
        $admin = User::factory()->admin()->create();
        $area = Area::factory()->create(['slug' => 'original-slug']);

        $this->actingAs($admin)->patch("/admin/areas/{$area->id}", array_merge(
            $this->payload(),
            ['slug' => 'attempted-new-slug']
        ));

        $this->assertSame('original-slug', $area->fresh()->slug);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $area = Area::factory()->create();

        $this->get('/admin/areas')->assertRedirect(route('login'));
        $this->get("/admin/areas/{$area->id}/edit")->assertRedirect(route('login'));
    }
}
