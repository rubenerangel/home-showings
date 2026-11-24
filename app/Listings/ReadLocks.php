<?php

namespace App\Listings;

use App\Models\Listing;
use Carbon\CarbonImmutable;

/** To rely on the answer, hold the lock on the listing row. */
class ReadLocks
{
    public function of(Listing $listing): ListingLocks
    {
        return new ListingLocks(
            location: $listing->showings()->active(CarbonImmutable::now())->exists(),
            timezone: $listing->windows()->whereNull('closed_at')->exists() || $listing->showings()->exists(),
        );
    }
}
