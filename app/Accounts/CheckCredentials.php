<?php

namespace App\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Who has this email and password. The failures of the web and of the API count together. */
final class CheckCredentials
{
    private const MAX_FAILURES = 5;

    public function handle(string $email, string $password, ?string $address): User
    {
        // Counted before checking, so attempts sent at once do not get past the limit.
        $key = 'login:'.hash('sha256', $email.'|'.$address);
        if (RateLimiter::hit($key) > self::MAX_FAILURES) {
            throw new LockedOut(RateLimiter::availableIn($key));
        }

        $guard = Auth::guard('web');
        if (! $guard->validate(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages(['email' => 'The email or the password is not right.']);
        }
        RateLimiter::clear($key);

        return $guard->getLastAttempted();
    }
}
