<?php

namespace App\Scheduling;

use App\Enums\HouseTimezone;
use Carbon\CarbonImmutable;

final class HouseClock
{
    /** The UTC instant at which the clock of the house shows this local time (R9). */
    public static function toInstant(CarbonImmutable $local, HouseTimezone $timezone): CarbonImmutable
    {
        return $local->shiftTimezone($timezone->value)->utc();
    }

    /** Whether a daylight-saving change happens on the date of this local time (R11). */
    public static function changesOn(CarbonImmutable $local, HouseTimezone $timezone): bool
    {
        $midnight = $local->startOfDay()->shiftTimezone($timezone->value);

        return $midnight->utcOffset() !== $midnight->addDay()->utcOffset();
    }

    /** What the clock shows right now, comparable with the local columns. */
    public static function localNow(HouseTimezone $timezone): CarbonImmutable
    {
        return CarbonImmutable::now($timezone->value)->shiftTimezone('UTC');
    }
}
