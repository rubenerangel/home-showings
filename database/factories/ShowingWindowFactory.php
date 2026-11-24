<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ShowingWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShowingWindow> */
class ShowingWindowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'local_starts_at' => '2027-03-10 10:00:00',
            'local_ends_at' => '2027-03-10 12:00:00',
            'duration_minutes' => 30,
            'closed_at' => null,
        ];
    }

    /** House time, for example between('2027-03-10', '10:00', '11:10'). */
    public function between(string $date, string $start, string $end): static
    {
        return $this->state(['local_starts_at' => "$date $start:00", 'local_ends_at' => "$date $end:00"]);
    }
}
