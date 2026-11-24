<?php

namespace App\Listings;

use App\Enums\HouseTimezone;

final class ListingOptions
{
    public const DURATIONS = [15, 30, 45, 60];

    public const NOTICES = [0, 2, 12, 24, 48];

    public const STATES = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS',
        'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC',
        'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
    ];

    /** @return array<string, list<string|int>> */
    public static function toArray(): array
    {
        return [
            'states' => self::STATES,
            'timezones' => array_column(HouseTimezone::cases(), 'value'),
            'durations' => self::DURATIONS,
            'notices' => self::NOTICES,
        ];
    }
}
