<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\ListingInput;
use App\Http\Presenter;
use App\Listings\CreateListing;
use App\Listings\ListingOptions;
use App\Listings\ReadLocks;
use App\Listings\UpdateListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    public function __construct(private readonly Presenter $presenter, private readonly ReadLocks $readLocks) {}

    public function index(Request $request): Response
    {
        return Inertia::render('seller/Home', [
            'listings' => $request->user()->listingsWithPhotos()->map($this->presenter->listingWithCover(...)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('seller/listings/Create', ['options' => ListingOptions::toArray()]);
    }

    public function store(Request $request, CreateListing $createListing): RedirectResponse
    {
        $listing = $createListing->handle($request->user(), ListingInput::from($request));
        Inertia::flash('message', 'Home created.');

        return redirect()->route('seller.listings.edit', $listing->id);
    }

    public function edit(Request $request, string $listing): Response
    {
        $listing = $request->user()->listings()->findOrFail($listing);

        return Inertia::render('seller/listings/Edit', [
            'listing' => $this->presenter->listing($listing),
            'photos' => $listing->photos->map($this->presenter->photo(...)),
            'locks' => $this->readLocks->of($listing),
            'options' => ListingOptions::toArray(),
        ]);
    }

    public function update(Request $request, string $listing, UpdateListing $updateListing): RedirectResponse
    {
        $request->user()->listings()->findOrFail($listing);
        $updateListing->handle($request->user(), $listing, ListingInput::from($request));
        Inertia::flash('message', 'Changes saved.');

        return redirect()->route('seller.listings.edit', $listing);
    }
}
