<?php

namespace App\Scheduling;

use App\Enums\ShowingEvent;
use App\Enums\ShowingStatus;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Books a slot (R6). The buyer row serializes everything one buyer does, and the window row
 * serializes booking and closing. Always in that order, so two bookings cannot wait for each
 * other. The exclusion constraints are the second line of defense (D21).
 */
class BookShowing
{
    public function __construct(private readonly TellOtherSide $tellOtherSide) {}

    /** From the search, which is by availability. */
    public function handle(User $buyer, int $windowId, CarbonImmutable $startsAt): Showing
    {
        return DB::transaction(fn () => $this->book($buyer->id, $windowId, $startsAt, anyTime: false), attempts: 3);
    }

    /** From the page of a home, where picking a time already says that the buyer is free then (D7). */
    public function fromHome(User $buyer, Listing $listing, int $windowId, CarbonImmutable $startsAt): Showing
    {
        $listing->windows()->findOrFail($windowId);

        return DB::transaction(fn () => $this->book($buyer->id, $windowId, $startsAt, anyTime: true), attempts: 3);
    }

    private function book(int $buyerId, int $windowId, CarbonImmutable $startsAt, bool $anyTime): Showing
    {
        $buyer = User::query()->lockForUpdate()->findOrFail($buyerId);
        $window = ShowingWindow::query()->with('listing')->lockForUpdate()->findOrFail($windowId);
        // Read after the locks: waiting for them takes time.
        $now = CarbonImmutable::now();

        $slot = array_find($window->slots(), fn (Slot $slot) => $slot->startsAt->equalTo($startsAt));
        $reason = $slot === null ? Refusal::NotAvailable : $this->refusal($buyer, $slot, $now, $anyTime);
        if ($reason !== null) {
            throw new Refused($reason);
        }

        try {
            $showing = Showing::create([
                'showing_window_id' => $window->id,
                'listing_id' => $window->listing_id,
                'buyer_id' => $buyer->id,
                'starts_at' => $slot->startsAt,
                'ends_at' => $slot->endsAt,
                'status' => ShowingStatus::Confirmed,
            ]);
        } catch (QueryException $exception) {
            throw Refused::fromConflict($exception, [
                'showings_listing_no_overlap' => Refusal::JustBooked,
                'showings_buyer_no_overlap' => Refusal::Overlaps,
            ]) ?? $exception;
        }
        $this->tellOtherSide->that(ShowingEvent::Booked, $showing);

        return $showing;
    }

    private function refusal(User $buyer, Slot $slot, CarbonImmutable $now, bool $anyTime): ?Refusal
    {
        $window = $slot->window;
        $blocking = $window->listing->showings()->get()->filter(fn (Showing $showing) => $showing->blocks($slot));
        $own = $buyer->showings()->active($now)->get();

        return match (true) {
            $window->closed_at !== null,
            $blocking->contains('status', ShowingStatus::CancelledBySeller) => Refusal::NotAvailable,
            ! $slot->hasNoticeAt($now) => Refusal::Notice,
            ! $anyTime && ! $buyer->availability()->contains($slot->local) => Refusal::OutsideAvailability,
            $own->contains('listing_id', $window->listing_id) => Refusal::AlreadyHasShowing,
            $blocking->isNotEmpty() => Refusal::JustBooked,
            $own->contains(fn (Showing $showing) => $slot->overlaps($showing)) => Refusal::Overlaps,
            default => null,
        };
    }
}
