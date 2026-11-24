<?php

namespace App\Http\Controllers\Seller;

use App\Enums\HouseTimezone;
use App\Http\Controllers\Controller;
use App\Listings\ListingLocks;
use App\Listings\ListingOptions;
use App\Listings\ListingPhotos;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    private const FIELDS = [
        'id', 'address', 'city', 'state', 'price', 'bedrooms', 'bathrooms', 'description',
        'timezone', 'showing_duration_minutes', 'min_notice_hours',
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('seller/Home', [
            'listings' => $request->user()->listings()->with('photos')->orderBy('id')->get()->map(fn (Listing $listing) => [
                ...$listing->only(self::FIELDS),
                'cover' => ($cover = $listing->photos->first()) ? ListingPhotos::present($cover) : null,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('seller/listings/Create', ['options' => ListingOptions::toArray()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $listing = $request->user()->listings()->create($request->validate($this->rules()));
        Inertia::flash('message', 'Home created.');

        return redirect()->route('seller.listings.edit', $listing->id);
    }

    public function edit(Request $request, string $listing): Response
    {
        $listing = $request->user()->listings()->findOrFail($listing);

        return Inertia::render('seller/listings/Edit', [
            'listing' => $listing->only(self::FIELDS),
            'photos' => $listing->photos->map(ListingPhotos::present(...)),
            'locks' => ListingLocks::of($listing),
            'options' => ListingOptions::toArray(),
        ]);
    }

    public function update(Request $request, string $listing): RedirectResponse
    {
        $request->user()->listings()->findOrFail($listing);
        $data = $request->validate($this->rules());

        DB::transaction(function () use ($request, $listing, $data) {
            // Inserting a window or a showing takes KEY SHARE on this row through its foreign key,
            // so both wait for this transaction, or this one waits and then sees them.
            $listing = $request->user()->listings()->lockForUpdate()->findOrFail($listing);

            if ($refusals = ListingLocks::of($listing)->refusals($listing, $data)) {
                throw ValidationException::withMessages($refusals);
            }
            $listing->update($data);
        });
        Inertia::flash('message', 'Changes saved.');

        return redirect()->route('seller.listings.edit', $listing);
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'address' => ['bail', 'required', 'string', 'max:255'],
            'city' => ['bail', 'required', 'string', 'max:255'],
            'state' => ['bail', 'required', 'string', Rule::in(ListingOptions::STATES)],
            'price' => ['bail', 'required', 'integer', 'min:1', 'max:2147483647'],
            'bedrooms' => ['bail', 'required', 'integer', 'min:0', 'max:99'],
            'bathrooms' => ['bail', 'required', 'decimal:0,1', 'min:0.5', 'max:99.5', 'multiple_of:0.5'],
            'description' => ['bail', 'present', 'nullable', 'string', 'max:1000'],
            'timezone' => ['bail', 'required', 'string', Rule::enum(HouseTimezone::class)],
            'showing_duration_minutes' => ['bail', 'required', 'integer', Rule::in(ListingOptions::DURATIONS)],
            'min_notice_hours' => ['bail', 'required', 'integer', Rule::in(ListingOptions::NOTICES)],
        ];
    }
}
