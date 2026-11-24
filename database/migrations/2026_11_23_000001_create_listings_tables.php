<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->index()->constrained('users');
            $table->string('address');
            $table->string('city');
            $table->string('state', 2);
            $table->integer('price');
            $table->smallInteger('bedrooms');
            $table->decimal('bathrooms', 3, 1);
            $table->text('description')->nullable();
            $table->string('timezone', 40);
            $table->smallInteger('showing_duration_minutes')->default(30);
            $table->smallInteger('min_notice_hours')->default(2);
            $table->timestamps();
        });
        DB::statement(<<<'SQL'
            ALTER TABLE listings
                ADD CONSTRAINT listings_price_check CHECK (price >= 1),
                ADD CONSTRAINT listings_bedrooms_check CHECK (bedrooms >= 0),
                ADD CONSTRAINT listings_bathrooms_check CHECK (bathrooms >= 0.5 AND mod(bathrooms, 0.5) = 0),
                ADD CONSTRAINT listings_timezone_check CHECK (timezone IN (
                    'America/New_York', 'America/Chicago', 'America/Denver', 'America/Phoenix', 'America/Los_Angeles'
                )),
                ADD CONSTRAINT listings_duration_check CHECK (showing_duration_minutes IN (15, 30, 45, 60)),
                ADD CONSTRAINT listings_notice_check CHECK (min_notice_hours IN (0, 2, 12, 24, 48))
            SQL);

        Schema::create('listing_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('room', 20);
            $table->smallInteger('position');
            $table->timestamps();
            $table->unique(['listing_id', 'position']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE listing_photos ADD CONSTRAINT listing_photos_room_check
                CHECK (room IN ('exterior', 'living_room', 'kitchen', 'bedroom', 'bathroom', 'other'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_photos');
        Schema::dropIfExists('listings');
    }
};
