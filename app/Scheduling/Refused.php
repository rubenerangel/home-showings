<?php

namespace App\Scheduling;

use Illuminate\Database\QueryException;
use RuntimeException;

/** A business refusal: expected, shown to the user, and never reported as an error. */
final class Refused extends RuntimeException
{
    private const EXCLUSION_VIOLATION = '23P01';

    public function __construct(public readonly Refusal $reason, ?string $message = null)
    {
        parent::__construct($message ?? $reason->message());
    }

    /**
     * The refusal of the exclusion constraint that was violated, or null for any other error.
     *
     * @param  array<string, Refusal>  $reasons  by constraint name
     */
    public static function fromConflict(QueryException $exception, array $reasons): ?self
    {
        if ($exception->getCode() !== self::EXCLUSION_VIOLATION) {
            return null;
        }

        $constraint = array_find(array_keys($reasons), fn (string $name) => str_contains($exception->getMessage(), $name));

        return $constraint === null ? null : new self($reasons[$constraint]);
    }
}
