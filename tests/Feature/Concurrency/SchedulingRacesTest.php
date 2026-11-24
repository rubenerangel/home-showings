<?php

namespace Tests\Feature\Concurrency;

use App\Enums\ShowingStatus;
use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/** Criteria 9, 11, 12, 13 and 15, and the parts of D21 and R6 that only show up between processes. */
class SchedulingRacesTest extends ConcurrencyTestCase
{
    // Criterion 9

    public function test_ten_buyers_on_one_slot_produce_one_showing_and_nine_refusals(): void
    {
        $window = $this->window('10:00', '12:00');

        $results = $this->race(...array_map(fn () => $this->book($this->buyer(), $window, '10:00'), range(1, 10)));

        $this->assertEquals(['ok' => 1, 'refused: just_booked' => 9], array_count_values($results));
        $this->assertSame(1, $this->confirmed());
    }

    // Criterion 11

    #[DataProvider('rounds')]
    public function test_one_buyer_cannot_book_overlapping_slots_in_two_homes(int $round): void
    {
        $buyer = $this->buyer();

        $results = $this->race(
            $this->book($buyer, $this->window('10:00', '11:00'), '10:00'),
            $this->book($buyer, $this->window('10:15', '11:15'), '10:15'),
        );

        $this->assertEquals(['ok' => 1, 'refused: overlaps' => 1], array_count_values($results));
        $this->assertSame(1, $this->confirmed());
    }

    #[DataProvider('rounds')]
    public function test_one_buyer_cannot_book_two_slots_of_the_same_home(int $round): void
    {
        $buyer = $this->buyer();
        $listing = Listing::factory()->create();

        $results = $this->race(
            $this->book($buyer, $this->window('10:00', '11:00', $listing), '10:00'),
            $this->book($buyer, $this->window('14:00', '15:00', $listing), '14:00'),
        );

        $this->assertEquals(['ok' => 1, 'refused: already_has_showing' => 1], array_count_values($results));
        $this->assertSame(1, $this->confirmed());
    }

    // Criterion 12

    public function test_booking_first_makes_the_closing_fail(): void
    {
        $window = $this->window('10:00', '11:00');

        $results = $this->queue(
            fn () => ShowingWindow::whereKey($window->id)->lockForUpdate()->get(),
            $this->book($this->buyer(), $window, '10:00'),
            ['close', $window->id],
        );

        $this->assertSame(['ok', 'refused: window_has_showings'], $results);
        $this->assertNull($window->refresh()->closed_at);
        $this->assertSame(1, $this->confirmed());
    }

    public function test_closing_first_makes_the_booking_fail(): void
    {
        $window = $this->window('10:00', '11:00');

        $results = $this->queue(
            fn () => ShowingWindow::whereKey($window->id)->lockForUpdate()->get(),
            ['close', $window->id],
            $this->book($this->buyer(), $window, '10:00'),
        );

        $this->assertSame(['ok', 'refused: not_available'], $results);
        $this->assertNotNull($window->refresh()->closed_at);
        $this->assertSame(0, $this->confirmed());
    }

    #[DataProvider('rounds')]
    public function test_no_closed_window_keeps_a_showing_that_ends_after_the_closing(int $round): void
    {
        $window = $this->window('10:00', '11:00');

        $results = $this->race($this->book($this->buyer(), $window, '10:00'), ['close', $window->id]);

        $this->assertContains($results, [['ok', 'refused: window_has_showings'], ['refused: not_available', 'ok']]);
        $this->assertSame(0, DB::table('showings')
            ->join('showing_windows', 'showing_windows.id', '=', 'showing_window_id')
            ->where('status', 'confirmed')
            ->whereColumn('ends_at', '>', 'closed_at')
            ->count());
    }

    // Criterion 13

    #[DataProvider('rounds')]
    public function test_both_sides_cancelling_at_once_leave_one_final_status(int $round): void
    {
        $window = $this->window('10:00', '11:00');
        $showing = Showing::factory()->for($window, 'window')->for($this->buyer(), 'buyer')->create([
            'starts_at' => $this->instant('10:00'),
            'ends_at' => $this->instant('10:30'),
        ]);

        $results = $this->race(['cancel', $showing->id, 'buyer'], ['cancel', $showing->id, 'seller']);

        $this->assertEquals(['ok' => 1, 'refused: showing_cancelled' => 1], array_count_values($results));
        $this->assertSame(
            $results[0] === 'ok' ? ShowingStatus::CancelledByBuyer : ShowingStatus::CancelledBySeller,
            $showing->refresh()->status,
        );
    }

    // Criterion 15

    #[DataProvider('rounds')]
    public function test_two_overlapping_windows_at_once_leave_one(int $round): void
    {
        $listing = Listing::factory()->create();

        $results = $this->race(
            ['open', $listing->id, "{$this->date()} 10:00", "{$this->date()} 11:00"],
            ['open', $listing->id, "{$this->date()} 10:30", "{$this->date()} 11:30"],
        );

        $this->assertCount(1, preg_grep('/^ok$/', $results));
        $this->assertCount(1, preg_grep('/^refused: window_overlaps This overlaps your window on \w+ \d+, 10:[03]0 AM to 11:[03]0 AM\.$/', $results));
        $this->assertSame(1, $listing->windows()->count());
    }

    // D21: the constraint catches a writer that forgot the locks, and it is still a business refusal

    public function test_a_conflict_found_only_by_the_constraint_is_a_refusal_and_not_an_error(): void
    {
        $listing = Listing::factory()->create();
        $window = $this->window('10:00', '11:00', $listing);
        $otherWindow = $this->window('14:00', '15:00', $listing);

        $results = $this->queue(
            fn () => Showing::factory()->for($otherWindow, 'window')->create([
                'starts_at' => $this->instant('10:00'),
                'ends_at' => $this->instant('10:30'),
            ]),
            $this->book($this->buyer(), $window, '10:00'),
        );

        $this->assertSame(['refused: just_booked'], $results);
        $this->assertSame(1, $this->confirmed());
    }

    // R6: the clock is read after the locks

    public function test_a_slot_that_starts_while_the_booking_waits_is_refused(): void
    {
        $zone = CarbonImmutable::now('America/Los_Angeles')->hour < 22 ? 'America/Los_Angeles' : 'America/New_York';
        $startsAt = CarbonImmutable::now($zone)->addSeconds(8)->startOfSecond();
        $listing = Listing::factory()->create(['timezone' => $zone, 'min_notice_hours' => 0]);
        $window = ShowingWindow::factory()->for($listing)->create([
            'local_starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'local_ends_at' => $startsAt->addMinutes(30)->format('Y-m-d H:i:s'),
        ]);
        $buyer = User::factory()->buyer()->create();
        AvailabilityRange::factory()->for($buyer, 'buyer')->between($startsAt->format('Y-m-d'), '00:00', '23:59')->create();
        $job = ['book', $buyer->id, $window->id, $startsAt->utc()->toIso8601ZuluString()];

        $lock = fn () => ShowingWindow::whereKey($window->id)->lockForUpdate()->get();

        // Without the wait the same booking is accepted, so the refusal comes from the clock.
        $this->assertSame(['ok'], $this->queue($lock, $job));
        Showing::query()->delete();

        $results = $this->queueUntil($startsAt, $lock, $job);

        $this->assertSame(['refused: notice'], $results);
        $this->assertSame(0, $this->confirmed());
    }

    /** @return list<array{int}> */
    public static function rounds(): array
    {
        return [[1], [2], [3]];
    }
}
