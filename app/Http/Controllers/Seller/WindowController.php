<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\LocalIntervalInput;
use App\Models\ShowingWindow;
use App\Scheduling\CloseWindow;
use App\Scheduling\OpenWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WindowController extends Controller
{
    public function store(Request $request, int $listing, OpenWindow $openWindow): RedirectResponse
    {
        // Found before validating: someone else's listing is a 404 whatever the input (R10).
        $listing = $request->user()->listings()->findOrFail($listing);
        $openWindow->handle($listing, LocalIntervalInput::from($request));
        Inertia::flash('message', 'Window added.');

        return back();
    }

    public function close(Request $request, int $window, CloseWindow $closeWindow): RedirectResponse
    {
        $closeWindow->handle(ShowingWindow::query()
            ->whereRelation('listing', 'seller_id', $request->user()->id)
            ->findOrFail($window));
        Inertia::flash('message', 'Window closed.');

        return back();
    }
}
