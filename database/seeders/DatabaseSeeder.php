<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // DemoUserSeeder guards itself against running outside local/testing,
        // but the check is duplicated here so this intent is visible without
        // having to open the other file.
        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
