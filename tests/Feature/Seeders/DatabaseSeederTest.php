<?php

namespace Tests\Feature\Seeders;

use App\Enums\HouseTimezone;
use App\Enums\Role;
use App\Enums\Room;
use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\ShowingWindow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

/** Criterion 23. */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = ['users', 'listings', 'listing_photos', 'showing_windows', 'availability_ranges', 'showings'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[DataProvider('firstStarts')]
    public function test_buyers_have_matches_during_fourteen_days_from_any_first_start(string $now): void
    {
        $start = CarbonImmutable::parse($now);
        $this->travelTo(Carbon::instance($start->toDateTime()));
        $this->seed(DatabaseSeeder::class);

        $buyers = User::where('role', Role::Buyer)->with('availabilityRanges')->get();
        $matching = $buyers->filter(fn (User $buyer) => $this->lastBookableInstant($buyer)?->isAfter($start->addDays(14)));
        $this->assertGreaterThanOrEqual(2, $matching->count());
        $this->assertCount(1, $buyers->filter(fn (User $buyer) => $buyer->availabilityRanges->isEmpty()));
    }

    #[DataProvider('firstStarts')]
    public function test_every_window_is_one_a_seller_could_have_created(string $now): void
    {
        $this->travelTo(Carbon::parse($now));
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, ShowingWindow::count());
        foreach (ShowingWindow::with('listing')->get() as $window) {
            $zone = $window->listing->timezone->value;
            $start = CarbonImmutable::parse($window->local_starts_at->format('Y-m-d H:i'), $zone);

            $this->assertTrue($start->isAfter(now()), 'It starts in the future.');
            $this->assertGreaterThanOrEqual('03:00', $window->local_starts_at->format('H:i'));
            $this->assertSame($window->listing->showing_duration_minutes, $window->duration_minutes);
            $this->assertGreaterThanOrEqual($window->duration_minutes, $window->local_starts_at->diffInMinutes($window->local_ends_at));
        }
        $today = now('America/Los_Angeles')->format('Y-m-d');
        foreach (AvailabilityRange::all() as $range) {
            $this->assertGreaterThan($today, $range->local_starts_at->format('Y-m-d'));
        }
    }

    /** @return array<string, array{string}> */
    public static function firstStarts(): array
    {
        return [
            'one minute before midnight in the Pacific' => ['2026-11-23 23:59:00 America/Los_Angeles'],
            'just after midnight in the Pacific' => ['2026-11-24 00:00:00 America/Los_Angeles'],
            'just after midnight in the East' => ['2026-11-24 00:00:00 America/New_York'],
            'midday' => ['2026-11-24 12:00:00 America/Denver'],
            'before the clocks go forward' => ['2027-03-13 23:30:00 America/Los_Angeles'],
            'while the clocks go forward' => ['2027-03-14 02:30:00 America/New_York'],
            'before the clocks go back' => ['2027-11-06 23:30:00 America/Los_Angeles'],
            'end of the year' => ['2026-12-31 23:59:59 America/Los_Angeles'],
            'leap day' => ['2028-02-28 20:00:00 America/Chicago'],
        ];
    }

    public function test_it_covers_both_roles_every_zone_and_photos_from_none_to_five(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(2, User::where('role', Role::Seller)->count());
        $this->assertEqualsCanonicalizing(
            array_column(HouseTimezone::cases(), 'value'),
            Listing::all()->pluck('timezone.value')->unique()->all(),
        );
        $counts = Listing::withCount('photos')->pluck('photos_count');
        $this->assertSame([0, 5], [$counts->min(), $counts->max()]);

        foreach (Listing::has('photos')->with('photos')->get() as $listing) {
            $this->assertSame(Room::Exterior, $listing->photos->first()->room);
        }
        Storage::disk('public')->assertExists(ListingPhoto::pluck('path')->all());
    }

    #[TestWith(['grace@example.com', '/seller'])]
    #[TestWith(['daniel@example.com', '/seller'])]
    #[TestWith(['priya@example.com', '/seller'])]
    #[TestWith(['emma@example.com', '/buyer'])]
    #[TestWith(['noah@example.com', '/buyer'])]
    #[TestWith(['olivia@example.com', '/buyer'])]
    public function test_the_accounts_of_the_readme_can_log_in(string $email, string $area): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/session', ['email' => $email, 'password' => 'maple-street-48'])->assertRedirect($area);

        $this->get($area)->assertOk();
    }

    public function test_a_second_start_changes_nothing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = $this->snapshot();
        $this->assertGreaterThan(0, User::count());

        $this->travel(3)->days();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($before, $this->snapshot());
    }

    public function test_a_second_start_respects_what_people_did(): void
    {
        $this->seed(DatabaseSeeder::class);
        $listing = Listing::has('photos')->firstOrFail();
        $listing->update(['price' => 1]);
        $listing->windows()->update(['closed_at' => now()]);
        $photo = $listing->photos->first();
        Storage::disk('public')->delete($photo->path);
        $photo->delete();
        AvailabilityRange::query()->delete();
        $own = Listing::factory()->for($listing->seller, 'seller')->create();
        $before = $this->snapshot();

        $this->travel(3)->days();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($before, $this->snapshot());
        $this->assertModelExists($own);
        Storage::disk('public')->assertMissing($photo->path);
    }

    public function test_a_first_start_that_fails_leaves_nothing_and_the_next_one_completes(): void
    {
        AvailabilityRange::creating(fn () => throw new RuntimeException('Interrupted'));
        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('The first start did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Interrupted', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('listings', 0);

        AvailabilityRange::flushEventListeners();
        $this->seed(DatabaseSeeder::class);
        $this->assertGreaterThan(0, AvailabilityRange::count());
    }

    /** The latest moment at which a slot of any home could still be booked by this buyer. */
    private function lastBookableInstant(User $buyer): ?CarbonImmutable
    {
        $last = null;
        foreach (ShowingWindow::with('listing')->get() as $window) {
            $slot = $window->local_starts_at;
            while (($end = $slot->addMinutes($window->duration_minutes)) <= $window->local_ends_at) {
                $fits = $buyer->availabilityRanges->contains(
                    fn ($range) => $range->local_starts_at <= $slot && $end <= $range->local_ends_at,
                );
                if ($fits) {
                    $instant = CarbonImmutable::parse($slot->format('Y-m-d H:i'), $window->listing->timezone->value)
                        ->subHours($window->listing->min_notice_hours);
                    $last = $last?->max($instant) ?? $instant;
                }
                $slot = $end;
            }
        }

        return $last;
    }

    private function snapshot(): string
    {
        return collect(self::TABLES)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()])->toJson();
    }
}
