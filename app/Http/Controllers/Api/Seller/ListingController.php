<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\ApiPresenter;
use App\Http\Controllers\Controller;
use App\Http\ListingInput;
use App\Listings\ListingLocks;
use App\Listings\ListingOptions;
use App\Listings\UpdateListing;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function __construct(private readonly ApiPresenter $presenter) {}

    public function options(): JsonResponse
    {
        return response()->json(['data' => ListingOptions::toArray()]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->listings()->with('photos')->orderBy('id')->get()->map($this->presenter->listingWithCover(...)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->answer($request->user()->listings()->create(ListingInput::from($request)), 201);
    }

    public function show(Request $request, string $listing): JsonResponse
    {
        return $this->answer($request->user()->listings()->findOrFail($listing));
    }

    public function update(Request $request, string $listing, UpdateListing $updateListing): JsonResponse
    {
        // Found before validating: someone else's listing is a 404 whatever the input (R10).
        $request->user()->listings()->findOrFail($listing);

        return $this->answer($updateListing->handle($request->user(), $listing, ListingInput::from($request)));
    }

    private function answer(Listing $listing, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [
            ...$this->presenter->listing($listing),
            'photos' => $listing->photos->map($this->presenter->photo(...)),
            'locks' => ListingLocks::of($listing),
        ]], $status);
    }
}
