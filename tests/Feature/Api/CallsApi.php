<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Testing\TestResponse;

trait CallsApi
{
    private function token(User $user): string
    {
        return $user->createToken('test', expiresAt: now()->addDays(30))->plainTextToken;
    }

    /** @param array<string, mixed> $data */
    private function api(string $method, string $uri, array $data = [], User|string|null $as = null): TestResponse
    {
        // Every call is a new request: nobody stays signed in from the one before.
        auth()->forgetGuards();
        $token = $as instanceof User ? $this->token($as) : $as;

        return $this->json($method, "/api/v1$uri", $data, $token === null ? [] : ['Authorization' => "Bearer $token"]);
    }
}
