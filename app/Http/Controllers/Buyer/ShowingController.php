<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\Role;
use App\Enums\ShowingStatus;
use App\Http\Controllers\Controller;
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
    public function index(Request $request): Response
    {
        $now = CarbonImmutable::now();
        $showings = $request->user()->showings()->with('listing')->orderBy('starts_at')->orderBy('id')->get();

        return Inertia::render('buyer/Showings', [
            'showings' => $showings->map(fn (Showing $showing) => [
                'id' => $showing->id,
                'listing' => $showing->listing->only('address', 'city', 'state', 'timezone'),
                'starts_at' => $showing->starts_at->toIso8601ZuluString(),
                'ends_at' => $showing->ends_at->toIso8601ZuluString(),
                'status' => $showing->status,
                'upcoming' => $showing->status === ShowingStatus::Confirmed && $showing->ends_at > $now,
                'cancellable' => $showing->status === ShowingStatus::Confirmed && $showing->starts_at > $now,
            ]),
        ]);
    }

    public function store(Request $request, BookShowing $bookShowing): RedirectResponse
    {
        $data = $request->validate([
            'window_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i:s\Z'],
        ]);

        $bookShowing->handle($request->user(), (int) $data['window_id'], CarbonImmutable::parse($data['starts_at']));
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
