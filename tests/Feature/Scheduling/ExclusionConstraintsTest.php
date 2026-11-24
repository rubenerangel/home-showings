<?php

namespace Tests\Feature\Scheduling;

use App\Enums\ShowingStatus;
use App\Models\Listing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Criterion 10: the last line of defense, without the application. */
class ExclusionConstraintsTest extends TestCase
{
    use SchedulingScenario;

    public function test_overlapping_showings_of_a_listing_are_rejected_by_the_database(): void
    {
        $this->showing('10:00', '10:30');
        $other = $this->window('14:00', '15:00');

        $this->assertExclusionViolation('showings_listing_no_overlap', fn () => $this->showing('10:29', '10:59', window: $other));
        $this->showing('10:30', '11:00', window: $other);
    }

    public function test_overlapping_showings_of_a_buyer_are_rejected_by_the_database(): void
    {
        $elsewhere = $this->window('10:00', '12:00', Listing::factory()->create());
        $this->showing('10:00', '10:30', $this->buyer);

        $this->assertExclusionViolation('showings_buyer_no_overlap', fn () => $this->showing('10:29', '10:59', $this->buyer, $elsewhere));
        $this->showing('10:30', '11:00', $this->buyer, $elsewhere);
    }

    public function test_cancelled_showings_do_not_count(): void
    {
        $this->showing('10:00', '10:30', $this->buyer, status: ShowingStatus::CancelledByBuyer);
        $this->showing('10:00', '10:30', $this->buyer, status: ShowingStatus::CancelledBySeller);

        $this->assertTrue($this->showing('10:00', '10:30', $this->buyer)->exists);
    }

    public function test_overlapping_open_windows_are_rejected_by_the_database(): void
    {
        $this->assertExclusionViolation('showing_windows_no_overlap', fn () => $this->window('11:59', '13:00'));
        $this->window('12:00', '13:00');
        $this->window('10:00', '12:00', attributes: ['closed_at' => now()]);
    }

    private function assertExclusionViolation(string $constraint, callable $write): void
    {
        try {
            DB::transaction($write);
        } catch (QueryException $exception) {
            $this->assertSame('23P01', $exception->getCode());
            $this->assertStringContainsString($constraint, $exception->getMessage());

            return;
        }

        $this->fail('The database accepted an overlap.');
    }
}
