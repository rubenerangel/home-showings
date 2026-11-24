<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Scheduling\OpenSlots;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    public function index(Request $request, OpenSlots $openSlots, Presenter $presenter): Response
    {
        $buyer = $request->user();

        return Inertia::render('buyer/Listings', [
            'hasAvailability' => $buyer->availabilityRanges()->current()->exists(),
            'listings' => $openSlots->forBuyer($buyer)->map($presenter->match(...)),
        ]);
    }
}
