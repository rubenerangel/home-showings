<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\LocalIntervalInput;
use App\Http\SchedulePresenter;
use App\Models\ShowingWindow;
use App\Scheduling\CloseWindow;
use App\Scheduling\OpenWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WindowController extends Controller
{
    public function __construct(private readonly SchedulePresenter $presenter) {}

    public function store(Request $request, string $listing, OpenWindow $openWindow): JsonResponse
    {
        // Found before validating: someone else's listing is a 404 whatever the input (R10).
        $listing = $request->user()->listings()->findOrFail($listing);
        $window = $openWindow->handle($listing, LocalIntervalInput::from($request));

        return response()->json(['data' => $this->presenter->window($window)], 201);
    }

    public function close(Request $request, string $window, CloseWindow $closeWindow): JsonResponse
    {
        $window = ShowingWindow::query()->whereRelation('listing', 'seller_id', $request->user()->id)->findOrFail($window);
        $closeWindow->handle($window);

        return response()->json(['data' => $this->presenter->window($window->refresh())]);
    }
}
