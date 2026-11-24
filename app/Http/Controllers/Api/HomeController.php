<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiPresenter;
use App\Http\BookingInput;
use App\Http\Controllers\Controller;
use App\Http\HomesPresenter;
use App\Listings\Catalog;
use App\Scheduling\BookShowing;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(private readonly HomesPresenter $homes, private readonly ApiPresenter $presenter, private readonly Catalog $catalog) {}

    public function index(Request $request): JsonResponse
    {
        ['homes' => $homes, 'state' => $state, 'states' => $states] = $this->homes->list($this->presenter, $request->query('state'), null);

        return response()->json(['data' => $homes, 'state' => $state, 'states' => $states]);
    }

    public function show(string $listing): JsonResponse
    {
        $listing = $this->catalog->find($listing);

        return response()->json(['data' => [
            ...$this->homes->home($this->presenter, $listing),
            'openings' => $this->homes->openings($listing),
        ]]);
    }

    public function showForBuyer(Request $request, string $listing): JsonResponse
    {
        $listing = $this->catalog->find($listing);

        return response()->json(['data' => [
            'home' => $this->homes->home($this->presenter, $listing),
            ...$this->homes->booking($this->presenter, $listing, $request->user()),
        ]]);
    }

    public function book(Request $request, string $listing, BookShowing $bookShowing): JsonResponse
    {
        $listing = $this->catalog->find($listing);
        $booking = BookingInput::from($request);
        $showing = $bookShowing->fromHome($request->user(), $listing, $booking->windowId, $booking->startsAt);

        return response()->json(['data' => $this->presenter->showing($showing, CarbonImmutable::now())], 201);
    }
}
