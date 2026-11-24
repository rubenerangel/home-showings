<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\ApiPresenter;
use App\Http\Controllers\Controller;
use App\Http\LocalIntervalInput;
use App\Scheduling\AddAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AvailabilityController extends Controller
{
    public function __construct(private readonly ApiPresenter $presenter) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->currentAvailability()->map($this->presenter->range(...))]);
    }

    public function store(Request $request, AddAvailability $addAvailability): JsonResponse
    {
        $range = $addAvailability->handle($request->user(), LocalIntervalInput::from($request));

        return response()->json(['data' => $this->presenter->range($range)], 201);
    }

    public function destroy(Request $request, string $range): Response
    {
        $request->user()->availabilityRanges()->findOrFail($range)->delete();

        return response()->noContent();
    }
}
