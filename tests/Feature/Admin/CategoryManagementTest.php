<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\UpdateCategoryAction;
use App\Exceptions\InvalidCategoryHierarchyException;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'gardening',
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 0,
            'names' => ['en' => 'Gardening', 'ja' => '庭仕事', 'vi' => 'Làm vườn'],
        ], $overrides);
    }

    public function test_customer_and_provider_are_forbidden_from_all_routes(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->provider()->create();
        $category = Category::factory()->create();

        foreach ([$customer, $provider] as $user) {
            $this->actingAs($user)->get('/admin/categories')->assertForbidden();
            $this->actingAs($user)->get('/admin/categories/create')->assertForbidden();
            $this->actingAs($user)->post('/admin/categories', $this->payload())->assertForbidden();
            $this->actingAs($user)->get("/admin/categories/{$category->id}/edit")->assertForbidden();
            $this->actingAs($user)->patch("/admin/categories/{$category->id}", $this->payload())->assertForbidden();
        }
    }

    public function test_admin_can_create_a_category_with_three_locale_translations(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/categories', $this->payload());

        $response->assertRedirect(route('admin.categories.index'));
        $category = Category::where('slug', 'gardening')->firstOrFail();
        $this->assertCount(3, $category->translations);
        $this->assertSame('Gardening', $category->translations->firstWhere('locale', 'en')->name);
        $this->assertSame('庭仕事', $category->translations->firstWhere('locale', 'ja')->name);
        $this->assertSame('Làm vườn', $category->translations->firstWhere('locale', 'vi')->name);
    }

    public function test_create_and_update_flash_messages_are_localized(): void
    {
        $expected = [
            'en' => ['created' => 'Category created.', 'updated' => 'Category updated.'],
            'ja' => ['created' => 'カテゴリを作成しました。', 'updated' => 'カテゴリを更新しました。'],
            'vi' => ['created' => 'Đã tạo danh mục.', 'updated' => 'Đã cập nhật danh mục.'],
        ];

        foreach ($expected as $locale => $messages) {
            $admin = User::factory()->admin()->create(['locale' => $locale]);

            $this->actingAs($admin)
                ->post('/admin/categories', $this->payload(['slug' => "gardening-{$locale}"]))
                ->assertSessionHas('status', $messages['created']);

            $category = Category::factory()->create();
            $this->actingAs($admin)
                ->patch("/admin/categories/{$category->id}", $this->payload())
                ->assertSessionHas('status', $messages['updated']);
        }
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->create(['slug' => 'gardening']);

        $this->actingAs($admin)->post('/admin/categories', $this->payload())->assertInvalid(['slug']);
    }

    public function test_admin_can_update_existing_translations_without_creating_new_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        foreach (['en' => 'Old EN', 'ja' => 'Old JA', 'vi' => 'Old VI'] as $locale => $name) {
            CategoryTranslation::factory()->for($category)->create(['locale' => $locale, 'name' => $name]);
        }

        $response = $this->actingAs($admin)->patch("/admin/categories/{$category->id}", $this->payload([
            'names' => ['en' => 'New EN', 'ja' => 'New JA', 'vi' => 'New VI'],
            'is_active' => false,
            'sort_order' => 5,
        ]));

        $response->assertRedirect(route('admin.categories.index'));
        $fresh = $category->fresh();
        $this->assertCount(3, $fresh->translations);
        $this->assertSame('New EN', $fresh->translations->firstWhere('locale', 'en')->name);
        $this->assertFalse($fresh->is_active);
        $this->assertSame(5, $fresh->sort_order);
    }

    public function test_update_ignores_a_submitted_slug_and_never_changes_it(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['slug' => 'original-slug']);

        $this->actingAs($admin)->patch("/admin/categories/{$category->id}", array_merge(
            $this->payload(),
            ['slug' => 'attempted-new-slug']
        ));

        $this->assertSame('original-slug', $category->fresh()->slug);
    }

    public function test_admin_can_create_a_category_with_a_parent(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();

        $this->actingAs($admin)->post('/admin/categories', $this->payload(['parent_id' => $parent->id]))->assertRedirect();

        $child = Category::where('slug', 'gardening')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
    }

    public function test_self_reference_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->patch("/admin/categories/{$category->id}", $this->payload(['parent_id' => $category->id]))
            ->assertInvalid(['parent_id']);
    }

    public function test_direct_circular_reference_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Category::factory()->create();
        $b = Category::factory()->create(['parent_id' => $a->id]);

        // A is currently B's parent; trying to make B the parent of A would
        // create a 2-node cycle (A -> B -> A).
        $this->expectException(InvalidCategoryHierarchyException::class);
        app(UpdateCategoryAction::class)->handle($a, $this->payload(['parent_id' => $b->id]));
    }

    public function test_indirect_circular_reference_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Category::factory()->create();
        $b = Category::factory()->create(['parent_id' => $a->id]);
        $c = Category::factory()->create(['parent_id' => $b->id]);

        // A -> B -> C already exists; setting A's parent to C would close
        // the loop (A -> C -> B -> A).
        $this->expectException(InvalidCategoryHierarchyException::class);
        app(UpdateCategoryAction::class)->handle($a, $this->payload(['parent_id' => $c->id]));
    }

    public function test_a_valid_non_cyclic_reparenting_succeeds(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Category::factory()->create();
        $b = Category::factory()->create();

        $this->actingAs($admin)->patch("/admin/categories/{$b->id}", $this->payload(['parent_id' => $a->id]))->assertRedirect();

        $this->assertSame($a->id, $b->fresh()->parent_id);
    }

    public function test_category_list_orders_children_immediately_after_their_parent(): void
    {
        $admin = User::factory()->admin()->create();
        // A (root, sort_order=5) has child B (sort_order=1, lower than
        // both roots); C (root, sort_order=10). A naive sort_order-only
        // ordering would put B before both roots; the correct tree order
        // is A, B, C.
        $a = Category::factory()->create(['sort_order' => 5]);
        $b = Category::factory()->create(['parent_id' => $a->id, 'sort_order' => 1]);
        $c = Category::factory()->create(['sort_order' => 10]);

        $this->actingAs($admin)->get('/admin/categories')->assertInertia(
            fn (Assert $page) => $page
                ->where('categories.0.id', $a->id)
                ->where('categories.1.id', $b->id)
                ->where('categories.1.depth', 1)
                ->where('categories.2.id', $c->id)
                ->where('categories.2.depth', 0)
        );
    }

    public function test_category_list_ties_within_a_level_are_broken_by_id(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Category::factory()->create(['sort_order' => 1]);
        $second = Category::factory()->create(['sort_order' => 1]);

        $this->actingAs($admin)->get('/admin/categories')->assertInertia(
            fn (Assert $page) => $page
                ->where('categories.0.id', $first->id)
                ->where('categories.1.id', $second->id)
        );
    }

    public function test_edit_form_excludes_the_category_itself_and_its_descendants_from_parent_options(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Category::factory()->create();
        $b = Category::factory()->create(['parent_id' => $a->id]);
        $c = Category::factory()->create(['parent_id' => $b->id]);
        $unrelated = Category::factory()->create();

        $this->actingAs($admin)->get("/admin/categories/{$a->id}/edit")->assertInertia(
            fn (Assert $page) => $page->where('categoryOptions', function ($options) use ($a, $b, $c, $unrelated) {
                $ids = collect($options)->pluck('id')->all();

                return ! in_array($a->id, $ids, true)
                    && ! in_array($b->id, $ids, true)
                    && ! in_array($c->id, $ids, true)
                    && in_array($unrelated->id, $ids, true);
            })
        );
    }

    public function test_hierarchy_error_message_is_localized(): void
    {
        $expectations = [
            'en' => 'circular category hierarchy',
            'ja' => '循環したカテゴリ階層',
            'vi' => 'danh mục vòng lặp',
        ];

        foreach ($expectations as $locale => $expectedSubstring) {
            $admin = User::factory()->admin()->create();
            $admin->locale = $locale;
            $admin->save();
            $a = Category::factory()->create();
            $b = Category::factory()->create(['parent_id' => $a->id]);

            $response = $this->actingAs($admin)->patch("/admin/categories/{$a->id}", $this->payload(['parent_id' => $b->id]));

            $response->assertInvalid(['parent_id']);
            $this->assertStringContainsString($expectedSubstring, session('errors')->first('parent_id'));
        }
    }

    public function test_category_list_does_not_trigger_n_plus_one_as_category_count_grows(): void
    {
        $admin = User::factory()->admin()->create();
        $three = Category::factory()->count(3)->create();
        foreach ($three as $category) {
            CategoryTranslation::factory()->for($category)->create(['locale' => 'en']);
        }

        $this->actingAs($admin)->get('/admin/categories')->assertOk();

        DB::enableQueryLog();
        $this->actingAs($admin)->get('/admin/categories')->assertOk();
        $queryCountForThree = count(DB::getQueryLog());
        DB::flushQueryLog();

        $nine = Category::factory()->count(9)->create();
        foreach ($nine as $category) {
            CategoryTranslation::factory()->for($category)->create(['locale' => 'en']);
        }
        DB::flushQueryLog();

        $this->actingAs($admin)->get('/admin/categories')->assertOk();
        $queryCountForTwelve = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForThree, $queryCountForTwelve);
    }
}
