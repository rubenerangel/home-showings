<?php

namespace Database\Seeders;

use App\Enums\HouseTimezone;
use App\Enums\Role;
use App\Enums\Room;
use App\Listings\ListingPhotos;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Runs on every start, without Faker: the runtime image has no development dependencies. */
class DatabaseSeeder extends Seeder
{
    /** Matches are promised for 14 days. The rest is margin for notice and for bookings. */
    private const DAYS = 21;

    private CarbonImmutable $today;

    public function run(): void
    {
        // Users are only created here, so their presence means that this already completed.
        if (User::query()->exists()) {
            return;
        }

        // The Pacific is the last zone to change date: from tomorrow there, 9:00 is in the future everywhere.
        $this->today = CarbonImmutable::today(HouseTimezone::Pacific->value);

        DB::transaction(function () {
            $this->homes();
            $this->buyers();
        });
    }

    private function homes(): void
    {
        $grace = User::create(['name' => 'Grace Miller', 'role' => Role::Seller]);
        $daniel = User::create(['name' => 'Daniel Ortiz', 'role' => Role::Seller]);
        $priya = User::create(['name' => 'Priya Shah', 'role' => Role::Seller]);

        $maple = $this->home($grace, HouseTimezone::Eastern, [
            'address' => '48 Maple Ave', 'city' => 'Albany', 'state' => 'NY',
            'price' => 465_000, 'bedrooms' => 4, 'bathrooms' => 2.5,
            'description' => 'Brick ranch on a wide lot, with a renovated kitchen and a bright living room.',
            'showing_duration_minutes' => 30, 'min_notice_hours' => 2,
        ], 'maple', [Room::Exterior, Room::LivingRoom, Room::Kitchen, Room::Bedroom, Room::Bathroom]);
        $this->windows($maple, 1, 1, '10:00', '12:00');
        $this->windows($maple, 1, 1, '17:00', '19:00');

        $lake = $this->home($grace, HouseTimezone::Central, [
            'address' => '210 Lake St', 'city' => 'Madison', 'state' => 'WI',
            'price' => 389_000, 'bedrooms' => 3, 'bathrooms' => 1.5,
            'description' => 'White house with a garden, ten minutes from the lake.',
            'showing_duration_minutes' => 45, 'min_notice_hours' => 12,
        ], 'lake', [Room::Exterior, Room::LivingRoom, Room::Kitchen]);
        $this->windows($lake, 2, 2, '10:00', '13:00');

        $aspen = $this->home($daniel, HouseTimezone::Mountain, [
            'address' => '1725 Aspen Ct', 'city' => 'Denver', 'state' => 'CO',
            'price' => 720_000, 'bedrooms' => 5, 'bathrooms' => 3,
            'description' => 'Historic red brick home with a wraparound porch.',
            'showing_duration_minutes' => 30, 'min_notice_hours' => 2,
        ], 'aspen', [Room::Exterior, Room::Kitchen, Room::Bathroom]);
        $this->windows($aspen, 1, 1, '10:00', '11:10');

        $saguaro = $this->home($daniel, HouseTimezone::Arizona, [
            'address' => '902 Saguaro Dr', 'city' => 'Phoenix', 'state' => 'AZ',
            'price' => 298_000, 'bedrooms' => 0, 'bathrooms' => 1,
            'description' => null,
            'showing_duration_minutes' => 15, 'min_notice_hours' => 0,
        ], 'saguaro', []);
        $this->windows($saguaro, 1, 3, '18:00', '20:00');

        $cedar = $this->home($priya, HouseTimezone::Pacific, [
            'address' => '36 Cedar Ln', 'city' => 'Portland', 'state' => 'OR',
            'price' => 540_000, 'bedrooms' => 2, 'bathrooms' => 1,
            'description' => 'Cabin among the trees, with a deck for the evenings.',
            'showing_duration_minutes' => 60, 'min_notice_hours' => 24,
        ], 'cedar', [Room::Exterior]);
        $this->windows($cedar, 3, 3, '9:00', '12:00');
    }

    private function buyers(): void
    {
        $emma = User::create(['name' => 'Emma Clark', 'role' => Role::Buyer]);
        $this->availability($emma, 1, 1, '9:30', '13:00');

        $noah = User::create(['name' => 'Noah Kim', 'role' => Role::Buyer]);
        $this->availability($noah, 1, 2, '17:00', '20:00');

        // No availability yet, to see what a new buyer sees.
        User::create(['name' => 'Olivia Reed', 'role' => Role::Buyer]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<Room>  $rooms  the files are named after the home and the room
     */
    private function home(User $seller, HouseTimezone $timezone, array $attributes, string $name, array $rooms): Listing
    {
        $listing = $seller->listings()->create([...$attributes, 'timezone' => $timezone]);

        foreach ($rooms as $index => $room) {
            $file = $name.'-'.str_replace('_', '-', $room->value).'.webp';
            // Fixed names, so a first start that failed is completed over the same files.
            $path = Storage::disk(ListingPhotos::DISK)->putFileAs('seed', new File(__DIR__."/photos/$file"), $file);
            throw_if($path === false, new RuntimeException("The photo $file could not be stored."));
            $listing->photos()->create(['path' => $path, 'room' => $room, 'position' => $index + 1]);
        }

        return $listing;
    }

    private function windows(Listing $listing, int $first, int $every, string $start, string $end): void
    {
        $listing->windows()->createMany(array_map(
            fn (array $times) => [...$times, 'duration_minutes' => $listing->showing_duration_minutes],
            $this->times($first, $every, $start, $end),
        ));
    }

    private function availability(User $buyer, int $first, int $every, string $start, string $end): void
    {
        $buyer->availabilityRanges()->createMany($this->times($first, $every, $start, $end));
    }

    /** @return list<array{local_starts_at: string, local_ends_at: string}> */
    private function times(int $first, int $every, string $start, string $end): array
    {
        return array_map(function (int $day) use ($start, $end) {
            $date = $this->today->addDays($day)->format('Y-m-d');

            return ['local_starts_at' => "$date $start:00", 'local_ends_at' => "$date $end:00"];
        }, range($first, self::DAYS, $every));
    }
}
