<?php

namespace Tests\Feature;

use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_user_seeder_creates_users_in_testing_environment(): void
    {
        // APP_ENV=testing per phpunit.xml, which is in the seeder's allow-list.
        (new DemoUserSeeder())->run();

        $this->assertDatabaseCount('users', 3);
    }

    public function test_demo_user_seeder_does_nothing_outside_local_or_testing(): void
    {
        $this->app->instance('env', 'production');

        (new DemoUserSeeder())->run();

        $this->assertDatabaseCount('users', 0);
    }
}
