<?php

namespace App\Listings;

use App\Enums\HouseTimezone;
use App\Enums\Room;

final class ListingOptions
{
    public const DURATIONS = [15, 30, 45, 60];

    public const NOTICES = [0, 2, 12, 24, 48];

    /** @return array<string, list<string|int>> */
    public static function toArray(): array
    {
        return [
            'states' => array_keys(UsStates::NAMES),
            'timezones' => array_column(HouseTimezone::cases(), 'value'),
            'durations' => self::DURATIONS,
            'notices' => self::NOTICES,
            'rooms' => array_column(Room::cases(), 'value'),
        ];
    }
}
