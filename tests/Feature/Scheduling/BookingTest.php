<?php

namespace Tests\Feature\Scheduling;

use App\Enums\HouseTimezone;
use App\Enums\ShowingStatus;
use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Criteria 7 and 8, rules R6, R8 and R9. */
class BookingTest extends TestCase
{
    use SchedulingScenario;

    private const NOT_AVAILABLE = 'This time is no longer available.';

    private const NOTICE = 'This time is too soon for the notice this home asks for.';

    private const OUTSIDE = 'This time is outside your availability.';

    private const ALREADY = 'You already have a showing at this home.';

    private const JUST_BOOKED = 'Someone else just booked this time.';

    private const OVERLAPS = 'You have another showing at this time.';

    // Criterion 7

    public function test_booking_creates_one_confirmed_showing(): void
    {
        $this->book('10:30')->assertRedirect()->assertSessionHasNoErrors();

        $showing = Showing::sole();
        $this->assertSame(
            [$this->window->id, $this->listing->id, $this->buyer->id, ShowingStatus::Confirmed, null],
            [$showing->showing_window_id, $showing->listing_id, $showing->buyer_id, $showing->status, $showing->cancelled_at],
        );
        $this->assertSame('2027-03-10T17:30:00Z', $showing->starts_at->toIso8601ZuluString());
        $this->assertSame('2027-03-10T18:00:00Z', $showing->ends_at->toIso8601ZuluString());
    }

    #[DataProvider('instants')]
    public function test_the_instant_is_the_house_time_on_that_date(HouseTimezone $timezone, string $date, string $startsAt, string $endsAt): void
    {
        $listing = Listing::factory()->in($timezone)->create();
        $window = ShowingWindow::factory()->for($listing)->between($date, '10:00', '10:30')->create();
        AvailabilityRange::factory()->for($this->buyer, 'buyer')->between($date, '10:00', '10:30')->create();

        $this->request($window, $startsAt)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($startsAt, Showing::sole()->starts_at->toIso8601ZuluString());
        $this->assertSame($endsAt, Showing::sole()->ends_at->toIso8601ZuluString());
    }

    /** @return array<string, array{HouseTimezone, string, string, string}> */
    public static function instants(): array
    {
        return [
            'Denver before the change' => [HouseTimezone::Mountain, '2027-03-13', '2027-03-13T17:00:00Z', '2027-03-13T17:30:00Z'],
            'Denver after the change' => [HouseTimezone::Mountain, '2027-03-15', '2027-03-15T16:00:00Z', '2027-03-15T16:30:00Z'],
            'Phoenix before' => [HouseTimezone::Arizona, '2027-03-13', '2027-03-13T17:00:00Z', '2027-03-13T17:30:00Z'],
            'Phoenix after' => [HouseTimezone::Arizona, '2027-03-15', '2027-03-15T17:00:00Z', '2027-03-15T17:30:00Z'],
        ];
    }

