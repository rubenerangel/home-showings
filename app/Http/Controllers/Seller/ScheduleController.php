<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\SchedulePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The windows and showings of one home. */
class ScheduleController extends Controller
{
    public function show(Request $request, string $listing, SchedulePresenter $presenter): Response
    {
        $listing = $request->user()->listings()->findOrFail($listing);

        return Inertia::render('seller/listings/Schedule', $presenter->schedule($listing));
    }
}
