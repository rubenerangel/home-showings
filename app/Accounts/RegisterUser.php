<?php

namespace App\Accounts;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class RegisterUser
{
    private const CHECK_VIOLATION = '23514';

    /** @param array<string, mixed> $data already validated */
    public function handle(array $data): User
    {
        try {
            return User::create($data);
        } catch (UniqueConstraintViolationException) {
            // Someone else registered it after the validation.
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        } catch (QueryException $exception) {
            // The database has the last word on the email, whatever got past the validation.
            if ($this->isAboutTheEmail($exception)) {
                throw ValidationException::withMessages(['email' => EmailFormat::MESSAGE]);
            }
            throw $exception;
        }
    }

    private function isAboutTheEmail(QueryException $exception): bool
    {
        // Only the first line: the next one has the refused row, which the user wrote.
        return $exception->getCode() === self::CHECK_VIOLATION
            && str_contains(strtok($exception->errorInfo[2], "\n"), EmailFormat::CONSTRAINT);
    }
}
