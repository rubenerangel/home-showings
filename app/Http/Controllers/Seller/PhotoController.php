<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Room;
use App\Http\Controllers\Controller;
use App\Listings\ListingPhotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PhotoController extends Controller
{
    public function store(Request $request, string $listing): RedirectResponse
    {
        $listing = $request->user()->listings()->findOrFail($listing);
        $data = $request->validate([
            'photos' => ['bail', 'required', 'list', 'max:'.ListingPhotos::MAX],
            'photos.*.file' => ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photos.*.room' => ['bail', 'required', 'string', Rule::enum(Room::class)],
        ], [
            'photos.required' => 'Choose at least one photo.',
            'photos.max' => ListingPhotos::TOO_MANY,
            'photos.*.file.mimes' => 'Photos must be JPG, PNG or WebP.',
            'photos.*.file.max' => 'Each photo can be up to 5 MB.',
            // PHP drops files above its own limit, which is higher than ours.
            'photos.*.file.uploaded' => 'The photo was not received. Each photo can be up to 5 MB.',
            'photos.*.room.required' => 'Choose a room.',
        ]);

        ListingPhotos::add($listing, $data['photos']);
        Inertia::flash('message', count($data['photos']) === 1 ? 'Photo added.' : 'Photos added.');

        return redirect()->route('seller.listings.edit', $listing->id);
    }

    public function update(Request $request, string $listing, string $photo): RedirectResponse
    {
        $photo = $request->user()->listings()->findOrFail($listing)->photos()->findOrFail($photo);
        $photo->update($request->validate(['room' => ['bail', 'required', 'string', Rule::enum(Room::class)]]));
        Inertia::flash('message', 'Room saved.');

        return redirect()->route('seller.listings.edit', $listing);
    }

    public function destroy(Request $request, string $listing, string $photo): RedirectResponse
    {
        ListingPhotos::remove($request->user()->listings()->findOrFail($listing)->photos()->findOrFail($photo));
        Inertia::flash('message', 'Photo removed.');

        return redirect()->route('seller.listings.edit', $listing);
    }
}
