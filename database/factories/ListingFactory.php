<?php

namespace Database\Factories;

use App\Enums\HouseTimezone;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Listing> */
class ListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => User::factory()->seller(),
            'address' => fake()->streetAddress(),
            'city' => 'Denver',
            'state' => 'CO',
            'price' => 450_000,
            'bedrooms' => 3,
            'bathrooms' => 2.5,
            'description' => null,
            'timezone' => HouseTimezone::Mountain,
            'showing_duration_minutes' => 30,
            'min_notice_hours' => 2,
        ];
    }

    public function in(HouseTimezone $timezone): static
    {
        return $this->state(['timezone' => $timezone]);
    }
}
