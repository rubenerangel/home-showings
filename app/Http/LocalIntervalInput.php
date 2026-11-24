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
            'date' => ['bail', 'required', 'date_format:Y-m-d'],
            'start' => ['bail', 'required', 'date_format:H:i'],
            'end' => ['bail', 'required', 'date_format:H:i', 'after:start'],
        ], [
            'required' => 'Choose a :attribute.',
            'date_format' => 'Choose a valid :attribute.',
            'end.after' => 'The end must be after the start.',
        ], ['start' => 'start time', 'end' => 'end time']);

        return Interval::parse("{$data['date']} {$data['start']}", "{$data['date']} {$data['end']}");
    }
}
