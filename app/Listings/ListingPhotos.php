<?php

namespace App\Listings;

use App\Models\Listing;
use App\Models\ListingPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/** R14. */
final class ListingPhotos
{
    public const MAX = 5;

    public const DISK = 'public';

    public const TOO_MANY = 'A home can have up to 5 photos.';

    /** @param list<array{file: UploadedFile, room: string}> $photos */
    public function add(Listing $listing, array $photos): void
    {
        $disk = Storage::disk(self::DISK);
        $stored = [];

        try {
            DB::transaction(function () use ($listing, $photos, $disk, &$stored) {
                // Uploads for the same listing take turns, so the count cannot change under this one.
                $listing->newQuery()->lockForUpdate()->findOrFail($listing->id);

                if ($listing->photos()->count() + count($photos) > self::MAX) {
                    throw ValidationException::withMessages(['photos' => self::TOO_MANY]);
                }

                $position = (int) $listing->photos()->max('position');
                foreach ($photos as $photo) {
                    $path = $disk->putFile("listings/{$listing->id}", $photo['file']);
                    throw_if($path === false, new RuntimeException('The photo could not be stored.'));
                    $stored[] = $path;
                    $listing->photos()->create(['path' => $path, 'room' => $photo['room'], 'position' => ++$position]);
                }
            });
        } catch (Throwable $exception) {
            $disk->delete($stored);

            throw $exception;
        }
    }

    public function remove(ListingPhoto $photo): void
    {
        $photo->delete();
        // After the row: a leftover file is harmless, a photo without its file is not.
        Storage::disk(self::DISK)->delete($photo->path);
    }
}
