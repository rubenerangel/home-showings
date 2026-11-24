<?php

namespace App\Accounts;

/** What a complete email is (R26), for the page and the server. The constraint of the database has the same pattern. */
final class EmailFormat
{
    public const MESSAGE = 'Enter a complete email, like name@example.com.';

    public const CONSTRAINT = 'users_email_check';

    /** Only with what JavaScript, PCRE and PostgreSQL read in the same way. In lower case, as emails are kept. */
    public const PATTERN = '^'.self::ATOM.'(\.'.self::ATOM.')*@('.self::LABEL.'\.)+'.self::LAST_LABEL.'$';

    private const ATOM = "[a-z0-9!#$%&'*+/=?^_`{|}~-]+";

    private const LABEL = '[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?';

    /** Starts with a letter, so the numbers of an address on the network are not a domain. */
    private const LAST_LABEL = '[a-z]([a-z0-9-]{0,61}[a-z0-9])';

    public static function isComplete(string $email): bool
    {
        return preg_match('"'.self::PATTERN.'"D', $email) === 1;
    }
}
