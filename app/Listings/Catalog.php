<?php

namespace App\Listings;

use App\Models\Listing;
use App\Scheduling\ListingMatch;
use App\Scheduling\OpenSlots;
use Illuminate\Support\Collection;

/** The homes that anyone can see. */
class Catalog
{
    private const ID = '/^[1-9][0-9]{0,17}$/D';

    public function __construct(private readonly OpenSlots $openSlots) {}

    /** @return Collection<int, ListingMatch> by state, city and price (R22) */
    public function available(): Collection
    {
        return $this->openSlots->available()->sortBy(fn (ListingMatch $match) => [
            UsStates::NAMES[$match->listing->state], mb_strtolower($match->listing->city), $match->listing->price, $match->listing->id,
        ])->values();
    }

    /** One that was opened to visits some time: the others are only of their seller (R25). */
    public function find(string $id): Listing
    {
        return Listing::query()->whereHas('windows')->with('photos')->findOrFail($id);
    }

    /** The home that someone wants to visit after signing in, if it still can be visited (R23). */
    public function toVisit(mixed $id): ?Listing
    {
        if (! (is_int($id) || is_string($id)) || preg_match(self::ID, (string) $id) !== 1) {
            return null;
        }
        $listing = Listing::query()->find($id);

        return $listing !== null && $this->openSlots->of($listing) !== [] ? $listing : null;
    }
}
