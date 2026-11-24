<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\ApiPresenter;
use App\Http\Controllers\Controller;
use App\Http\PhotoInput;
use App\Listings\ListingPhotos;
use App\Models\ListingPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PhotoController extends Controller
{
    public function __construct(private readonly ApiPresenter $presenter) {}

    public function store(Request $request, string $listing, ListingPhotos $listingPhotos): JsonResponse
    {
        $listing = $request->user()->listings()->findOrFail($listing);
        $listingPhotos->add($listing, PhotoInput::uploads($request));

        return response()->json(['data' => $listing->photos->map($this->presenter->photo(...))], 201);
    }

    public function order(Request $request, string $listing, ListingPhotos $listingPhotos): JsonResponse
    {
        $listing = $request->user()->listings()->findOrFail($listing);
        $listingPhotos->reorder($listing, PhotoInput::order($request));

        return response()->json(['data' => $listing->photos->map($this->presenter->photo(...))]);
    }

    public function update(Request $request, string $listing, string $photo, ListingPhotos $listingPhotos): JsonResponse
    {
        $photo = $this->find($request, $listing, $photo);
        $listingPhotos->changeRoom($photo, PhotoInput::room($request));

        return response()->json(['data' => $this->presenter->photo($photo)]);
    }

    public function destroy(Request $request, string $listing, string $photo, ListingPhotos $listingPhotos): Response
    {
        $listingPhotos->remove($this->find($request, $listing, $photo));

        return response()->noContent();
    }

    private function find(Request $request, string $listing, string $photo): ListingPhoto
    {
        return $request->user()->listings()->findOrFail($listing)->photos()->findOrFail($photo);
    }
}
