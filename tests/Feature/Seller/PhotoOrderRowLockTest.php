<?php

namespace Tests\Feature\Seller;

use App\Models\Listing;
use App\Models\ListingPhoto;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Criterion 48. Committed data and a second connection, so nothing here runs inside a wrapping transaction. */
class PhotoOrderRowLockTest extends TestCase
{
    use DatabaseMigrations;

    private const LOCK_NOT_AVAILABLE = '55P03';

    private Listing $listing;

    /** @var list<ListingPhoto> */
    private array $photos;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.other' => config('database.connections.pgsql')]);
        DB::statement("SET lock_timeout = '300ms'");
        $this->listing = Listing::factory()->create();
        foreach ([1, 2, 3] as $position) {
            $this->photos[] = $this->listing->photos()->create(['path' => "$position.jpg", 'room' => 'other', 'position' => $position]);
        }
    }

    protected function tearDown(): void
    {
        DB::connection('other')->rollBack();
        DB::disconnect('other');
        DB::disconnect();
        parent::tearDown();
    }

    public function test_it_waits_for_the_listing(): void
    {
        $other = DB::connection('other');
        $other->beginTransaction();
        $other->table('listings')->where('id', $this->listing->id)->lockForUpdate()->get();

        $this->assertWaits();

        $other->commit();
        $this->order()->assertSessionHasNoErrors();
        $this->assertOrder([2, 0, 1]);
    }

    public function test_it_waits_for_a_photo_that_is_being_removed_and_then_refuses(): void
    {
        $other = DB::connection('other');
        $other->beginTransaction();
        $other->table('listing_photos')->where('id', $this->photos[1]->id)->delete();

        $this->assertWaits();

        $other->commit();
        $this->order()->assertSessionHasErrors('photos');
        $this->assertOrder([0, 2]);
    }

    private function assertWaits(): void
    {
        try {
            $this->withoutExceptionHandling();
            $this->order();
            $this->fail('It did not wait.');
        } catch (QueryException $exception) {
            $this->assertSame(self::LOCK_NOT_AVAILABLE, $exception->getCode());
        }
        $this->withExceptionHandling();
    }

    private function order(): TestResponse
    {
        return $this->actingAs($this->listing->seller)->put("/seller/listings/{$this->listing->id}/photos/order", [
            'photos' => [$this->photos[2]->id, $this->photos[0]->id, $this->photos[1]->id],
        ]);
    }

    /** @param list<int> $indexes */
    private function assertOrder(array $indexes): void
    {
        $this->assertSame(
            array_map(fn (int $index) => $this->photos[$index]->id, $indexes),
            $this->listing->photos()->get()->pluck('id')->all(),
        );
    }
}
