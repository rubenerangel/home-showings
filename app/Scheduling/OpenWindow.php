<?php

namespace App\Scheduling;

use App\Models\Listing;
use App\Models\ShowingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Opens a window with the duration the listing has now (D13), checking R1, R11, R12 and D15. */
class OpenWindow
{
    private const EARLIEST_HOUR_ON_CLOCK_CHANGE = 3;

    public function handle(Listing $listing, Interval $interval): ShowingWindow
    {
        return DB::transaction(fn () => $this->open($listing->id, $interval), attempts: 3);
    }

    private function open(int $listingId, Interval $interval): ShowingWindow
    {
        // Two windows of one listing are opened one after the other.
        $listing = Listing::query()->lockForUpdate()->findOrFail($listingId);
        $minutes = $listing->showing_duration_minutes;

        if (HouseClock::toInstant($interval->start, $listing->timezone) <= CarbonImmutable::now()) {
            throw new Refused(Refusal::WindowInPast);
        }
        if (HouseClock::changesOn($interval->start, $listing->timezone)
            && $interval->start->hour < self::EARLIEST_HOUR_ON_CLOCK_CHANGE) {
            throw new Refused(Refusal::ClockChange);
        }
        if ((new Slots($interval, $minutes))->all() === []) {
            throw new Refused(Refusal::WindowTooShort, "A showing takes $minutes minutes, so nothing fits in this window.");
        }

        $conflict = $listing->windows()
            ->whereNull('closed_at')
            ->where('local_starts_at', '<', $interval->end)
            ->where('local_ends_at', '>', $interval->start)
            ->orderBy('local_starts_at')
            ->first();
        if ($conflict !== null) {
            throw new Refused(Refusal::WindowOverlaps, sprintf(
                'This overlaps your window on %s to %s.',
                $conflict->local_starts_at->format('M j, g:i A'),
                $conflict->local_ends_at->format('g:i A'),
            ));
        }

        try {
            return $listing->windows()->create([
                'local_starts_at' => $interval->start,
                'local_ends_at' => $interval->end,
                'duration_minutes' => $minutes,
            ]);
        } catch (QueryException $exception) {
            throw Refused::fromConflict($exception, ['showing_windows_no_overlap' => Refusal::WindowOverlaps]) ?? $exception;
        }
    }
}
