<?php

namespace App\Http;

use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Scheduling\ListingMatch;
use App\Scheduling\Slot;
use App\Scheduling\Slots;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;

/** The shape in which data leaves the application, the same for the pages and for the API. */
class Presenter
{
    private const LISTING = [
        'id', 'address', 'city', 'state', 'price', 'bedrooms', 'bathrooms', 'description',
        'timezone', 'showing_duration_minutes', 'min_notice_hours',
    ];

    /** @return array<string, mixed> */
    public function listing(Listing $listing): array
    {
        return $listing->only(self::LISTING);
    }

    /** @return array<string, mixed> */
    public function listingWithCover(Listing $listing): array
    {
        $cover = $listing->photos->first();

        return [...$this->listing($listing), 'cover' => $cover === null ? null : $this->photo($cover)];
    }

    /** @return array{id: int, url: string, room: string} */
    public function photo(ListingPhoto $photo): array
    {
        return ['id' => $photo->id, 'url' => $this->photoUrl($photo), 'room' => $photo->room->value];
    }

    /** @return array<string, mixed> */
    public function match(ListingMatch $match): array
    {
        return [
            ...$match->listing->only('id', 'address', 'city', 'state', 'price', 'bedrooms', 'bathrooms', 'description', 'timezone'),
            'photos' => $match->listing->photos->map(fn (ListingPhoto $photo) => ['url' => $this->photoUrl($photo), 'room' => $photo->room]),
            'slots' => array_map(fn (Slot $slot) => [
                'window_id' => $slot->window->id,
                'starts_at' => $slot->startsAt->toIso8601ZuluString(),
                'ends_at' => $slot->endsAt->toIso8601ZuluString(),
            ], $match->slots),
        ];
    }

    /** @return array<string, mixed> */
    public function range(AvailabilityRange $range): array
    {
        return [
            'id' => $range->id,
            'date' => $range->local_starts_at->format('Y-m-d'),
            'start' => $range->local_starts_at->format('H:i'),
            'end' => $range->local_ends_at->format('H:i'),
        ];
    }

    /** @return array<string, mixed> as the buyer sees it */
    public function showing(Showing $showing, CarbonImmutable $now): array
    {
        return [
            'id' => $showing->id,
            'listing' => $showing->listing->only('address', 'city', 'state', 'timezone'),
            'starts_at' => $showing->starts_at->toIso8601ZuluString(),
            'ends_at' => $showing->ends_at->toIso8601ZuluString(),
            'status' => $showing->status,
            'upcoming' => $showing->isActiveAt($now),
            'cancellable' => $showing->canBeCancelledAt($now),
        ];
    }

    /** @return array<string, mixed> as the seller sees it */
    public function showingWithBuyer(Showing $showing, CarbonImmutable $now): array
    {
        return [...$this->showing($showing, $now), 'buyer_name' => $showing->buyer->name];
    }

    /** @return array<string, mixed> for a window loaded with its active showings */
    public function window(ShowingWindow $window, CarbonImmutable $localNow): array
    {
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
    }

    /** @return array<string, mixed> */
    public function notification(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            ...$notification->data,
            'read' => $notification->read(),
            'created_at' => $notification->created_at->toIso8601ZuluString(),
        ];
    }

    protected function photoUrl(ListingPhoto $photo): string
    {
        return $photo->url();
    }
}
