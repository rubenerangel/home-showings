<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'role'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    /** @return HasMany<Showing, $this> */
    public function showings(): HasMany
    {
        return $this->hasMany(Showing::class, 'buyer_id');
    }
}
