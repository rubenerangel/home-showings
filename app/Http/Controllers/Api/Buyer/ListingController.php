<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\ApiPresenter;
use App\Http\Controllers\Controller;
use App\Scheduling\OpenSlots;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request, OpenSlots $openSlots, ApiPresenter $presenter): JsonResponse
    {
        $buyer = $request->user();

        return response()->json([
            'data' => $openSlots->forBuyer($buyer)->map($presenter->match(...)),
            'has_availability' => $buyer->availabilityRanges()->current()->exists(),
        ]);
    }
}
