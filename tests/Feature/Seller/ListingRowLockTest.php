<?php

namespace Tests\Feature\Seller;

use App\Models\Listing;
use App\Models\ShowingWindow;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Committed data and a second connection, so nothing here runs inside a wrapping transaction. */
class ListingRowLockTest extends TestCase
{
    use DatabaseMigrations;

    private const LOCK_NOT_AVAILABLE = '55P03';

    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_editing_waits_for_a_window_that_is_being_created_and_then_sees_it(): void
    {
        $listing = Listing::factory()->create();
        $attributes = array_diff_key($listing->refresh()->getAttributes(), array_flip(['id', 'seller_id']));
        $edit = fn () => $this->actingAs($listing->seller)
            ->put("/seller/listings/{$listing->id}", [...$attributes, 'timezone' => 'America/Phoenix']);

        $other = DB::connection('other');
        $other->beginTransaction();
        $window = ShowingWindow::factory()->for($listing)->make();
        $other->table('showing_windows')->insert($window->getAttributes());

        try {
            $this->withoutExceptionHandling();
            $edit();
            $this->fail('The edit did not wait for the window.');
        } catch (QueryException $exception) {
            $this->assertSame(self::LOCK_NOT_AVAILABLE, $exception->getCode());
        }

        $other->commit();
        $this->withExceptionHandling();
        $edit()->assertSessionHasErrors('timezone');
        $this->assertSame('America/Denver', $listing->refresh()->timezone->value);
    }
}
