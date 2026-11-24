<?php

namespace App\Http;

use App\Models\ListingPhoto;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/** A client of the API has no page to resolve a relative address against. */
final class ApiPresenter extends Presenter
{
    /** @return array<string, mixed> */
    public function user(User $user): array
    {
        return $user->only('id', 'name', 'email', 'role');
    }

    /** @return array<string, mixed> */
    public function token(User $user, NewAccessToken $token): array
    {
        return [
            'user' => $this->user($user),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toIso8601ZuluString(),
        ];
    }

    protected function photoUrl(ListingPhoto $photo): string
    {
        return url($photo->url());
    }
}
