<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\ListingPhoto;
use App\Scheduling\ListingMatch;
use App\Scheduling\OpenSlots;
use App\Scheduling\Slot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    public function index(Request $request, OpenSlots $openSlots): Response
    {
        $buyer = $request->user();

        return Inertia::render('buyer/Listings', [
            'hasAvailability' => $buyer->availabilityRanges()->current()->exists(),
            'listings' => $openSlots->forBuyer($buyer)->map($this->present(...)),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(ListingMatch $match): array
    {
        return [
            ...$match->listing->only('id', 'address', 'city', 'state', 'price', 'bedrooms', 'bathrooms', 'description', 'timezone'),
            'photos' => $match->listing->photos->map(fn (ListingPhoto $photo) => ['url' => $photo->url(), 'room' => $photo->room]),
            'slots' => array_map(fn (Slot $slot) => [
                'window_id' => $slot->window->id,
                'starts_at' => $slot->startsAt->toIso8601ZuluString(),
                'ends_at' => $slot->endsAt->toIso8601ZuluString(),
            ], $match->slots),
        ];
    }
}
