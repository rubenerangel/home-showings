<?php

namespace Tests\Feature;

use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    private const CHECK_VIOLATION = '23514';

    /** @param array<string, mixed> $attributes */
    #[DataProvider('invalidListings')]
    public function test_the_database_rejects_invalid_listings(array $attributes): void
    {
        $this->assertSqlState(self::CHECK_VIOLATION, fn () => Listing::factory()->create($attributes));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidListings(): array
    {
        return [
            'price' => [['price' => 0]],
            'bedrooms' => [['bedrooms' => -1]],
            'bathrooms below half' => [['bathrooms' => 0]],
            'bathrooms step' => [['bathrooms' => 1.3]],
            'duration' => [['showing_duration_minutes' => 20]],
            'notice' => [['min_notice_hours' => 1]],
        ];
    }

    public function test_the_database_rejects_an_unknown_time_zone(): void
    {
        $listing = Listing::factory()->create();

        $this->assertSqlState(self::CHECK_VIOLATION, fn () => DB::table('listings')
            ->where('id', $listing->id)->update(['timezone' => 'Europe/Madrid']));
    }

    public function test_windows_and_ranges_stay_within_one_date_and_end_after_they_start(): void
    {
        foreach ([ShowingWindow::class, AvailabilityRange::class] as $model) {
            foreach ([['2027-03-10 10:00', '2027-03-10 10:00'], ['2027-03-10 23:00', '2027-03-11 00:00']] as [$start, $end]) {
                $this->assertSqlState(self::CHECK_VIOLATION, fn () => $model::factory()
                    ->create(['local_starts_at' => $start, 'local_ends_at' => $end]));
            }
        }
    }

    public function test_an_instant_in_any_zone_is_stored_as_the_same_instant(): void
    {
        $showing = Showing::factory()->create([
            'starts_at' => CarbonImmutable::parse('2027-03-13 10:00', 'America/Denver'),
            'ends_at' => CarbonImmutable::parse('2027-03-13 10:30', 'America/Denver'),
        ]);

        $this->assertSame('2027-03-13T17:00:00+00:00', $showing->refresh()->starts_at->toIso8601String());
    }

    private function assertSqlState(string $state, callable $write): void
    {
        try {
            DB::transaction($write);
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->getCode());

            return;
        }

        $this->fail('The database accepted the row.');
    }
}
