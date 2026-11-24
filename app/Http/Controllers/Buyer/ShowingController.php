<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Scheduling\BookShowing;
use App\Scheduling\CancelShowing;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShowingController extends Controller
{
    public function store(Request $request, BookShowing $bookShowing): RedirectResponse
    {
        $data = $request->validate([
            'window_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i:s\Z'],
        ]);

        $bookShowing->handle($request->user(), (int) $data['window_id'], CarbonImmutable::parse($data['starts_at']));

        return back();
    }

    public function cancel(Request $request, int $showing, CancelShowing $cancelShowing): RedirectResponse
    {
        $cancelShowing->handle($request->user()->showings()->findOrFail($showing), Role::Buyer);

        return back();
    }
}
