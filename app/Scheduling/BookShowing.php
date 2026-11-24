<?php

namespace App\Scheduling;

use App\Enums\ShowingEvent;
use App\Enums\ShowingStatus;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use App\Notifications\ShowingChanged;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Books a slot (R6). The buyer row serializes everything one buyer does, and the window row
 * serializes booking and closing. Always in that order, so two bookings cannot wait for each
 * other. The exclusion constraints are the second line of defense (D21).
 */
final class BookShowing
{
    public function handle(User $buyer, int $windowId, CarbonImmutable $startsAt): Showing
    {
        return DB::transaction(fn () => $this->book($buyer->id, $windowId, $startsAt), attempts: 3);
    }

    private function book(int $buyerId, int $windowId, CarbonImmutable $startsAt): Showing
    {
        $buyer = User::query()->lockForUpdate()->findOrFail($buyerId);
        $window = ShowingWindow::query()->with('listing')->lockForUpdate()->findOrFail($windowId);
        // Read after the locks: waiting for them takes time.
        $now = CarbonImmutable::now();

        $slot = array_find($window->slots(), fn (Slot $slot) => $slot->startsAt->equalTo($startsAt));
        $reason = $slot === null ? Refusal::NotAvailable : $this->refusal($buyer, $slot, $now);
        if ($reason !== null) {
            throw new Refused($reason);
        }

        $showing = Refused::onConflict(fn () => Showing::create([
            'showing_window_id' => $window->id,
            'listing_id' => $window->listing_id,
            'buyer_id' => $buyer->id,
            'starts_at' => $slot->startsAt,
            'ends_at' => $slot->endsAt,
            'status' => ShowingStatus::Confirmed,
        ]), [
            'showings_listing_no_overlap' => Refusal::JustBooked,
            'showings_buyer_no_overlap' => Refusal::Overlaps,
        ]);
        $window->listing->seller->notify(new ShowingChanged($showing, ShowingEvent::Booked));

        return $showing;
    }

    private function refusal(User $buyer, Slot $slot, CarbonImmutable $now): ?Refusal
    {
        $window = $slot->window;
        $blocking = $window->listing->showings()->get()->filter(fn (Showing $showing) => $showing->blocks($slot));
        $own = $buyer->showings()->active($now)->get();

        return match (true) {
            $window->closed_at !== null,
            $blocking->contains('status', ShowingStatus::CancelledBySeller) => Refusal::NotAvailable,
            ! $slot->hasNoticeAt($now) => Refusal::Notice,
            ! $buyer->availability()->contains($slot->local) => Refusal::OutsideAvailability,
            $own->contains('listing_id', $window->listing_id) => Refusal::AlreadyHasShowing,
            $blocking->isNotEmpty() => Refusal::JustBooked,
            $own->contains(fn (Showing $showing) => $slot->overlaps($showing)) => Refusal::Overlaps,
            default => null,
        };
    }
}
