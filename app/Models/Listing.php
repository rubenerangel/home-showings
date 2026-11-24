<?php

namespace App\Models;

use App\Enums\HouseTimezone;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'address', 'city', 'state', 'price', 'bedrooms', 'bathrooms', 'description',
    'timezone', 'showing_duration_minutes', 'min_notice_hours',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'float',
            'timezone' => HouseTimezone::class,
            'showing_duration_minutes' => 'integer',
            'min_notice_hours' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return HasMany<ListingPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('position');
    }

    /** @return HasMany<ShowingWindow, $this> */
    public function windows(): HasMany
    {
        return $this->hasMany(ShowingWindow::class)->chaperone();
    }

    /** @return HasMany<Showing, $this> */
    public function showings(): HasMany
    {
        return $this->hasMany(Showing::class);
    }
}
