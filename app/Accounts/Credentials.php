<?php

namespace App\Accounts;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class Credentials
{
    /** ASCII only, so PHP and PostgreSQL always agree on the lower case of an email. */
    public const EMAIL_RULES = ['bail', 'required', 'string', 'max:255', 'regex:/^[\x21-\x7E]+$/', 'email'];

    /** Emails are kept in lower case. */
    public static function lowerEmail(Request $request): void
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower($email)]);
        }
    }
}
