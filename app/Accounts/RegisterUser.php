<?php

namespace App\Accounts;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final class RegisterUser
{
    /** @param array<string, mixed> $data already validated */
    public function handle(array $data): User
    {
        try {
            return User::create($data);
        } catch (UniqueConstraintViolationException) {
            // Someone else registered it after the validation.
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        }
    }
}
