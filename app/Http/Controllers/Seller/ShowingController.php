<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Showing;
use App\Scheduling\CancelShowing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShowingController extends Controller
{
    public function cancel(Request $request, int $showing, CancelShowing $cancelShowing): RedirectResponse
    {
        $cancelShowing->handle(Showing::ofSeller($request->user())->findOrFail($showing), Role::Seller);
        Inertia::flash('message', 'Showing cancelled. The buyer has been notified.');

        return back();
    }
}
