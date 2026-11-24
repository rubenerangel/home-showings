<?php

namespace App\Listings;

use App\Enums\ShowingStatus;
use App\Models\Listing;

/** R13: what cannot change now. To rely on the answer, hold the lock on the listing row. */
final readonly class ListingLocks
{
    private function __construct(public bool $location, public bool $timezone) {}

    public static function of(Listing $listing): self
    {
        return new self(
            location: $listing->showings()
                ->where('status', ShowingStatus::Confirmed)
                ->where('ends_at', '>', now())
                ->exists(),
            timezone: $listing->windows()->whereNull('closed_at')->exists() || $listing->showings()->exists(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string> message by field, only for locked fields that would change
     */
    public function refusals(Listing $listing, array $data): array
    {
        $refusals = [];
        foreach ($this->location ? ['address', 'city', 'state'] : [] as $field) {
            if ($data[$field] !== $listing->$field) {
                $refusals[$field] = 'This cannot change while the home has upcoming showings.';
            }
        }
        if ($this->timezone && $data['timezone'] !== $listing->timezone->value) {
            $refusals['timezone'] = 'This cannot change once the home has an open window or any showing.';
        }

        return $refusals;
    }
}
