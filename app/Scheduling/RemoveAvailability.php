<?php

namespace App\Scheduling;

use App\Models\AvailabilityRange;

class RemoveAvailability
{
    public function handle(AvailabilityRange $range): void
    {
        $range->delete();
    }
}
