<?php

namespace App\Models;

use App\Enums\Role;
use App\Scheduling\Availability;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable;

    /** There is no "remember me". */
    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return ['password' => 'hashed', 'role' => Role::class];
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

    /** @return Collection<int, AvailabilityRange> past ranges are left out (R12) */
    public function currentAvailability(): Collection
    {
        return $this->availabilityRanges()->current()->orderBy('local_starts_at')->orderBy('id')->get();
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

    /** @return Collection<int, Showing> */
    public function orderedShowings(): Collection
    {
        return $this->showings()->with('listing')->orderBy('starts_at')->orderBy('id')->get();
    }

    /** @return Collection<int, DatabaseNotification> */
    public function latestNotifications(): Collection
    {
        return $this->notifications()->limit(50)->get();
    }
}
