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
        // Each of these seeders guards itself against running outside
        // local/testing, but the check is duplicated here so this intent is
        // visible without having to open the other files. Order matters:
        // DemoProviderProfileSeeder depends on categories/areas/the demo
        // provider user already existing.
        if (app()->environment(['local', 'testing'])) {
            $this->call(CategorySeeder::class);
            $this->call(AreaSeeder::class);
            $this->call(DemoUserSeeder::class);
            $this->call(DemoProviderProfileSeeder::class);
        }
    }
}
