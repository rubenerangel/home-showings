<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    private static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$password ??= Hash::make('password'),
            'role' => Role::Buyer,
        ];
    }

    public function seller(): static
    {
        return $this->state(['role' => Role::Seller]);
    }

    public function buyer(): static
    {
        return $this->state(['role' => Role::Buyer]);
    }
}
