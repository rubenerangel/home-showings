<?php

namespace App\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

/** An account and its first token. Alone, the account would only leave a taken email behind. */
class RegisterDevice
{
    public function __construct(private readonly RegisterUser $registerUser, private readonly IssueToken $issueToken) {}

    /**
     * @param  array<string, mixed>  $data  already validated
     * @return array{User, NewAccessToken}
     */
    public function handle(array $data, string $deviceName): array
    {
        return DB::transaction(function () use ($data, $deviceName) {
            $user = $this->registerUser->handle($data);

            return [$user, $this->issueToken->handle($user, $deviceName)];
        });
    }
}
