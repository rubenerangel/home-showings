<?php

namespace App\Http;

use App\Enums\Role;
use App\Listings\Catalog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/** Where someone goes once signed in: to the home they wanted, or to their area (R23). */
class AfterSignIn
{
    public function __construct(private readonly Catalog $catalog) {}

    /** @param mixed $home as it came in the request */
    public function go(User $user, mixed $home): RedirectResponse
    {
        $listing = $user->role === Role::Buyer ? $this->catalog->toVisit($home) : null;

        return $listing === null ? redirect()->route($user->role->home()) : redirect()->route('homes.show', $listing->id);
    }

    /** @return array{id: int, address: string}|null */
    public function wanted(mixed $home): ?array
    {
        return $this->catalog->toVisit($home)?->only('id', 'address');
    }
}
