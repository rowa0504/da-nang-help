<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates one Customer / Provider / Admin account for manual, local
 * verification of Phase 2 auth flows. Must never run outside local/testing
 * environments — it creates accounts with a well-known password.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::factory()->create([
            'name' => 'Demo Customer',
            'email' => 'customer@example.test',
        ]);

        User::factory()->provider()->create([
            'name' => 'Demo Provider',
            'email' => 'provider@example.test',
        ]);

        User::factory()->admin()->create([
            'name' => 'Demo Admin',
            'email' => 'admin@example.test',
        ]);
    }
}
