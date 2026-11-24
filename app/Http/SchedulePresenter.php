<?php

namespace App\Http;

use App\Enums\ShowingStatus;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Scheduling\HouseClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** The open windows and the showings of one home. */
final class SchedulePresenter
{
    public function __construct(private readonly Presenter $presenter) {}

    /** @return array<string, mixed> */
    public function schedule(Listing $listing): array
    {
        $now = CarbonImmutable::now();
        $localNow = HouseClock::localNow($listing->timezone);
        $windows = $listing->windows()
            ->whereNull('closed_at')
            ->withCount(['showings as active_showings' => fn ($showings) => $showings->active($now)])
            ->orderBy('local_starts_at')
            ->get();

        return [
            'listing' => $listing->only('id', 'address', 'city', 'state', 'timezone', 'showing_duration_minutes'),
            'today' => $localNow->format('Y-m-d'),
            'windows' => $windows->map(fn (ShowingWindow $window) => $this->presenter->window($window, $localNow)),
            'showings' => $this->showings($listing, $now),
        ];
    }

    /** @return array<string, mixed> */
    public function window(ShowingWindow $window): array
    {
        $window->loadCount(['showings as active_showings' => fn ($showings) => $showings->active(CarbonImmutable::now())]);

        return $this->presenter->window($window, HouseClock::localNow($window->listing->timezone));
    }

    /** @return Collection<int, array<string, mixed>> upcoming by start, then the rest by latest start */
    private function showings(Listing $listing, CarbonImmutable $now): Collection
    {
        [$upcoming, $rest] = $listing->showings()->with(['buyer', 'listing'])->orderBy('starts_at')->orderBy('id')->get()
            ->partition(fn (Showing $showing) => $showing->status === ShowingStatus::Confirmed && $showing->ends_at > $now);

        return $upcoming->concat($rest->reverse())->values()
            ->map(fn (Showing $showing) => $this->presenter->showingWithBuyer($showing, $now));
    }
}
