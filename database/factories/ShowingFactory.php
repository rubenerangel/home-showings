<?php

namespace Database\Factories;

use App\Enums\ShowingStatus;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Showing> */
class ShowingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'showing_window_id' => ShowingWindow::factory(),
            'listing_id' => fn (array $attributes) => ShowingWindow::findOrFail($attributes['showing_window_id'])->listing_id,
            'buyer_id' => User::factory()->buyer(),
            'starts_at' => CarbonImmutable::parse('2027-03-10 17:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2027-03-10 17:30:00', 'UTC'),
            'status' => ShowingStatus::Confirmed,
            'cancelled_at' => null,
        ];
    }

    public function cancelled(ShowingStatus $status): static
    {
        return $this->state(['status' => $status, 'cancelled_at' => CarbonImmutable::parse('2027-03-01 00:00:00', 'UTC')]);
    }
}
