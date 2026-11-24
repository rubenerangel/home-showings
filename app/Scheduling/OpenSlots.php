<?php

namespace App\Scheduling;

use App\Enums\ShowingStatus;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** The slots each listing has open for a buyer (R5), in the order of R15. */
final class OpenSlots
{
    /** @return Collection<int, ListingMatch> */
    public function forBuyer(User $buyer): Collection
    {
        $now = CarbonImmutable::now();
        $availability = $buyer->availability();
        if ($availability->ranges === []) {
            return collect();
        }

        $ownShowings = $buyer->showings()->active($now)->get();

        return $this->candidates($availability, $ownShowings, $now)
            ->map(fn (Listing $listing) => [$listing, $this->openSlots($listing, $availability, $ownShowings, $now)])
            ->filter(fn (array $match) => $match[1] !== [])
            ->map(fn (array $match) => new ListingMatch(...$match))
            ->sort(fn (ListingMatch $a, ListingMatch $b) => [$a->slots[0]->startsAt, $a->listing->id] <=> [$b->slots[0]->startsAt, $b->listing->id])
            ->values();
    }

    /**
     * Listings with an open window that may match. The exact rules run in PHP.
     *
     * @param  Collection<int, Showing>  $ownShowings
     * @return Collection<int, Listing>
     */
    private function candidates(Availability $availability, Collection $ownShowings, CarbonImmutable $now): Collection
    {
        $from = $availability->ranges[0]->start;
        $until = collect($availability->ranges)->last()->end;
        $mayMatch = fn (Builder|HasMany $windows) => $windows
            ->whereNull('closed_at')
            ->where('local_starts_at', '<', $until)
            ->where('local_ends_at', '>', $from);

        return Listing::query()
            ->whereNotIn('id', $ownShowings->pluck('listing_id'))
            ->whereHas('windows', $mayMatch)
            ->with([
                'photos',
                'windows' => fn (HasMany $windows) => $mayMatch($windows)->orderBy('local_starts_at'),
                'showings' => fn (HasMany $showings) => $showings
                    ->whereIn('status', [ShowingStatus::Confirmed, ShowingStatus::CancelledBySeller])
                    ->where('ends_at', '>', $now),
            ])
            ->get();
    }

    /**
     * @param  Collection<int, Showing>  $ownShowings
     * @return list<Slot>
     */
    private function openSlots(Listing $listing, Availability $availability, Collection $ownShowings, CarbonImmutable $now): array
    {
        return $listing->windows
            ->flatMap(fn (ShowingWindow $window) => $window->slots())
            ->filter(fn (Slot $slot) => $availability->contains($slot->local)
                && $slot->hasNoticeAt($now)
                && ! $listing->showings->contains(fn (Showing $showing) => $showing->blocks($slot))
                && ! $ownShowings->contains(fn (Showing $showing) => $slot->overlaps($showing)))
            ->values()
            ->all();
    }
}
