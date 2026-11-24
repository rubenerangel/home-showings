<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Enums\Role;
use App\Http\ApiPresenter;
use App\Http\BookingInput;
use App\Http\Controllers\Controller;
use App\Models\Showing;
use App\Scheduling\BookShowing;
use App\Scheduling\CancelShowing;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowingController extends Controller
{
    public function __construct(private readonly ApiPresenter $presenter) {}

    public function index(Request $request): JsonResponse
    {
        $now = CarbonImmutable::now();

        return response()->json([
            'data' => $request->user()->orderedShowings()->map(fn (Showing $showing) => $this->presenter->showing($showing, $now)),
        ]);
    }

    public function store(Request $request, BookShowing $bookShowing): JsonResponse
    {
        $booking = BookingInput::from($request);
        $showing = $bookShowing->handle($request->user(), $booking->windowId, $booking->startsAt);

        return response()->json(['data' => $this->presenter->showing($showing, CarbonImmutable::now())], 201);
    }

    public function cancel(Request $request, string $showing, CancelShowing $cancelShowing): JsonResponse
    {
        $showing = $request->user()->showings()->findOrFail($showing);
        $cancelShowing->handle($showing, Role::Buyer);

        return response()->json(['data' => $this->presenter->showing($showing->refresh(), CarbonImmutable::now())]);
    }
}
