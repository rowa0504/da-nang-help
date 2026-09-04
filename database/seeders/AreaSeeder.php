<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds Da Nang's urban districts as local development/testing fixtures.
 *
 * These are real district names used purely as realistic placeholder data;
 * they are NOT a decision about the initial launch area(s), which is still
 * an open question in docs/BLUEPRINT.md ("20 未確定事項", item 1). Do not
 * treat this seeder's contents as production master data.
 *
 * Idempotent: safe to run repeatedly via updateOrCreate() on `slug`.
 */
class AreaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $areas = ['Hai Chau', 'Thanh Khe', 'Son Tra', 'Ngu Hanh Son', 'Lien Chieu', 'Cam Le'];

        foreach ($areas as $name) {
            Area::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}
