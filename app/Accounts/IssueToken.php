<?php

namespace App\Accounts;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class IssueToken
{
    private const DAYS = 30;

    public function handle(User $user, string $deviceName): NewAccessToken
    {
        $user->tokens()->where('expires_at', '<=', now())->delete();

        return $user->createToken($deviceName, expiresAt: now()->addDays(self::DAYS));
    }
}
