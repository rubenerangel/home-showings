<?php

namespace Database\Factories;

use App\Models\AvailabilityRange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AvailabilityRange> */
class AvailabilityRangeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory()->buyer(),
            'local_starts_at' => '2027-03-10 08:00:00',
            'local_ends_at' => '2027-03-10 18:00:00',
        ];
    }

    public function between(string $date, string $start, string $end): static
    {
        return $this->state(['local_starts_at' => "$date $start:00", 'local_ends_at' => "$date $end:00"]);
    }
}
