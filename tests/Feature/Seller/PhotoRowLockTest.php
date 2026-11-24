<?php

namespace Tests\Feature\Seller;

use App\Models\Listing;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Committed data and a second connection, so nothing here runs inside a wrapping transaction. */
class PhotoRowLockTest extends TestCase
{
    use DatabaseMigrations;

    private const LOCK_NOT_AVAILABLE = '55P03';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['database.connections.other' => config('database.connections.pgsql')]);
        DB::statement("SET lock_timeout = '300ms'");
    }

    protected function tearDown(): void
    {
        DB::connection('other')->rollBack();
        DB::disconnect('other');
        DB::disconnect();
        parent::tearDown();
    }

    public function test_an_upload_waits_for_another_one_and_then_counts_its_photo(): void
    {
        $listing = Listing::factory()->create();
        foreach (range(0, 3) as $position) {
            $listing->photos()->create(['path' => "$position.jpg", 'room' => 'other', 'position' => $position]);
        }
        $upload = fn () => $this->actingAs($listing->seller)->post("/seller/listings/{$listing->id}/photos", [
            'photos' => [['file' => UploadedFile::fake()->create('a.jpg', 1, 'image/jpeg'), 'room' => 'other']],
        ]);

        $other = DB::connection('other');
        $other->beginTransaction();
        $other->table('listing_photos')->insert([
            'listing_id' => $listing->id, 'path' => 'fifth.jpg', 'room' => 'other', 'position' => 100,
        ]);

        try {
            $this->withoutExceptionHandling();
            $upload();
            $this->fail('The upload did not wait for the other one.');
        } catch (QueryException $exception) {
            $this->assertSame(self::LOCK_NOT_AVAILABLE, $exception->getCode());
        }

        $other->commit();
        $this->withExceptionHandling();
        $upload()->assertSessionHasErrors('photos');
        $this->assertSame(5, $listing->photos()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
