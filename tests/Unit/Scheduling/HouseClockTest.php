<?php

namespace Tests\Unit\Scheduling;

use App\Enums\HouseTimezone;
use App\Scheduling\HouseClock;
use App\Scheduling\Interval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Rule R9: what matching and booking use to turn house time into instants. */
class HouseClockTest extends TestCase
{
    #[DataProvider('instants')]
    public function test_house_time_becomes_the_right_instant(HouseTimezone $timezone, string $local, string $utc): void
    {
        $start = Interval::parse($local, "$local:59")->start;

        $this->assertSame($utc, HouseClock::toInstant($start, $timezone)->toIso8601ZuluString());
    }

    /** @return array<string, array{HouseTimezone, string, string}> */
    public static function instants(): array
    {
        return [
            'Denver before the change' => [HouseTimezone::Mountain, '2027-03-13 10:00', '2027-03-13T17:00:00Z'],
            'Denver after the change' => [HouseTimezone::Mountain, '2027-03-15 10:00', '2027-03-15T16:00:00Z'],
            'Phoenix before' => [HouseTimezone::Arizona, '2027-03-13 10:00', '2027-03-13T17:00:00Z'],
            'Phoenix after' => [HouseTimezone::Arizona, '2027-03-15 10:00', '2027-03-15T17:00:00Z'],
            'New York before' => [HouseTimezone::Eastern, '2027-03-13 10:00', '2027-03-13T15:00:00Z'],
        ];
    }
}
