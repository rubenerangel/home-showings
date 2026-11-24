<?php

namespace App\Models;

use App\Enums\Role;
use App\Scheduling\Availability;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'role'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    /** There are no passwords, so there is nothing to remember. */
    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'seller_id');
    }

    /** @return HasMany<AvailabilityRange, $this> */
    public function availabilityRanges(): HasMany
    {
        return $this->hasMany(AvailabilityRange::class, 'buyer_id');
    }

    /** Past ranges are left out (R12). */
    public function availability(): Availability
    {
        return new Availability($this->availabilityRanges()->current()->get()->map->interval());
    }

    /** @return HasMany<Showing, $this> */
    public function showings(): HasMany
    {
        return $this->hasMany(Showing::class, 'buyer_id');
    }
}
