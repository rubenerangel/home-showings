<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lets a GiST exclusion constraint compare plain integers. Trusted, so no superuser is needed.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // Wall clock times of the house: no time zone on purpose.
        Schema::create('showing_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained();
            $table->timestamp('local_starts_at');
            $table->timestamp('local_ends_at');
            $table->smallInteger('duration_minutes');
            $table->timestampTz('closed_at')->nullable();
            $table->timestamps();
        });
        DB::statement(<<<'SQL'
            ALTER TABLE showing_windows
                ADD CONSTRAINT showing_windows_order_check CHECK (local_ends_at > local_starts_at),
                ADD CONSTRAINT showing_windows_same_date_check CHECK (local_starts_at::date = local_ends_at::date),
                ADD CONSTRAINT showing_windows_duration_check CHECK (duration_minutes IN (15, 30, 45, 60)),
                ADD CONSTRAINT showing_windows_no_overlap EXCLUDE USING gist (
                    listing_id WITH =,
                    tsrange(local_starts_at, local_ends_at) WITH &&
                ) WHERE (closed_at IS NULL)
            SQL);

        Schema::create('availability_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->index()->constrained('users');
            $table->timestamp('local_starts_at');
            $table->timestamp('local_ends_at');
            $table->timestamps();
        });
        DB::statement(<<<'SQL'
            ALTER TABLE availability_ranges
                ADD CONSTRAINT availability_ranges_order_check CHECK (local_ends_at > local_starts_at),
                ADD CONSTRAINT availability_ranges_same_date_check CHECK (local_starts_at::date = local_ends_at::date)
            SQL);

        Schema::create('showings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('showing_window_id')->index()->constrained();
            $table->foreignId('listing_id')->index()->constrained();
            $table->foreignId('buyer_id')->index()->constrained('users');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status', 20);
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();
        });
        DB::statement(<<<'SQL'
            ALTER TABLE showings
                ADD CONSTRAINT showings_order_check CHECK (ends_at > starts_at),
                ADD CONSTRAINT showings_status_check CHECK (
                    status IN ('confirmed', 'cancelled_by_buyer', 'cancelled_by_seller')
                ),
                ADD CONSTRAINT showings_cancelled_check CHECK ((status = 'confirmed') = (cancelled_at IS NULL)),
                ADD CONSTRAINT showings_listing_no_overlap EXCLUDE USING gist (
                    listing_id WITH =,
                    tstzrange(starts_at, ends_at) WITH &&
                ) WHERE (status = 'confirmed'),
                ADD CONSTRAINT showings_buyer_no_overlap EXCLUDE USING gist (
                    buyer_id WITH =,
                    tstzrange(starts_at, ends_at) WITH &&
                ) WHERE (status = 'confirmed')
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('showings');
        Schema::dropIfExists('availability_ranges');
        Schema::dropIfExists('showing_windows');
    }
};
