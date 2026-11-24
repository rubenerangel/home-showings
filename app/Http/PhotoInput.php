<?php

namespace App\Http;

use App\Enums\Room;
use App\Listings\ListingPhotos;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/** R14. */
final class PhotoInput
{
    /** @return list<array{file: UploadedFile, room: string}> */
    public static function uploads(Request $request): array
    {
        return $request->validate([
            'photos' => ['bail', 'required', 'list', 'max:'.ListingPhotos::MAX],
            'photos.*.file' => ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photos.*.room' => self::roomRules(),
        ], [
            'photos.required' => 'Choose at least one photo.',
            'photos.max' => ListingPhotos::TOO_MANY,
            'photos.*.file.mimes' => 'Photos must be JPG, PNG or WebP.',
            'photos.*.file.max' => 'Each photo can be up to 5 MB.',
            // PHP drops files above its own limit, which is higher than ours.
            'photos.*.file.uploaded' => 'The photo was not received. Each photo can be up to 5 MB.',
            'photos.*.room.required' => 'Choose a room.',
        ])['photos'];
    }

    /** @return array{room: string} */
    public static function room(Request $request): array
    {
        return $request->validate(['room' => self::roomRules()]);
    }

    /** @return list<mixed> */
    private static function roomRules(): array
    {
        return ['bail', 'required', 'string', Rule::enum(Room::class)];
    }
}
