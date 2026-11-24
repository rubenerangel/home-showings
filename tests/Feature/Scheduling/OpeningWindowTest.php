<?php

namespace Tests\Feature\Scheduling;

use App\Enums\HouseTimezone;
use App\Models\Listing;
use App\Models\ShowingWindow;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Criteria 1, 15 and 16, rules R1, R11, R12 and D13, D15. The scenario has an open window from 10:00 to 12:00. */
class OpeningWindowTest extends TestCase
{
    use SchedulingScenario;

    public function test_a_window_copies_the_duration_of_the_listing(): void
    {
        $this->listing->update(['showing_duration_minutes' => 45]);

        $this->open('2027-03-11', '09:00', '11:00')->assertRedirect()->assertSessionHasNoErrors();
        $this->listing->update(['showing_duration_minutes' => 15]);

        $window = ShowingWindow::latest('id')->firstOrFail();
        $this->assertSame('2027-03-11 09:00', $window->local_starts_at->format('Y-m-d H:i'));
        $this->assertSame('2027-03-11 11:00', $window->local_ends_at->format('Y-m-d H:i'));
        $this->assertSame(45, $window->duration_minutes);
        $this->assertSame(30, $this->window->refresh()->duration_minutes);
        $this->assertNull($window->closed_at);
    }

    // Criterion 1

    public function test_a_window_shorter_than_one_showing_is_rejected(): void
    {
        $this->listing->update(['showing_duration_minutes' => 60]);

        $this->assertRefused('A showing takes 60 minutes, so nothing fits in this window.', '2027-03-11', '10:00', '10:45');
        $this->open('2027-03-11', '10:00', '11:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    // Criterion 15

    #[DataProvider('overlaps')]
    public function test_an_overlapping_window_is_rejected_naming_the_conflict(string $start, string $end): void
    {
        $this->assertRefused('This overlaps your window on Mar 10, 10:00 AM to 12:00 PM.', '2027-03-10', $start, $end);
    }

    /** @return array<string, array{string, string}> */
    public static function overlaps(): array
    {
        return [
            'the same' => ['10:00', '12:00'],
            'across the start' => ['09:00', '10:30'],
            'across the end' => ['11:30', '13:00'],
            'inside' => ['10:30', '11:00'],
            'around' => ['09:00', '13:00'],
        ];
    }

    public function test_adjacent_windows_are_accepted(): void
    {
        $this->open('2027-03-10', '09:00', '10:00')->assertRedirect()->assertSessionHasNoErrors();
        $this->open('2027-03-10', '12:00', '13:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_closed_windows_and_other_listings_do_not_conflict(): void
    {
        $this->window('14:00', '15:00', Listing::factory()->create());
        $this->open('2027-03-10', '14:00', '15:00')->assertRedirect()->assertSessionHasNoErrors();

        $this->window->update(['closed_at' => now()]);
        $this->open('2027-03-10', '10:00', '12:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    // Criterion 16, R11

    #[DataProvider('clockChanges')]
    public function test_on_a_clock_change_a_window_starts_at_three_or_later(HouseTimezone $timezone, string $date, bool $changes): void
    {
        $this->listing->update(['timezone' => $timezone]);
        $message = 'The clock changes on this date, so windows start at 3:00 AM or later.';

        if ($changes) {
            $this->assertRefused($message, $date, '02:59', '04:00');
            $this->assertRefused($message, $date, '00:30', '01:30');
            $this->open($date, '03:00', '04:00')->assertRedirect()->assertSessionHasNoErrors();
        } else {
            $this->open($date, '02:59', '04:00')->assertRedirect()->assertSessionHasNoErrors();
        }
    }

    /** @return array<string, array{HouseTimezone, string, bool}> */
    public static function clockChanges(): array
    {
        return [
            'Denver in spring' => [HouseTimezone::Mountain, '2027-03-14', true],
            'Denver in autumn' => [HouseTimezone::Mountain, '2027-11-07', true],
            'New York in spring' => [HouseTimezone::Eastern, '2027-03-14', true],
            'Denver the day before' => [HouseTimezone::Mountain, '2027-03-13', false],
            'Denver the day after' => [HouseTimezone::Mountain, '2027-03-15', false],
            'Phoenix never changes' => [HouseTimezone::Arizona, '2027-03-14', false],
        ];
    }

    // Criterion 16, R12

    public function test_a_window_starts_in_the_future_on_the_clock_of_the_house(): void
    {
        $this->at('2027-03-10 14:00:00');
        $this->assertRefused('A window starts in the future.', '2027-03-10', '14:00', '15:00');

        $this->at('2027-03-10 13:59:59');
        $this->open('2027-03-10', '14:00', '15:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    /** @param array<string, string> $input */
    #[DataProvider('invalidInput')]
    public function test_malformed_windows_are_rejected(array $input, string $field): void
    {
        $count = ShowingWindow::count();

        $this->open(...['date' => '2027-03-11', 'start' => '10:00', 'end' => '11:00', ...$input])->assertSessionHasErrors($field);

        $this->assertSame($count, ShowingWindow::count());
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function invalidInput(): array
    {
        return [
            'impossible date' => [['date' => '2027-02-30'], 'date'],
            'ends at midnight' => [['end' => '24:00'], 'end'],
            'ends at the next midnight' => [['end' => '00:00'], 'end'],
            'empty' => [['end' => '10:00'], 'end'],
            'reversed' => [['end' => '09:00'], 'end'],
            'no time' => [['start' => ''], 'start'],
        ];
    }

    private function open(string $date, string $start, string $end): TestResponse
    {
        return $this->actingAs(User::findOrFail($this->seller->id))
            ->post("/seller/listings/{$this->listing->id}/windows", compact('date', 'start', 'end'));
    }

    private function assertRefused(string $message, string $date, string $start, string $end): void
    {
        $count = ShowingWindow::count();

        $this->open($date, $start, $end)->assertRedirect()->assertSessionHasErrors(['refusal' => $message]);

        $this->assertSame($count, ShowingWindow::count());
    }
}
