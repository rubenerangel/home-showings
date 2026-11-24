<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\PhotoInput;
use App\Listings\ListingPhotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhotoController extends Controller
{
    public function store(Request $request, string $listing, ListingPhotos $listingPhotos): RedirectResponse
    {
        $listing = $request->user()->listings()->findOrFail($listing);
        $photos = PhotoInput::uploads($request);

        $listingPhotos->add($listing, $photos);
        Inertia::flash('message', count($photos) === 1 ? 'Photo added.' : 'Photos added.');

        return redirect()->route('seller.listings.edit', $listing->id);
    }

    public function update(Request $request, string $listing, string $photo): RedirectResponse
    {
        $photo = $request->user()->listings()->findOrFail($listing)->photos()->findOrFail($photo);
        $photo->update(PhotoInput::room($request));
        Inertia::flash('message', 'Room saved.');

        return redirect()->route('seller.listings.edit', $listing);
    }

    public function destroy(Request $request, string $listing, string $photo, ListingPhotos $listingPhotos): RedirectResponse
    {
        $listingPhotos->remove($request->user()->listings()->findOrFail($listing)->photos()->findOrFail($photo));
        Inertia::flash('message', 'Photo removed.');

        return redirect()->route('seller.listings.edit', $listing);
    }
}
