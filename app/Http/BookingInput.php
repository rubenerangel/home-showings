<?php

namespace App\Http;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final readonly class BookingInput
{
    private function __construct(public int $windowId, public CarbonImmutable $startsAt) {}

    public static function from(Request $request): self
    {
        $data = $request->validate([
            'window_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i:s\Z'],
        ]);

        return new self((int) $data['window_id'], CarbonImmutable::parse($data['starts_at']));
    }
}
