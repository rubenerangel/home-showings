<?php

namespace App\Listings;

use App\Models\Listing;
use App\Models\User;

class CreateListing
{
    /** @param array<string, mixed> $data already validated */
    public function handle(User $seller, array $data): Listing
    {
        return $seller->listings()->create($data);
    }
}
