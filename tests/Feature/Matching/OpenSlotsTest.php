<?php

namespace Tests\Feature\Matching;

use App\Enums\HouseTimezone;
use App\Enums\ShowingStatus;
use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use App\Scheduling\OpenSlots;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Criteria 2 to 6, rules R3, R4, R5, R12 and R15. Everything happens on 2027-03-10. */
class OpenSlotsTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->buyer()->create();
        $this->nowInDenver('07:00:00');
    }

    // Criterion 2

    public function test_only_slots_fully_inside_the_availability_are_offered(): void
    {
        $listing = $this->listingWithWindow('10:00', '12:00');
        $this->available('10:10', '11:20');

        $this->assertOffered([$listing->id => ['10:30']]);
    }

    public function test_a_slot_that_spans_two_touching_ranges_is_offered(): void
    {
        $listing = $this->listingWithWindow('10:15', '10:45');
        $this->available('10:00', '10:30');
        $this->available('10:30', '11:00');

        $this->assertOffered([$listing->id => ['10:15']]);
    }

    public function test_availability_of_another_buyer_does_not_count(): void
    {
        $this->listingWithWindow('10:00', '12:00');
        AvailabilityRange::factory()->between('2027-03-10', '10:00', '12:00')->create();

        $this->assertOffered([]);
    }

    // Criterion 3

    public function test_a_listing_without_a_bookable_slot_is_not_shown(): void
    {
        $this->listingWithWindow('15:00', '16:00');
        Listing::factory()->create();
        $shown = $this->listingWithWindow('10:00', '10:30');
        $this->available('10:00', '12:00');

        $this->assertOffered([$shown->id => ['10:00']]);
    }

    // Criterion 4

    public function test_notice_is_measured_to_the_start_of_the_slot(): void
    {
        $listing = $this->listingWithWindow('10:00', '12:00', ['min_notice_hours' => 2]);
        $this->available('08:00', '18:00');

        $this->nowInDenver('09:00:00');
        $this->assertOffered([$listing->id => ['11:00', '11:30']]);

        $this->nowInDenver('09:00:01');
        $this->assertOffered([$listing->id => ['11:30']]);
    }

    public function test_each_listing_uses_its_own_notice(): void
    {
        $this->listingWithWindow('10:00', '10:30', ['min_notice_hours' => 12]);
        $shown = $this->listingWithWindow('10:00', '10:30', ['min_notice_hours' => 0]);
        $this->available('08:00', '18:00');

        $this->nowInDenver('09:00:00');
        $this->assertOffered([$shown->id => ['10:00']]);
    }

    public function test_a_started_slot_is_never_offered_even_without_notice(): void
    {
        $listing = $this->listingWithWindow('10:00', '11:00', ['min_notice_hours' => 0]);
        $this->available('08:00', '18:00');

        $this->nowInDenver('09:59:59');
        $this->assertOffered([$listing->id => ['10:00', '10:30']]);

        $this->nowInDenver('10:00:00');
        $this->assertOffered([$listing->id => ['10:30']]);
    }

    // Criterion 5 and R15

    public function test_one_range_matches_the_same_local_time_in_two_zones_ordered_by_instant(): void
    {
        $denver = $this->listingWithWindow('10:00', '10:30');
        $newYork = $this->listingWithWindow('10:00', '10:30', ['timezone' => HouseTimezone::Eastern]);
        $this->available('10:00', '12:00');
        $this->nowInDenver('02:00:00');

        $matches = app(OpenSlots::class)->forBuyer($this->buyer);

        $this->assertSame([$newYork->id, $denver->id], $matches->map(fn ($match) => $match->listing->id)->all());
        $this->assertSame(
            ['2027-03-10T15:00:00Z', '2027-03-10T17:00:00Z'],
            $matches->map(fn ($match) => $match->slots[0]->startsAt->toIso8601ZuluString())->all(),
        );
        $this->assertSame('2027-03-10T15:30:00Z', $matches[0]->slots[0]->endsAt->toIso8601ZuluString());
    }

    public function test_listings_with_the_same_earliest_slot_are_ordered_by_id_and_slots_by_time(): void
    {
        $first = $this->listingWithWindow('14:00', '14:30');
        $second = $this->listingWithWindow('10:00', '10:30');
        ShowingWindow::factory()->for($first)->between('2027-03-10', '10:00', '10:30')->create();
        $this->available('08:00', '18:00');

        // The database returns them the other way round, so the order has to come from the rule.
        $scopes = Model::getAllGlobalScopes();
        Listing::addGlobalScope('reversed', fn (Builder $query) => $query->orderByDesc('id'));
        try {
            $this->assertOffered([$first->id => ['10:00', '14:00'], $second->id => ['10:00']]);
        } finally {
            Model::setAllGlobalScopes($scopes);
        }
    }

    // Criterion 6

    public function test_a_booked_slot_is_not_offered(): void
    {
        $listing = $this->listingWithWindow('10:00', '11:30');
        $this->showing($listing, '10:30', '11:00');
        $this->available('08:00', '18:00');

        $this->assertOffered([$listing->id => ['10:00', '11:00']]);
    }

    public function test_a_booking_from_another_window_hides_every_slot_it_overlaps(): void
    {
        $listing = $this->listingWithWindow('10:00', '11:00', [], ['closed_at' => now()]);
        $this->showing($listing, '10:00', '10:30');
        ShowingWindow::factory()->for($listing)->between('2027-03-10', '10:00', '11:00')->create(['duration_minutes' => 15]);
        $this->available('08:00', '18:00');

        $this->assertOffered([$listing->id => ['10:30', '10:45']]);
    }

    public function test_the_slots_of_a_closed_window_are_not_offered(): void
    {
        $this->listingWithWindow('10:00', '11:00', [], ['closed_at' => now()]);
        $this->available('08:00', '18:00');

        $this->assertOffered([]);
    }

    public function test_a_slot_cancelled_by_the_seller_stays_closed_in_its_window_only(): void
    {
        $listing = $this->listingWithWindow('10:00', '11:00');
        $this->showing($listing, '10:00', '10:30', ShowingStatus::CancelledBySeller);
        $this->available('08:00', '18:00');

        $this->assertOffered([$listing->id => ['10:30']]);

        $listing->windows()->update(['closed_at' => now()]);
        ShowingWindow::factory()->for($listing)->between('2027-03-10', '10:00', '11:00')->create();
        $this->assertOffered([$listing->id => ['10:00', '10:30']]);
    }

    public function test_a_slot_cancelled_by_the_buyer_is_offered_again_if_the_notice_holds(): void
    {
        $listing = $this->listingWithWindow('10:00', '11:00', ['min_notice_hours' => 2]);
        $this->showing($listing, '10:00', '10:30', ShowingStatus::CancelledByBuyer);
        $this->available('08:00', '18:00');

        $this->nowInDenver('08:00:00');
        $this->assertOffered([$listing->id => ['10:00', '10:30']]);

        $this->nowInDenver('08:00:01');
        $this->assertOffered([$listing->id => ['10:30']]);
    }

    public function test_slots_that_overlap_a_showing_of_the_buyer_are_hidden_and_adjacent_ones_are_not(): void
    {
        $elsewhere = $this->listingWithWindow('10:45', '11:15');
        $this->showing($elsewhere, '10:45', '11:15', buyer: $this->buyer);
        $listing = $this->listingWithWindow('09:45', '12:15');
        $this->available('08:00', '18:00');
        $this->nowInDenver('06:00:00');

        $this->assertOffered([$listing->id => ['09:45', '10:15', '11:15', '11:45']]);
    }

    public function test_a_partial_overlap_hides_the_slots_on_both_sides(): void
    {
        $listing = $this->listingWithWindow('10:15', '10:45');
        $this->showing($listing, '10:15', '10:45');
        $listing->windows()->update(['closed_at' => now()]);
        ShowingWindow::factory()->for($listing)->between('2027-03-10', '09:30', '11:30')->create();
        $this->available('08:00', '18:00');

        $this->assertOffered([$listing->id => ['09:30', '11:00']]);
    }

    public function test_a_partial_overlap_with_a_showing_of_the_buyer_hides_the_slots_on_both_sides(): void
    {
        $elsewhere = $this->listingWithWindow('10:15', '10:45');
        $this->showing($elsewhere, '10:15', '10:45', buyer: $this->buyer);
        $listing = $this->listingWithWindow('09:30', '11:30');
        $this->available('08:00', '18:00');

        $this->assertOffered([$listing->id => ['09:30', '11:00']]);
    }

    public function test_an_overlap_is_found_across_time_zones(): void
    {
        $denver = $this->listingWithWindow('10:00', '10:30');
        $this->showing($denver, '10:00', '10:30', buyer: $this->buyer);
        $newYork = $this->listingWithWindow('11:30', '12:30', ['timezone' => HouseTimezone::Eastern]);
        $this->available('08:00', '18:00');

        $this->assertOffered([$newYork->id => ['11:30']]);
    }

    public function test_a_cancelled_showing_of_the_buyer_hides_nothing(): void
    {
        $elsewhere = $this->listingWithWindow('15:00', '15:30');
        $this->showing($elsewhere, '15:00', '15:30', ShowingStatus::CancelledByBuyer, $this->buyer);
        $listing = $this->listingWithWindow('15:00', '15:30');
        $this->available('08:00', '18:00');

        $this->assertOffered([$elsewhere->id => ['15:00'], $listing->id => ['15:00']]);
    }

    /** @param list<string> $offered */
    #[DataProvider('momentsAroundTheEnd')]
    public function test_a_listing_is_hidden_while_the_buyer_has_an_active_showing_on_it(string $now, array $offered): void
    {
        $listing = $this->listingWithWindow('10:00', '16:00', ['showing_duration_minutes' => 60, 'min_notice_hours' => 0]);
        $this->showing($listing, '10:00', '11:00', buyer: $this->buyer);
        $this->available('08:00', '13:00');

        $this->nowInDenver($now);
        $this->assertOffered($offered === [] ? [] : [$listing->id => $offered]);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function momentsAroundTheEnd(): array
    {
        return [
            'before it starts' => ['09:00:00', []],
            'one second before it ends' => ['10:59:59', []],
            'when it ends' => ['11:00:00', ['12:00']],
        ];
    }

    public function test_an_active_showing_of_another_buyer_does_not_hide_the_listing(): void
    {
        $listing = $this->listingWithWindow('10:00', '11:00');
        $this->showing($listing, '10:00', '10:30');
        $this->available('08:00', '18:00');

        $this->assertOffered([$listing->id => ['10:30']]);
    }

    // Helpers

    private function nowInDenver(string $time): void
    {
        $this->travelTo(CarbonImmutable::parse("2027-03-10 $time", 'America/Denver'));
    }

    private function available(string $start, string $end): void
    {
        AvailabilityRange::factory()->for($this->buyer, 'buyer')->between('2027-03-10', $start, $end)->create();
    }

    /**
     * @param  array<string, mixed>  $listing
     * @param  array<string, mixed>  $window
     */
    private function listingWithWindow(string $start, string $end, array $listing = [], array $window = []): Listing
    {
        $listing = Listing::factory()->create($listing);
        ShowingWindow::factory()->for($listing)->between('2027-03-10', $start, $end)
            ->create(['duration_minutes' => $listing->showing_duration_minutes, ...$window]);

        return $listing;
    }

    private function showing(Listing $listing, string $start, string $end, ShowingStatus $status = ShowingStatus::Confirmed, ?User $buyer = null): void
    {
        Showing::factory()->create([
            'showing_window_id' => $listing->windows()->firstOrFail()->id,
            'buyer_id' => $buyer->id ?? User::factory()->buyer(),
            'starts_at' => CarbonImmutable::parse("2027-03-10 $start", $listing->timezone->value),
            'ends_at' => CarbonImmutable::parse("2027-03-10 $end", $listing->timezone->value),
            'status' => $status,
            'cancelled_at' => $status === ShowingStatus::Confirmed ? null : now(),
        ]);
    }

    /** @param array<int, list<string>> $expected house times by listing id, in order */
    private function assertOffered(array $expected): void
    {
        $offered = app(OpenSlots::class)->forBuyer($this->buyer)->mapWithKeys(fn ($match) => [
            $match->listing->id => array_map(
                fn ($slot) => $slot->startsAt->setTimezone($match->listing->timezone->value)->format('H:i'),
                $match->slots,
            ),
        ])->all();

        $this->assertSame($expected, $offered);
    }
}
