<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\SchedulePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function show(Request $request, string $listing, SchedulePresenter $presenter): JsonResponse
    {
        return response()->json(['data' => $presenter->schedule($request->user()->listings()->findOrFail($listing))]);
    }
}
