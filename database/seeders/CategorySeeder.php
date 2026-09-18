<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Seeds the launch category list for local development and testing.
 *
 * This mirrors the production data applied by the
 * `2026_09_16_000001_upsert_launch_categories` migration — this seeder is
 * for local/testing environments only (production never runs seeders), so
 * the two must be kept in sync by hand whenever the category list changes.
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
                'slug' => 'motorbike-repair',
                'sort_order' => 10,
                'translations' => [
                    'en' => 'Motorbike Repair',
                    'ja' => 'バイク修理',
                    'vi' => 'Sửa xe máy',
                ],
            ],
            [
                'slug' => 'aircon-repair',
                'sort_order' => 20,
                'translations' => [
                    'en' => 'Air-con Repair',
                    'ja' => 'エアコン修理',
                    'vi' => 'Sửa điều hòa',
                ],
            ],
            [
                'slug' => 'cleaning',
                'sort_order' => 30,
                'translations' => [
                    'en' => 'Cleaning',
                    'ja' => '清掃',
                    'vi' => 'Vệ sinh',
                ],
            ],
            [
                'slug' => 'education',
                'sort_order' => 40,
                'translations' => [
                    'en' => 'Lessons & Tutoring',
                    'ja' => '学習・家庭教師',
                    'vi' => 'Gia sư & Học tập',
                ],
            ],
            [
                'slug' => 'plumbing',
                'sort_order' => 50,
                'translations' => [
                    'en' => 'Plumbing',
                    'ja' => '水道修理',
                    'vi' => 'Sửa ống nước',
                ],
            ],
            [
                'slug' => 'electrical-work',
                'sort_order' => 60,
                'translations' => [
                    'en' => 'Electrical Work',
                    'ja' => '電気工事',
                    'vi' => 'Sửa điện',
                ],
            ],
            [
                'slug' => 'moving',
                'sort_order' => 70,
                'translations' => [
                    'en' => 'Moving',
                    'ja' => '引っ越し',
                    'vi' => 'Chuyển nhà',
                ],
            ],
            [
                'slug' => 'other',
                'sort_order' => 80,
                'translations' => [
                    'en' => 'Other',
                    'ja' => 'その他',
                    'vi' => 'Khác',
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
