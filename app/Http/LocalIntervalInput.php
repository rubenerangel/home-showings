<?php

namespace App\Http;

use App\Scheduling\Interval;
use Illuminate\Http\Request;

/** A date and two times on the clock of the house. Nothing ends at midnight (R12). */
final class LocalIntervalInput
{
    public static function from(Request $request): Interval
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
        ]);

        return Interval::parse("{$data['date']} {$data['start']}", "{$data['date']} {$data['end']}");
    }
}
