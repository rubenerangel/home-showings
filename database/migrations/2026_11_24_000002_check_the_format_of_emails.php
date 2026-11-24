<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** A copy of EmailFormat::PATTERN on the day of this migration. If that changes, another migration does too. */
    private const PATTERN = <<<'REGEX'
        ^[a-z0-9!#$%&'*+/=?^_`{|}~-]+(\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]([a-z0-9-]{0,61}[a-z0-9])$
        REGEX;

    /** Lower case is part of the format. */
    public function up(): void
    {
        $this->check('email ~ '.DB::escape(self::PATTERN));
    }

    public function down(): void
    {
        $this->check('email = lower(email)');
    }

    private function check(string $condition): void
    {
        DB::statement("ALTER TABLE users DROP CONSTRAINT users_email_check, ADD CONSTRAINT users_email_check CHECK ($condition)");
    }
};
