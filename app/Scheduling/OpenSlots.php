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
class OpenSlots
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

    /** @return Collection<int, ListingMatch> every listing with a slot that some buyer could book now (R21) */
    public function available(): Collection
    {
        $now = CarbonImmutable::now();

        return $this->withSlots(fn (Builder|HasMany $windows) => $windows->whereNull('closed_at'), $now)->get()
            ->map(fn (Listing $listing) => new ListingMatch($listing, $this->bookable($listing, $now)))
            ->filter(fn (ListingMatch $match) => $match->slots !== [])
            ->values();
    }

    /** @return list<Slot> */
    public function of(Listing $listing): array
    {
        $now = CarbonImmutable::now();

        return $this->bookable($this->withSlots(fn (Builder|HasMany $windows) => $windows->whereNull('closed_at'), $now)->find($listing->id), $now);
    }

    /**
     * Those of one home for a buyer, whatever the availability (R24).
     *
     * @return list<Slot>
     */
    public function ofForBuyer(Listing $listing, User $buyer): array
    {
        $own = $buyer->showings()->active(CarbonImmutable::now())->get();
        if ($own->contains('listing_id', $listing->id)) {
            return [];
        }

        return array_values(array_filter(
            $this->of($listing),
            fn (Slot $slot) => ! $this->isTakenBy($own, $slot),
        ));
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

        return $this->withSlots(fn (Builder|HasMany $windows) => $windows
            ->whereNull('closed_at')
            ->where('local_starts_at', '<', $until)
            ->where('local_ends_at', '>', $from), $now)
            ->whereNotIn('id', $ownShowings->pluck('listing_id'))
            ->get();
    }

    /**
     * @param  callable(Builder<ShowingWindow>|HasMany<ShowingWindow, Listing>): mixed  $windows
     * @return Builder<Listing> with what decides whether a slot is taken
     */
    private function withSlots(callable $windows, CarbonImmutable $now): Builder
    {
        return Listing::query()
            ->whereHas('windows', $windows)
            ->with([
                'photos',
                'windows' => fn (HasMany $relation) => $windows($relation)->orderBy('local_starts_at'),
                'showings' => fn (HasMany $showings) => $showings
                    ->whereIn('status', [ShowingStatus::Confirmed, ShowingStatus::CancelledBySeller])
                    ->where('ends_at', '>', $now),
            ]);
    }

    /**
     * @param  Collection<int, Showing>  $ownShowings
     * @return list<Slot>
     */
    private function openSlots(Listing $listing, Availability $availability, Collection $ownShowings, CarbonImmutable $now): array
    {
        return array_values(array_filter(
            $this->bookable($listing, $now),
            fn (Slot $slot) => $availability->contains($slot->local)
                && ! $this->isTakenBy($ownShowings, $slot),
        ));
    }

    /** @param Collection<int, Showing> $ownShowings of a buyer, who cannot be in two homes at once (D20) */
    private function isTakenBy(Collection $ownShowings, Slot $slot): bool
    {
        return $ownShowings->contains(fn (Showing $showing) => $slot->overlaps($showing));
    }

    /** @return list<Slot> with notice and not taken, for a listing loaded with its windows and showings */
    private function bookable(?Listing $listing, CarbonImmutable $now): array
    {
        return $listing === null ? [] : $listing->windows
            ->flatMap(fn (ShowingWindow $window) => $window->slots())
            ->filter(fn (Slot $slot) => $slot->hasNoticeAt($now)
                && ! $listing->showings->contains(fn (Showing $showing) => $showing->blocks($slot)))
            ->values()
            ->all();
    }
}
