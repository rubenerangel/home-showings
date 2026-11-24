<?php

namespace Tests\Feature\Seller;

use App\Enums\ShowingStatus;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** R13. The factory showing is 2027-03-10 17:00-17:30 UTC. */
class ListingLocksTest extends TestCase
{
    use RefreshDatabase;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listing = Listing::factory()->create();
        $this->actingAs($this->listing->seller);
    }

    #[DataProvider('locationChanges')]
    public function test_the_location_is_locked_until_the_showing_ends(string $field, string $value): void
    {
        $this->showing();

        foreach (['2027-03-01 00:00:00', '2027-03-10 17:15:00', '2027-03-10 17:29:59'] as $now) {
            $this->travelTo(Carbon::parse($now, 'UTC'));
            $this->update([$field => $value, 'price' => 1])->assertSessionHasErrors($field);
            $this->assertDatabaseMissing('listings', [$field => $value]);
            $this->assertDatabaseMissing('listings', ['price' => 1]);
        }

        $this->travelTo(Carbon::parse('2027-03-10 17:30:00', 'UTC'));
        $this->update([$field => $value])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('listings', [$field => $value]);
    }

    /** @return array<string, array{string, string}> */
    public static function locationChanges(): array
    {
        return ['address' => ['address', '1 New St'], 'city' => ['city', 'Aurora'], 'state' => ['state', 'NM']];
    }

    #[DataProvider('cancelled')]
    public function test_a_cancelled_showing_does_not_lock_the_location(ShowingStatus $status): void
    {
        $this->travelTo(Carbon::parse('2027-03-10 12:00:00', 'UTC'));
        $this->showing($status);

        $this->update(['address' => '1 New St'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('listings', ['address' => '1 New St']);
    }

    public function test_the_showing_of_another_listing_locks_nothing_here(): void
    {
        $this->travelTo(Carbon::parse('2027-03-10 12:00:00', 'UTC'));
        Showing::factory()->create();

        $this->update(['address' => '1 New St', 'timezone' => 'America/Phoenix'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('listings', ['address' => '1 New St', 'timezone' => 'America/Phoenix']);
    }

    public function test_locked_values_sent_unchanged_do_not_block_the_other_fields(): void
    {
        $this->travelTo(Carbon::parse('2027-03-10 12:00:00', 'UTC'));
        $this->showing();

        $this->update(['price' => 425_000, 'description' => 'New roof.', 'min_notice_hours' => 24])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('listings', ['price' => 425_000, 'description' => 'New roof.', 'min_notice_hours' => 24]);
    }

    #[DataProvider('moments')]
    public function test_an_open_window_locks_the_zone_but_not_the_location(string $now): void
    {
        $this->travelTo(Carbon::parse($now, 'UTC'));
        ShowingWindow::factory()->for($this->listing)->create();

        $this->update(['timezone' => 'America/Phoenix'])->assertSessionHasErrors('timezone');
        $this->assertDatabaseMissing('listings', ['timezone' => 'America/Phoenix']);
        $this->update(['address' => '1 New St'])->assertSessionHasNoErrors();
    }

    /** @return array<string, array{string}> */
    public static function moments(): array
    {
        return ['future window' => ['2027-03-01 00:00:00'], 'past window' => ['2027-04-01 00:00:00']];
    }

    public function test_a_closed_window_without_showings_does_not_lock_the_zone(): void
    {
        ShowingWindow::factory()->for($this->listing)->create(['closed_at' => now()]);

        $this->update(['timezone' => 'America/Phoenix'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('listings', ['timezone' => 'America/Phoenix']);
    }

    #[DataProvider('statuses')]
    public function test_any_showing_locks_the_zone_even_when_past_or_cancelled_in_a_closed_window(ShowingStatus $status): void
    {
        $this->showing($status);
        $this->listing->windows()->update(['closed_at' => now()]);
        $this->travelTo(Carbon::parse('2027-04-01 00:00:00', 'UTC'));

        $this->update(['timezone' => 'America/Phoenix'])->assertSessionHasErrors('timezone');
        $this->assertDatabaseMissing('listings', ['timezone' => 'America/Phoenix']);
    }

    public function test_changing_the_duration_does_not_touch_existing_windows(): void
    {
        $this->travelTo(Carbon::parse('2027-03-10 12:00:00', 'UTC'));
        $showing = $this->showing();

        $this->update(['showing_duration_minutes' => 60])->assertSessionHasNoErrors();

        $this->assertSame(60, $this->listing->refresh()->showing_duration_minutes);
        $this->assertSame(30, $showing->window->refresh()->duration_minutes);
        $this->assertSame('2027-03-10 17:30:00', $showing->refresh()->ends_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_edit_page_says_what_is_locked(): void
    {
        $this->travelTo(Carbon::parse('2027-03-10 12:00:00', 'UTC'));
        $this->assertLocks(location: false, timezone: false);

        ShowingWindow::factory()->for($this->listing)->create();
        $this->assertLocks(location: false, timezone: true);

        $this->showing(window: $this->listing->windows()->sole());
        $this->assertLocks(location: true, timezone: true);
    }

    /** @return array<string, array{ShowingStatus}> */
    public static function cancelled(): array
    {
        return [
            'by the buyer' => [ShowingStatus::CancelledByBuyer],
            'by the seller' => [ShowingStatus::CancelledBySeller],
        ];
    }

    /** @return array<string, array{ShowingStatus}> */
    public static function statuses(): array
    {
        return ['confirmed' => [ShowingStatus::Confirmed], ...self::cancelled()];
    }

    private function showing(ShowingStatus $status = ShowingStatus::Confirmed, ?ShowingWindow $window = null): Showing
    {
        $window ??= ShowingWindow::factory()->for($this->listing)->create();
        $factory = Showing::factory()->for($window, 'window');

        return $status === ShowingStatus::Confirmed ? $factory->create() : $factory->cancelled($status)->create();
    }

    /** @param array<string, mixed> $changes */
    private function update(array $changes): TestResponse
    {
        $current = array_diff_key(
            $this->listing->fresh()->getAttributes(),
            array_flip(['id', 'seller_id', 'created_at', 'updated_at']),
        );

        return $this->put("/seller/listings/{$this->listing->id}", [...$current, ...$changes]);
    }

    private function assertLocks(bool $location, bool $timezone): void
    {
        $this->get("/seller/listings/{$this->listing->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('locks', ['location' => $location, 'timezone' => $timezone]));
    }
}
