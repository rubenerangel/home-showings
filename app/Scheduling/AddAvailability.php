<?php

namespace App\Scheduling;

use App\Enums\HouseTimezone;
use App\Models\AvailabilityRange;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** A range is past once it is over in Pacific time (R12). */
final class AddAvailability
{
    public function handle(User $buyer, Interval $interval): AvailabilityRange
    {
        if ($interval->end <= HouseClock::localNow(HouseTimezone::Pacific)) {
            throw ValidationException::withMessages(['end' => 'This time is already over.']);
        }

        return $buyer->availabilityRanges()->create([
            'local_starts_at' => $interval->start,
            'local_ends_at' => $interval->end,
        ]);
    }
}
