<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\LocalIntervalInput;
use App\Http\Presenter;
use App\Scheduling\AddAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityController extends Controller
{
    public function index(Request $request, Presenter $presenter): Response
    {
        return Inertia::render('buyer/Availability', [
            'ranges' => $request->user()->currentAvailability()->map($presenter->range(...)),
        ]);
    }

    public function store(Request $request, AddAvailability $addAvailability): RedirectResponse
    {
        $addAvailability->handle($request->user(), LocalIntervalInput::from($request));
        Inertia::flash('message', 'Availability added.');

        return back();
    }

    public function destroy(Request $request, int $range): RedirectResponse
    {
        $request->user()->availabilityRanges()->findOrFail($range)->delete();
        Inertia::flash('message', 'Availability removed.');

        return back();
    }
}
