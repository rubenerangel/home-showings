<?php

namespace App\Scheduling;

use App\Models\AvailabilityRange;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** A range is past once it is over in Pacific time (R12). */
class AddAvailability
{
    public function handle(User $buyer, Interval $interval): AvailabilityRange
    {
        if ($interval->end <= HouseClock::lastLocalNow()) {
            throw ValidationException::withMessages(['end' => 'This time is already over.']);
        }

        return $buyer->availabilityRanges()->create([
            'local_starts_at' => $interval->start,
            'local_ends_at' => $interval->end,
        ]);
    }
}
