<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\Role;
use App\Http\BookingInput;
use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Models\Showing;
use App\Scheduling\BookShowing;
use App\Scheduling\CancelShowing;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowingController extends Controller
{
    public function index(Request $request, Presenter $presenter): Response
    {
        $now = CarbonImmutable::now();

        return Inertia::render('buyer/Showings', [
            'showings' => $request->user()->orderedShowings()->map(fn (Showing $showing) => $presenter->showing($showing, $now)),
        ]);
    }

    public function store(Request $request, BookShowing $bookShowing): RedirectResponse
    {
        $booking = BookingInput::from($request);
        $bookShowing->handle($request->user(), $booking->windowId, $booking->startsAt);
        Inertia::flash('message', 'Showing booked. The seller has been notified.');

        return back();
    }

    public function cancel(Request $request, int $showing, CancelShowing $cancelShowing): RedirectResponse
    {
        $cancelShowing->handle($request->user()->showings()->findOrFail($showing), Role::Buyer);
        Inertia::flash('message', 'Showing cancelled. The seller has been notified.');

        return back();
    }
}
