<?php

namespace App\Http;

use App\Enums\HouseTimezone;
use App\Listings\ListingOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ListingInput
{
    /** PostgreSQL stores UTF-8 without null bytes (R19). */
    private const TEXT = 'regex:/^[^\x00]*$/uD';

    /** @return array<string, mixed> */
    public static function from(Request $request): array
    {
        return $request->validate([
            'address' => ['bail', 'required', 'string', 'max:255', self::TEXT],
            'city' => ['bail', 'required', 'string', 'max:255', self::TEXT],
            'state' => ['bail', 'required', 'string', Rule::in(ListingOptions::STATES)],
            'price' => ['bail', 'required', 'integer', 'min:1', 'max:2147483647'],
            'bedrooms' => ['bail', 'required', 'integer', 'min:0', 'max:99'],
            'bathrooms' => ['bail', 'required', 'decimal:0,1', 'min:0.5', 'max:99.5', 'multiple_of:0.5'],
            'description' => ['bail', 'present', 'nullable', 'string', 'max:1000', self::TEXT],
            'timezone' => ['bail', 'required', 'string', Rule::enum(HouseTimezone::class)],
            'showing_duration_minutes' => ['bail', 'required', 'integer', Rule::in(ListingOptions::DURATIONS)],
            'min_notice_hours' => ['bail', 'required', 'integer', Rule::in(ListingOptions::NOTICES)],
        ], attributes: [
            'timezone' => 'time zone',
            'showing_duration_minutes' => 'showing duration',
            'min_notice_hours' => 'minimum notice',
        ]);
    }
}
