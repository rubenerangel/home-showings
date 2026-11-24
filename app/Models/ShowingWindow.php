<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ShowingWindowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The local times are the wall clock of the house. They carry no zone in the
 * database, so the zone of these objects (UTC) has no meaning.
 *
 * @property CarbonImmutable $local_starts_at
 * @property CarbonImmutable $local_ends_at
 * @property CarbonImmutable|null $closed_at
 */
#[Fillable(['local_starts_at', 'local_ends_at', 'duration_minutes', 'closed_at'])]
class ShowingWindow extends Model
{
    /** @use HasFactory<ShowingWindowFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'local_starts_at' => 'immutable_datetime',
            'local_ends_at' => 'immutable_datetime',
            'duration_minutes' => 'integer',
            'closed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return HasMany<Showing, $this> */
    public function showings(): HasMany
    {
        return $this->hasMany(Showing::class);
    }
}
