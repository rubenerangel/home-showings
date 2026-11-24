<?php

namespace App\Scheduling;

use App\Models\Listing;

final readonly class ListingMatch
{
    /** @param non-empty-list<Slot> $slots ordered by start */
    public function __construct(public Listing $listing, public array $slots) {}
}
