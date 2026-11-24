<?php

namespace Tests\Unit\Scheduling;

use App\Scheduling\Availability;
use App\Scheduling\Interval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Criterion 2, rules R2 and R3. */
class AvailabilityTest extends TestCase
{
    /**
     * @param  list<array{string, string}>  $ranges
     * @param  list<array{string, string}>  $merged
     */
    #[DataProvider('ranges')]
    public function test_ranges_that_touch_or_overlap_are_merged(array $ranges, array $merged): void
    {
        $result = array_map(
            fn (Interval $range) => [$range->start->format('H:i'), $range->end->format('H:i')],
            $this->availability($ranges)->ranges,
        );

        $this->assertSame($merged, $result);
    }

    /** @return array<string, array{list<array{string, string}>, list<array{string, string}>}> */
    public static function ranges(): array
    {
        return [
            'none' => [[], []],
            'touching' => [[['10:00', '10:30'], ['10:30', '11:00']], [['10:00', '11:00']]],
            'overlapping' => [[['10:00', '10:45'], ['10:30', '11:00']], [['10:00', '11:00']]],
            'one inside another' => [[['10:00', '12:00'], ['10:30', '11:00']], [['10:00', '12:00']]],
            'one minute apart' => [[['10:00', '10:30'], ['10:31', '11:00']], [['10:00', '10:30'], ['10:31', '11:00']]],
            'out of order' => [[['10:30', '11:00'], ['10:00', '10:30'], ['14:00', '15:00']], [['10:00', '11:00'], ['14:00', '15:00']]],
        ];
    }

    /** @param list<array{string, string}> $ranges */
    #[DataProvider('slots')]
    public function test_a_slot_fits_only_when_it_is_fully_inside_a_merged_range(array $ranges, string $start, string $end, bool $fits): void
    {
        $this->assertSame($fits, $this->availability($ranges)->contains(Interval::parse("2027-03-10 $start", "2027-03-10 $end")));
    }

    /** @return array<string, array{list<array{string, string}>, string, string, bool}> */
    public static function slots(): array
    {
        return [
            'across two touching ranges' => [[['10:00', '10:30'], ['10:30', '11:00']], '10:15', '10:45', true],
            'across a gap' => [[['10:00', '10:30'], ['10:31', '11:00']], '10:15', '10:45', false],
            'both boundaries equal' => [[['10:00', '10:30']], '10:00', '10:30', true],
            'starts one minute early' => [[['10:01', '10:30']], '10:00', '10:30', false],
            'ends one minute late' => [[['10:00', '10:29']], '10:00', '10:30', false],
            'no ranges' => [[], '10:00', '10:30', false],
        ];
    }

    public function test_the_same_hours_on_another_date_do_not_fit(): void
    {
        $this->assertFalse($this->availability([['10:00', '12:00']])->contains(Interval::parse('2027-03-11 10:00', '2027-03-11 10:30')));
    }

    /** @param list<array{string, string}> $ranges */
    private function availability(array $ranges): Availability
    {
        return new Availability(array_map(
            fn (array $range) => Interval::parse("2027-03-10 $range[0]", "2027-03-10 $range[1]"),
            $ranges,
        ));
    }
}
