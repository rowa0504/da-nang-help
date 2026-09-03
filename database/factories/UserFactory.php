<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * `role` is intentionally excluded from the model's Fillable attribute
     * (see App\Models\User), so it cannot be set via the definition() array
     * (mass assignment). Assign it directly on the instance instead.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->role ??= UserRole::Customer;
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a Provider.
     */
    public function provider(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->role = UserRole::Provider;
        });
    }

    /**
     * Indicate that the user is an Admin.
     */
    public function admin(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->role = UserRole::Admin;
        });
    }
}
