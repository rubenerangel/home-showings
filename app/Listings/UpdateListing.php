<?php

namespace App\Listings;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Edits a listing of the seller, with what is locked now (R13). */
final class UpdateListing
{
    /** @param array<string, mixed> $data already validated */
    public function handle(User $seller, string $listingId, array $data): Listing
    {
        return DB::transaction(function () use ($seller, $listingId, $data) {
            // Inserting a window or a showing takes KEY SHARE on this row through its foreign key,
            // so both wait for this transaction, or this one waits and then sees them.
            $listing = $seller->listings()->lockForUpdate()->findOrFail($listingId);

            if ($refusals = ListingLocks::of($listing)->refusals($listing, $data)) {
                throw ValidationException::withMessages($refusals);
            }
            $listing->update($data);

            return $listing;
        });
    }
}
