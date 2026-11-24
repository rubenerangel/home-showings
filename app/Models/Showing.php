<?php

namespace App\Models;

use App\Enums\ShowingStatus;
use App\Scheduling\Slot;
use Carbon\CarbonImmutable;
use Database\Factories\ShowingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property ShowingStatus $status
 */
#[Fillable(['showing_window_id', 'listing_id', 'buyer_id', 'starts_at', 'ends_at', 'status', 'cancelled_at'])]
class Showing extends Model
{
    /** @use HasFactory<ShowingFactory> */
    use HasFactory;

    /** Written with the offset, so an instant in any zone is stored correctly. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => ShowingStatus::class,
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * Confirmed and not over.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query, CarbonImmutable $now): void
    {
        $query->where('status', ShowingStatus::Confirmed)->where('ends_at', '>', $now);
    }

    /**
     * Whether this showing takes the slot of its listing: any confirmed one that overlaps
     * it, or one the seller cancelled in the same window (R5, R8).
     */
    public function blocks(Slot $slot): bool
    {
        return $slot->overlaps($this) && match ($this->status) {
            ShowingStatus::Confirmed => true,
            ShowingStatus::CancelledBySeller => $this->showing_window_id === $slot->window->id,
            ShowingStatus::CancelledByBuyer => false,
        };
    }

    /** @return BelongsTo<ShowingWindow, $this> */
    public function window(): BelongsTo
    {
        return $this->belongsTo(ShowingWindow::class, 'showing_window_id');
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
