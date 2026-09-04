<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Seeds a small set of service categories for local development and
 * testing only.
 *
 * These are illustrative placeholders (drawn from the examples in
 * docs/BLUEPRINT.md's Provider persona list), NOT a decision about which
 * categories the real Da Nang launch will offer — that is still an open
 * question in the Blueprint ("20 未確定事項", item 1). Do not treat this
 * seeder's contents as production master data.
 *
 * Idempotent: safe to run repeatedly via updateOrCreate() on `slug` /
 * `(category_id, locale)`.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $categories = [
            [
                'slug' => 'aircon-repair',
                'sort_order' => 10,
                'translations' => [
                    'en' => 'Air-con Repair',
                    'ja' => 'エアコン修理',
                    'vi' => 'Sửa điều hòa',
                ],
            ],
            [
                'slug' => 'plumbing',
                'sort_order' => 20,
                'translations' => [
                    'en' => 'Plumbing',
                    'ja' => '水道修理',
                    'vi' => 'Sửa ống nước',
                ],
            ],
            [
                'slug' => 'electrical-work',
                'sort_order' => 30,
                'translations' => [
                    'en' => 'Electrical Work',
                    'ja' => '電気工事',
                    'vi' => 'Sửa điện',
                ],
            ],
            [
                'slug' => 'cleaning',
                'sort_order' => 40,
                'translations' => [
                    'en' => 'Cleaning',
                    'ja' => '清掃',
                    'vi' => 'Dọn dẹp',
                ],
            ],
            [
                'slug' => 'moving',
                'sort_order' => 50,
                'translations' => [
                    'en' => 'Moving',
                    'ja' => '引っ越し',
                    'vi' => 'Chuyển nhà',
                ],
            ],
        ];

        foreach ($categories as $data) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $data['slug']],
                ['is_active' => true, 'sort_order' => $data['sort_order']],
            );

            foreach ($data['translations'] as $locale => $name) {
                $category->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $name],
                );
            }
        }
    }
}
