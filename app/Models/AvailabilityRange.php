<?php

namespace App\Models;

use App\Enums\HouseTimezone;
use App\Scheduling\HouseClock;
use App\Scheduling\Interval;
use Carbon\CarbonImmutable;
use Database\Factories\AvailabilityRangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Local to whichever house it is compared with: the zone of these objects has no meaning.
 *
 * @property CarbonImmutable $local_starts_at
 * @property CarbonImmutable $local_ends_at
 */
#[Fillable(['local_starts_at', 'local_ends_at'])]
class AvailabilityRange extends Model
{
    /** @use HasFactory<AvailabilityRangeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['local_starts_at' => 'immutable_datetime', 'local_ends_at' => 'immutable_datetime'];
    }

    /**
     * Not over yet in Pacific time, the last of the supported zones (R12).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->where('local_ends_at', '>', HouseClock::localNow(HouseTimezone::Pacific));
    }

    public function interval(): Interval
    {
        return new Interval($this->local_starts_at, $this->local_ends_at);
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
