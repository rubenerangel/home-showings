<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ShowingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Scheduling\HouseClock;
use App\Scheduling\Slots;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/** The windows and showings of one home. */
class ScheduleController extends Controller
{
    public function show(Request $request, string $listing): Response
    {
        $listing = $request->user()->listings()->findOrFail($listing);
        $now = CarbonImmutable::now();

        return Inertia::render('seller/listings/Schedule', [
            'listing' => $listing->only('id', 'address', 'city', 'state', 'timezone', 'showing_duration_minutes'),
            'today' => HouseClock::localNow($listing->timezone)->format('Y-m-d'),
            'windows' => $this->windows($listing, $now),
            'showings' => $this->showings($listing, $now),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function windows(Listing $listing, CarbonImmutable $now): Collection
    {
        $localNow = HouseClock::localNow($listing->timezone);

        return $listing->windows()
            ->whereNull('closed_at')
            ->withCount(['showings as active_showings' => fn ($showings) => $showings->active($now)])
            ->orderBy('local_starts_at')
            ->get()
            ->map(function (ShowingWindow $window) use ($localNow) {
                $slots = new Slots($window->interval(), $window->duration_minutes);

                return [
                    'id' => $window->id,
                    'date' => $window->local_starts_at->format('Y-m-d'),
                    'start' => $window->local_starts_at->format('H:i'),
                    'end' => $window->local_ends_at->format('H:i'),
                    'showings' => count($slots->all()),
                    'unused_minutes' => $slots->unusedMinutes(),
                    'active_showings' => $window->active_showings,
                    'past' => $window->local_ends_at <= $localNow,
                ];
            });
    }

    /** @return Collection<int, array<string, mixed>> upcoming by start, then the rest by latest start */
    private function showings(Listing $listing, CarbonImmutable $now): Collection
    {
        $confirmed = fn (Showing $showing) => $showing->status === ShowingStatus::Confirmed;
        [$upcoming, $rest] = $listing->showings()->with('buyer')->orderBy('starts_at')->orderBy('id')->get()
            ->partition(fn (Showing $showing) => $confirmed($showing) && $showing->ends_at > $now);

        return $upcoming->concat($rest->reverse())->values()->map(fn (Showing $showing) => [
            'id' => $showing->id,
            'buyer_name' => $showing->buyer->name,
            'listing' => $listing->only('address', 'city', 'state', 'timezone'),
            'starts_at' => $showing->starts_at->toIso8601ZuluString(),
            'ends_at' => $showing->ends_at->toIso8601ZuluString(),
            'status' => $showing->status,
            'upcoming' => $confirmed($showing) && $showing->ends_at > $now,
            'cancellable' => $confirmed($showing) && $showing->starts_at > $now,
        ]);
    }
}
