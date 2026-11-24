<?php

namespace App\Http;

use App\Accounts\EmailFormat;
use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** What is sent to register or to log in. Emails are kept in lower case. */
final class AccountInput
{
    /** For the API, which names the tokens. */
    public const DEVICE_NAME = ['device_name' => ['bail', 'required', 'string', 'max:100', self::ONE_LINE]];

    private const ONE_LINE = 'regex:/^[^\x00-\x1F\x7F]+$/uD';

    /** ASCII only, so PHP and PostgreSQL always agree on the lower case of an email. */
    private const ASCII = 'regex:/^[\x21-\x7E]+$/D';

    private const EMAIL = ['bail', 'required', 'string', 'max:255'];

    /**
     * @param  array<string, list<mixed>>  $extra  more rules
     * @return array<string, mixed>
     */
    public static function registration(Request $request, array $extra = []): array
    {
        self::lowerEmail($request);

        return $request->validate([
            'name' => ['bail', 'required', 'string', 'max:255', self::ONE_LINE],
            'email' => [...self::EMAIL, self::complete(...), 'email', 'unique:users'],
            'password' => ['bail', 'required', 'string', 'confirmed', 'min:8', self::hashable(...)],
            'role' => ['bail', 'required', 'string', Rule::enum(Role::class)],
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, list<mixed>>  $extra  more rules
     * @return array<string, string>
     */
    public static function login(Request $request, array $extra = []): array
    {
        self::lowerEmail($request);

        return $request->validate([
            'email' => [...self::EMAIL, self::ASCII, 'email'],
            'password' => ['bail', 'required', 'string'],
            ...$extra,
        ]);
    }

    private static function lowerEmail(Request $request): void
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower($email)]);
        }
    }

    private static function complete(string $attribute, string $value, Closure $fail): void
    {
        if (! EmailFormat::isComplete($value)) {
            $fail(EmailFormat::MESSAGE);
        }
    }

    /** Bcrypt ignores what comes after 72 bytes, and rejects null bytes. */
    private static function hashable(string $attribute, string $value, Closure $fail): void
    {
        if (strlen($value) > 72) {
            $fail('The password must not be longer than 72 bytes.');
        } elseif (str_contains($value, "\0")) {
            $fail('The password has a character that is not allowed.');
        }
    }
}
