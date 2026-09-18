<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The launch category list (7 service categories plus the catch-all
     * "other" category). Categories are upserted by `slug` and translations
     * by `(category_id, locale)`, so a category that already exists keeps
     * its id — and therefore keeps every existing provider_categories /
     * service_requests row that already points at it. This is real product
     * master data (not seed/demo data), which is why it is applied here
     * rather than only in CategorySeeder (production never runs seeders).
     */
    private function categories(): array
    {
        return [
            ['slug' => 'motorbike-repair', 'sort_order' => 10, 'names' => [
                'en' => 'Motorbike Repair', 'ja' => 'バイク修理', 'vi' => 'Sửa xe máy',
            ]],
            ['slug' => 'aircon-repair', 'sort_order' => 20, 'names' => [
                'en' => 'Air-con Repair', 'ja' => 'エアコン修理', 'vi' => 'Sửa điều hòa',
            ]],
            ['slug' => 'cleaning', 'sort_order' => 30, 'names' => [
                'en' => 'Cleaning', 'ja' => '清掃', 'vi' => 'Vệ sinh',
            ]],
            ['slug' => 'education', 'sort_order' => 40, 'names' => [
                'en' => 'Lessons & Tutoring', 'ja' => '学習・家庭教師', 'vi' => 'Gia sư & Học tập',
            ]],
            ['slug' => 'plumbing', 'sort_order' => 50, 'names' => [
                'en' => 'Plumbing', 'ja' => '水道修理', 'vi' => 'Sửa ống nước',
            ]],
            ['slug' => 'electrical-work', 'sort_order' => 60, 'names' => [
                'en' => 'Electrical Work', 'ja' => '電気工事', 'vi' => 'Sửa điện',
            ]],
            ['slug' => 'moving', 'sort_order' => 70, 'names' => [
                'en' => 'Moving', 'ja' => '引っ越し', 'vi' => 'Chuyển nhà',
            ]],
            ['slug' => 'other', 'sort_order' => 80, 'names' => [
                'en' => 'Other', 'ja' => 'その他', 'vi' => 'Khác',
            ]],
        ];
    }

    /**
     * Field values to restore on rollback, for the 5 categories that
     * existed before this migration. `vi` is null where the translation is
     * unchanged by this migration (only `cleaning`'s vi name changes).
     */
    private function previousState(): array
    {
        return [
            'aircon-repair' => ['sort_order' => 10, 'vi' => null],
            'plumbing' => ['sort_order' => 20, 'vi' => null],
            'electrical-work' => ['sort_order' => 30, 'vi' => null],
            'cleaning' => ['sort_order' => 40, 'vi' => 'Dọn dẹp'],
            'moving' => ['sort_order' => 50, 'vi' => null],
        ];
    }

    public function up(): void
    {
        DB::transaction(function () {
            foreach ($this->categories() as $data) {
                $categoryId = $this->upsertCategory($data['slug'], $data['sort_order']);

                foreach ($data['names'] as $locale => $name) {
                    $this->upsertTranslation($categoryId, $locale, $name);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach ($this->previousState() as $slug => $state) {
                DB::table('categories')->where('slug', $slug)->update([
                    'sort_order' => $state['sort_order'],
                    'updated_at' => now(),
                ]);

                if ($state['vi'] !== null) {
                    $category = DB::table('categories')->where('slug', $slug)->first();
                    if ($category) {
                        DB::table('category_translations')
                            ->where('category_id', $category->id)
                            ->where('locale', 'vi')
                            ->update(['name' => $state['vi'], 'updated_at' => now()]);
                    }
                }
            }

            // motorbike-repair/education/other are deliberately NOT deleted
            // here, and this rollback is deliberately incomplete for them —
            // this is intentional, not an oversight. categories.id is
            // referenced by provider_categories (cascadeOnDelete) and
            // service_requests (restrictOnDelete): a hard delete would
            // either silently wipe a Provider's pivot row the moment any
            // Provider selected one of these categories, or make this
            // entire down() fail outright the moment any ServiceRequest
            // referenced one. Soft-disabling instead leaves every related
            // row untouched either way, consistent with the rest of the app
            // never physically deleting a Category/Area (see Phase 9's
            // Admin category management — is_active is the only supported
            // removal path).
            DB::table('categories')->whereIn('slug', ['motorbike-repair', 'education', 'other'])->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
        });
    }

    private function upsertCategory(string $slug, int $sortOrder): int
    {
        $existing = DB::table('categories')->where('slug', $slug)->first();

        if ($existing) {
            DB::table('categories')->where('id', $existing->id)->update([
                'is_active' => true,
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);

            return $existing->id;
        }

        return DB::table('categories')->insertGetId([
            'slug' => $slug,
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertTranslation(int $categoryId, string $locale, string $name): void
    {
        $existing = DB::table('category_translations')
            ->where('category_id', $categoryId)
            ->where('locale', $locale)
            ->first();

        if ($existing) {
            DB::table('category_translations')->where('id', $existing->id)->update([
                'name' => $name,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('category_translations')->insert([
            'category_id' => $categoryId,
            'locale' => $locale,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
