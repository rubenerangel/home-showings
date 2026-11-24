<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'role' => Role::Buyer];
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
