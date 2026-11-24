<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\HouseTimezone;
use App\Http\Controllers\Controller;
use App\Http\LocalIntervalInput;
use App\Models\AvailabilityRange;
use App\Scheduling\HouseClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityController extends Controller
{
    public function index(Request $request): Response
    {
        $ranges = $request->user()->availabilityRanges()->current()->orderBy('local_starts_at')->orderBy('id')->get();

        return Inertia::render('buyer/Availability', [
            'ranges' => $ranges->map(fn (AvailabilityRange $range) => [
                'id' => $range->id,
                'date' => $range->local_starts_at->format('Y-m-d'),
                'start' => $range->local_starts_at->format('H:i'),
                'end' => $range->local_ends_at->format('H:i'),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $interval = LocalIntervalInput::from($request);
        if ($interval->end <= HouseClock::localNow(HouseTimezone::Pacific)) {
            throw ValidationException::withMessages(['end' => 'This time is already over.']);
        }

        $request->user()->availabilityRanges()->create([
            'local_starts_at' => $interval->start,
            'local_ends_at' => $interval->end,
        ]);
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
