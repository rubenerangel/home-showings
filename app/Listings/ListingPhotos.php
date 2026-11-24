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

/** R14 and R20. */
class ListingPhotos
{
    public const MAX = 5;

    public const DISK = 'public';

    public const TOO_MANY = 'A home can have up to 5 photos.';

    public const CHANGED = 'The photos changed. Check the order and try again.';

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

    public function changeRoom(ListingPhoto $photo, string $room): void
    {
        $photo->update(['room' => $room]);
    }

    /** @param list<int> $ids every photo of the listing, in the new order (R20) */
    public function reorder(Listing $listing, array $ids): void
    {
        DB::transaction(function () use ($listing, $ids) {
            $listing->newQuery()->lockForUpdate()->findOrFail($listing->id);
            // The rows too: a photo that is being removed has not left the list yet.
            $current = $listing->photos()->reorder('id')->lockForUpdate()->pluck('position', 'id');

            $same = $current->keys()->sort()->values()->all() === collect($ids)->sort()->values()->all();
            if (! $same) {
                throw ValidationException::withMessages(['photos' => self::CHANGED]);
            }

            // In two steps, because the position is unique in a listing at every moment.
            $offset = $current->max() + count($ids);
            foreach (array_values($ids) as $index => $id) {
                $listing->photos()->whereKey($id)->update(['position' => $offset + $index + 1]);
            }
            foreach (array_values($ids) as $index => $id) {
                $listing->photos()->whereKey($id)->update(['position' => $index + 1]);
            }
        });
    }

    public function remove(ListingPhoto $photo): void
    {
        $photo->delete();
        // After the row: a leftover file is harmless, a photo without its file is not.
        Storage::disk(self::DISK)->delete($photo->path);
    }
}
