<?php

namespace App\Accounts;

use App\Models\User;

class RevokeTokens
{
    /** The one of this request. */
    public function current(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function all(User $user): void
    {
        $user->tokens()->delete();
    }
}
