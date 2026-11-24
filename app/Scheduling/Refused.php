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
     * Turns an exclusion violation into the refusal of its constraint. Any other error passes through.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @param  array<string, Refusal>  $reasons  by constraint name
     * @return T
     */
    public static function onConflict(callable $write, array $reasons): mixed
    {
        try {
            return $write();
        } catch (QueryException $exception) {
            if ($exception->getCode() === self::EXCLUSION_VIOLATION) {
                foreach ($reasons as $constraint => $reason) {
                    if (str_contains($exception->getMessage(), $constraint)) {
                        throw new self($reason);
                    }
                }
            }

            throw $exception;
        }
    }
}
