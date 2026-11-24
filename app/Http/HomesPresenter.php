<?php

namespace App\Http;

use App\Listings\Catalog;
use App\Listings\UsStates;
use App\Models\Listing;
use App\Models\User;
use App\Scheduling\ListingMatch;
use App\Scheduling\OpenSlots;
use App\Scheduling\Slot;
use Carbon\CarbonImmutable;

/** The public homes: days, and not times, unless a buyer asks. */
class HomesPresenter
{
    public function __construct(private readonly Catalog $catalog, private readonly OpenSlots $openSlots) {}

    /**
     * @param  mixed  $state  as it came, so anything that is not a state is ignored (R22)
     * @return array{state: ?string, states: list<array<string, mixed>>, homes: list<array<string, mixed>>}
     */
    public function list(Presenter $presenter, mixed $state, ?User $buyer): array
    {
        $state = is_string($state) && array_key_exists($state, UsStates::NAMES) ? $state : null;
        $all = $this->catalog->available();
        $fitting = $buyer === null ? null : $this->openSlots->forBuyer($buyer)->map(fn (ListingMatch $match) => $match->listing->id);

        return [
            'state' => $state,
            'states' => $all->countBy(fn (ListingMatch $match) => $match->listing->state)
                ->map(fn (int $count, string $code) => ['code' => $code, 'name' => UsStates::NAMES[$code], 'count' => $count])
                ->values()->all(),
            'homes' => $all
                ->filter(fn (ListingMatch $match) => $state === null || $match->listing->state === $state)
                ->map(fn (ListingMatch $match) => [
                    ...$presenter->home($match->listing),
                    'cover' => ($cover = $match->listing->photos->first()) === null ? null : $presenter->publicPhoto($cover),
                    'openings' => count($match->slots),
                    'next_opening' => $match->slots[0]->local->start->format('Y-m-d'),
                    'fits' => $fitting?->contains($match->listing->id),
                ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function home(Presenter $presenter, Listing $listing): array
    {
        return [...$presenter->home($listing), 'photos' => $listing->photos->map($presenter->publicPhoto(...))];
    }

    /** @return list<array{date: string, count: int}> on the calendar of the house */
    public function openings(Listing $listing): array
    {
        return collect($this->openSlots->of($listing))
            ->countBy(fn (Slot $slot) => $slot->local->start->format('Y-m-d'))
            ->map(fn (int $count, string $date) => ['date' => $date, 'count' => $count])
            ->sortBy('date')->values()->all();
    }

    /** @return array{slots: list<array<string, mixed>>, showing: ?array<string, mixed>} */
    public function booking(Presenter $presenter, Listing $listing, User $buyer): array
    {
        $now = CarbonImmutable::now();
        $availability = $buyer->availability();
        $showing = $buyer->activeShowingAt($listing, $now);

        return [
            'slots' => array_map(
                fn (Slot $slot) => [...$presenter->slot($slot), 'fits' => $availability->contains($slot->local)],
                $this->openSlots->ofForBuyer($listing, $buyer),
            ),
            'showing' => $showing === null ? null : $presenter->showing($showing, $now),
        ];
    }
}
