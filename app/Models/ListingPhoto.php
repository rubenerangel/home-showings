<?php

namespace App\Models;

use App\Enums\Room;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['path', 'room', 'position'])]
class ListingPhoto extends Model
{
    protected function casts(): array
    {
        return ['room' => Room::class, 'position' => 'integer'];
    }

    /** Relative, so it works on whatever host and port the application is served from. */
    public function url(): string
    {
        return '/storage/'.$this->path;
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
