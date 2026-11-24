<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\BookingInput;
use App\Http\HomesPresenter;
use App\Http\Presenter;
use App\Listings\Catalog;
use App\Scheduling\BookShowing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The main pages, open to anyone. */
class HomeController extends Controller
{
    public function __construct(private readonly HomesPresenter $homes, private readonly Presenter $presenter, private readonly Catalog $catalog) {}

    public function index(Request $request): Response
    {
        $buyer = $request->user()?->role === Role::Buyer ? $request->user() : null;

        return Inertia::render('Homes', $this->homes->list($this->presenter, $request->query('state'), $buyer));
    }

    public function show(Request $request, string $listing): Response
    {
        $listing = $this->catalog->find($listing);

        return Inertia::render('Home', [
            'home' => $this->homes->home($this->presenter, $listing),
            'openings' => $this->homes->openings($listing),
            'booking' => $request->user()?->role === Role::Buyer ? $this->homes->booking($this->presenter, $listing, $request->user()) : null,
        ]);
    }

    public function book(Request $request, string $listing, BookShowing $bookShowing): RedirectResponse
    {
        $listing = $this->catalog->find($listing);
        $booking = BookingInput::from($request);
        $bookShowing->fromHome($request->user(), $listing, $booking->windowId, $booking->startsAt);
        Inertia::flash('message', 'Showing booked. The seller has been notified.');

        return redirect()->route('homes.show', $listing->id);
    }
}
