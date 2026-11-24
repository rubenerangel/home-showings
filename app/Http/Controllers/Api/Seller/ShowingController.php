<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Role;
use App\Http\ApiPresenter;
use App\Http\Controllers\Controller;
use App\Models\Showing;
use App\Scheduling\CancelShowing;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowingController extends Controller
{
    public function cancel(Request $request, string $showing, CancelShowing $cancelShowing, ApiPresenter $presenter): JsonResponse
    {
        $showing = Showing::query()->whereRelation('listing', 'seller_id', $request->user()->id)->findOrFail($showing);
        $cancelShowing->handle($showing, Role::Seller);

        return response()->json(['data' => $presenter->showingWithBuyer($showing->refresh(), CarbonImmutable::now())]);
    }
}
