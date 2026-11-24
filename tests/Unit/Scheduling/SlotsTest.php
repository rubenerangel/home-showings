<?php

namespace Tests\Unit\Scheduling;

use App\Scheduling\Interval;
use App\Scheduling\Slots;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Criterion 1, rule R1. */
class SlotsTest extends TestCase
{
    /** @param list<string> $starts */
    #[DataProvider('windows')]
    public function test_slots_are_anchored_at_the_window_start(string $start, string $end, int $minutes, array $starts, int $unused): void
    {
        $slots = new Slots(Interval::parse("2027-03-10 $start", "2027-03-10 $end"), $minutes);

        $this->assertSame($starts, array_map(fn (Interval $slot) => $slot->start->format('H:i'), $slots->all()));
        $this->assertSame($unused, $slots->unusedMinutes());
        foreach ($slots->all() as $slot) {
            $this->assertSame($minutes, (int) $slot->start->diffInMinutes($slot->end));
        }
    }

    /** @return array<string, array{string, string, int, list<string>, int}> */
    public static function windows(): array
    {
        return [
            'leftover' => ['10:00', '11:10', 30, ['10:00', '10:30'], 10],
            'exact fit' => ['10:00', '11:00', 30, ['10:00', '10:30'], 0],
            'one slot exactly' => ['10:00', '10:45', 45, ['10:00'], 0],
            'one minute short of the second slot' => ['10:00', '10:59', 30, ['10:00'], 29],
            'shorter than one slot' => ['10:00', '10:45', 60, [], 45],
            'start off the half hour' => ['10:10', '11:10', 30, ['10:10', '10:40'], 0],
        ];
    }
}