    public function test_the_end_comes_from_the_window_and_not_from_the_client_or_the_listing(): void
    {
        $this->listing->update(['showing_duration_minutes' => 60]);

        $this->book('10:00', ['ends_at' => '2027-03-10T20:00:00Z'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('2027-03-10T17:30:00Z', Showing::sole()->ends_at->toIso8601ZuluString());
    }

    public function test_adjacent_showings_are_accepted(): void
    {
        $elsewhere = $this->window('10:30', '11:00', Listing::factory()->create());
        $this->showing('10:30', '11:00', $this->buyer, $elsewhere);
        $this->showing('10:30', '11:00');

        $this->book('10:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_the_home_can_be_booked_again_once_the_first_showing_ends(): void
    {
        $this->showing('10:00', '10:30', $this->buyer);
        $this->listing->update(['min_notice_hours' => 0]);

        $this->at('2027-03-10 10:29:59');
        $this->assertRefused(self::ALREADY, '11:00');

        $this->at('2027-03-10 10:30:00');
        $this->book('11:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_a_slot_cancelled_by_the_buyer_can_be_booked_again(): void
    {
        $this->showing('10:00', '10:30', status: ShowingStatus::CancelledByBuyer);

        $this->book('10:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_a_slot_cancelled_by_the_seller_opens_again_in_a_new_window(): void
    {
        $this->showing('10:00', '10:30', status: ShowingStatus::CancelledBySeller);
        $this->window->update(['closed_at' => now()]);
        $this->window = $this->window('10:00', '12:00');

        $this->book('10:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    // Criterion 8: not available

    public function test_a_closed_window_is_refused(): void
    {
        $this->window->update(['closed_at' => now()]);

        $this->assertRefused(self::NOT_AVAILABLE, '10:00');
    }

    #[DataProvider('timesThatAreNotSlots')]
    public function test_a_time_that_is_not_a_slot_of_the_window_is_refused(string $time): void
    {
        $this->assertRefused(self::NOT_AVAILABLE, $time);
    }

    /** @return array<string, array{string}> */
    public static function timesThatAreNotSlots(): array
    {
        return ['off the grid' => ['10:15'], 'before the window' => ['09:30'], 'at its end' => ['12:00']];
    }

    public function test_a_slot_cancelled_by_the_seller_is_refused(): void
    {
        $this->showing('10:00', '10:30', status: ShowingStatus::CancelledBySeller);

        $this->assertRefused(self::NOT_AVAILABLE, '10:00');
    }

    // Criterion 8: notice

    public function test_notice_is_checked_on_both_sides_of_the_limit(): void
    {
        $this->at('2027-03-10 08:00:01');
        $this->assertRefused(self::NOTICE, '10:00');

        $this->at('2027-03-10 08:00:00');
        $this->book('10:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_a_started_slot_is_refused_even_without_notice(): void
    {
        $this->listing->update(['min_notice_hours' => 0]);

        $this->at('2027-03-10 10:00:00');
        $this->assertRefused(self::NOTICE, '10:00');

        $this->at('2027-03-10 09:59:59');
        $this->book('10:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    // Criterion 8: the rest

    public function test_a_slot_outside_the_availability_is_refused(): void
    {
        $this->buyer->availabilityRanges()->delete();
        $this->available('10:01', '12:00');

        $this->assertRefused(self::OUTSIDE, '10:00');
    }

    public function test_a_second_active_showing_at_the_home_is_refused(): void
    {
        $this->showing('10:00', '10:30', $this->buyer);

        $this->assertRefused(self::ALREADY, '11:00');
    }

    public function test_a_slot_booked_by_someone_else_is_refused(): void
    {
        $this->showing('10:00', '10:30');

        $this->assertRefused(self::JUST_BOOKED, '10:00');
    }

    public function test_a_slot_that_overlaps_another_showing_of_the_buyer_is_refused(): void
    {
        $elsewhere = $this->window('10:15', '10:45', Listing::factory()->create());
        $this->showing('10:15', '10:45', $this->buyer, $elsewhere);

        $this->assertRefused(self::OVERLAPS, '10:00');
    }

    // Criterion 8: the order of the reasons

    public function test_not_available_comes_before_notice(): void
    {
        $this->window->update(['closed_at' => now()]);
        $this->at('2027-03-10 09:00:00');

        $this->assertRefused(self::NOT_AVAILABLE, '10:00');
    }

    public function test_notice_comes_before_availability(): void
    {
        $this->buyer->availabilityRanges()->delete();
        $this->at('2027-03-10 09:00:00');

        $this->assertRefused(self::NOTICE, '10:00');
    }

    public function test_availability_comes_before_an_active_showing(): void
    {
        $this->showing('11:00', '11:30', $this->buyer);
        $this->buyer->availabilityRanges()->delete();

        $this->assertRefused(self::OUTSIDE, '10:00');
    }

    public function test_an_active_showing_comes_before_just_booked(): void
    {
        $this->showing('11:00', '11:30', $this->buyer);
        $this->showing('10:00', '10:30');

        $this->assertRefused(self::ALREADY, '10:00');
    }

    public function test_just_booked_comes_before_an_overlap(): void
    {
        $elsewhere = $this->window('10:00', '10:30', Listing::factory()->create());
        $this->showing('10:00', '10:30', $this->buyer, $elsewhere);
        $this->showing('10:00', '10:30');

        $this->assertRefused(self::JUST_BOOKED, '10:00');
    }

    public function test_booking_the_slot_one_already_holds_says_so(): void
    {
        $this->showing('10:00', '10:30', $this->buyer);

        $this->assertRefused(self::ALREADY, '10:00');
    }

    public function test_malformed_input_is_rejected(): void
    {
        $this->actingAs($this->buyer)
            ->postJson('/buyer/showings', ['window_id' => 'x', 'starts_at' => 'tomorrow'])
            ->assertJsonValidationErrors(['window_id', 'starts_at']);
    }

    public function test_an_unknown_window_is_not_found(): void
    {
        $this->actingAs($this->buyer)
            ->postJson('/buyer/showings', ['window_id' => 999, 'starts_at' => '2027-03-10T17:00:00Z'])
            ->assertNotFound();
    }

    /** @param array<string, mixed> $extra */
    private function book(string $time, array $extra = []): TestResponse
    {
        return $this->request($this->window, $this->instant($time)->toIso8601ZuluString(), $extra);
    }

    /** @param array<string, mixed> $extra */
    private function request(ShowingWindow $window, string $startsAt, array $extra = []): TestResponse
    {
        // A fresh model each time, as in a real request.
        return $this->actingAs(User::findOrFail($this->buyer->id))
            ->post('/buyer/showings', ['window_id' => $window->id, 'starts_at' => $startsAt, ...$extra]);
    }

    private function assertRefused(string $message, string $time): void
    {
        $before = Showing::orderBy('id')->get()->toArray();

        $this->book($time)->assertRedirect()->assertSessionHasErrors(['refusal' => $message]);

        $this->assertSame($before, Showing::orderBy('id')->get()->toArray());
    }
}
