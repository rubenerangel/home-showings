<?php

namespace Tests\Feature\Api;

use App\Enums\Room;
use App\Enums\ShowingStatus;
use App\Listings\ListingOptions;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\ShowingWindow;
use App\Models\User;
use App\Scheduling\Refusal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criteria 37, 39 and 40, for the seller, and R19. */
class SellerTest extends TestCase
{
    use CallsApi, SchedulingScenario;

    private const INPUT = [
        'address' => '1 Main St', 'city' => 'Austin', 'state' => 'TX', 'price' => 300_000, 'bedrooms' => 2, 'bathrooms' => 1.5,
        'description' => null, 'timezone' => 'America/Chicago', 'showing_duration_minutes' => 45, 'min_notice_hours' => 12,
    ];

    // Listings

    public function test_the_own_listings_with_their_cover(): void
    {
        $photo = $this->photo();
        Listing::factory()->create();

        $this->api('GET', '/seller/listings', as: $this->seller)->assertOk()->assertExactJson(['data' => [[
            ...$this->fields(), 'cover' => ['id' => $photo->id, 'url' => 'http://localhost:8080/storage/front.jpg', 'room' => 'exterior'],
        ]]]);
    }

    public function test_one_listing_with_its_photos_and_locks(): void
    {
        $photo = $this->photo();

        $this->api('GET', "/seller/listings/{$this->listing->id}", as: $this->seller)->assertOk()->assertExactJson(['data' => [
            ...$this->fields(),
            'photos' => [['id' => $photo->id, 'url' => 'http://localhost:8080/storage/front.jpg', 'room' => 'exterior']],
            'locks' => ['location' => false, 'timezone' => true],
        ]]);
    }

    public function test_the_options(): void
    {
        $this->api('GET', '/seller/listing-options', as: $this->seller)->assertOk()->assertExactJson(['data' => ListingOptions::toArray()]);
    }

    public function test_a_listing_is_created_for_the_account_of_the_token(): void
    {
        $answer = $this->api('POST', '/seller/listings', [...self::INPUT, 'seller_id' => User::factory()->seller()->create()->id], $this->seller);

        $listing = $this->seller->listings()->latest('id')->firstOrFail();
        $answer->assertCreated()->assertExactJson(['data' => [
            'id' => $listing->id, ...self::INPUT, 'photos' => [], 'locks' => ['location' => false, 'timezone' => false],
        ]]);
        $this->assertSame('1 Main St', $listing->address);
    }

    public function test_a_listing_is_edited_and_its_locks_hold(): void
    {
        $same = ['timezone' => 'America/Denver', 'address' => $this->listing->address, 'city' => 'Denver', 'state' => 'CO'];
        $uri = "/seller/listings/{$this->listing->id}";

        $this->api('PUT', $uri, self::INPUT, $this->seller)->assertUnprocessable()->assertOnlyJsonValidationErrors('timezone');
        $this->api('PUT', $uri, [...self::INPUT, 'timezone' => 'America/Denver'], $this->seller)->assertOk()->assertJsonPath('data.city', 'Austin');
        $this->listing->refresh()->update(['city' => 'Denver', 'state' => 'CO', 'address' => $same['address']]);

        $this->showing('10:00', '10:30');
        $this->api('PUT', $uri, [...self::INPUT, 'timezone' => 'America/Denver'], $this->seller)
            ->assertUnprocessable()->assertOnlyJsonValidationErrors(['address', 'city', 'state']);
        $this->api('PUT', $uri, [...self::INPUT, ...$same, 'price' => 1], $this->seller)->assertOk();

        $this->assertSame([1, 'Denver'], [$this->listing->refresh()->price, $this->listing->city]);
    }

    public function test_malformed_listings_are_refused(): void
    {
        $this->api('POST', '/seller/listings', ['address' => ['x'], 'price' => 'x', 'timezone' => 5], $this->seller)
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys(self::INPUT));

        $this->assertSame(1, Listing::count());
    }

    // R19

    #[TestWith(['address', "1 Main\0St"])]
    #[TestWith(['city', "Aus\0tin"])]
    #[TestWith(['description', "Nice\0home"])]
    #[TestWith(['address', "1 Main\xffSt"])]
    #[TestWith(['description', "Nice\xffhome"])]
    public function test_text_that_cannot_be_stored_is_refused_on_the_web_too(string $field, string $value): void
    {
        $this->actingAs($this->seller)->post('/seller/listings', [...self::INPUT, $field => $value])->assertSessionHasErrors($field);
        auth()->forgetGuards();
        $this->withToken($this->token($this->seller))->post('/api/v1/seller/listings', [...self::INPUT, $field => $value])
            ->assertUnprocessable()->assertOnlyJsonValidationErrors($field);

        $this->assertSame(1, Listing::count());
    }

    public function test_a_description_keeps_its_lines_and_accents(): void
    {
        $this->api('POST', '/seller/listings', [...self::INPUT, 'description' => "Café\nand\tgarden"], $this->seller)
            ->assertCreated()->assertJsonPath('data.description', "Café\nand\tgarden");
    }

    // Photos

    public function test_photos_are_added_changed_and_removed(): void
    {
        Storage::fake('public');
        $uri = "/seller/listings/{$this->listing->id}/photos";

        $answer = $this->upload(['exterior', 'kitchen'])->assertCreated();
        [$first, $second] = $this->listing->photos()->orderBy('position')->get()->all();
        $answer->assertExactJson(['data' => [
            ['id' => $first->id, 'url' => "http://localhost:8080/storage/$first->path", 'room' => 'exterior'],
            ['id' => $second->id, 'url' => "http://localhost:8080/storage/$second->path", 'room' => 'kitchen'],
        ]]);
        $this->upload(['bedroom'])->assertCreated()->assertJsonCount(3, 'data');

        $this->api('PATCH', "$uri/$first->id", ['room' => 'other'], $this->seller)->assertOk()->assertJsonPath('data.room', 'other');
        $this->assertSame(Room::Other, $first->refresh()->room);
        $this->api('PATCH', "$uri/$first->id", ['room' => 'garage'], $this->seller)->assertUnprocessable();

        $this->api('DELETE', "$uri/$first->id", as: $this->seller)->assertNoContent();
        $this->assertSame(2, $this->listing->photos()->count());
        Storage::disk('public')->assertMissing($first->path);
    }

    public function test_a_sixth_photo_and_a_file_that_is_not_a_photo_are_refused(): void
    {
        Storage::fake('public');
        $this->upload(array_fill(0, 5, 'other'))->assertCreated();

        $this->upload(['other'])->assertUnprocessable()->assertJsonValidationErrors(['photos' => 'A home can have up to 5 photos.']);
        $this->listing->photos()->delete();
        $this->upload(['other'], 'a.gif')->assertUnprocessable()->assertJsonValidationErrors('photos.0.file');

        $this->assertSame(0, $this->listing->photos()->count());
    }

    // Schedule

    public function test_the_schedule_has_the_fields_of_the_web(): void
    {
        $this->showing('10:30', '11:00');
        $answer = $this->api('GET', "/seller/listings/{$this->listing->id}/schedule", as: $this->seller)->assertOk();

        $this->assertSame(['listing', 'today', 'windows', 'showings'], array_keys($answer->json('data')));
        $this->assertCount(1, $answer->json('data.windows'));
        $this->assertCount(1, $answer->json('data.showings'));
        $this->actingAs($this->seller)->get("/seller/listings/{$this->listing->id}/schedule")->assertInertia(fn ($page) => $page
            ->where('today', $answer->json('data.today'))
            ->where('listing', $answer->json('data.listing'))
            ->where('windows', $answer->json('data.windows'))
            ->where('showings', $answer->json('data.showings')));
    }

    public function test_windows_are_opened_and_closed(): void
    {
        $answer = $this->open('13:00', '14:10')->assertCreated();

        $window = ShowingWindow::latest('id')->firstOrFail();
        $expected = [
            'id' => $window->id, 'date' => '2027-03-10', 'start' => '13:00', 'end' => '14:10',
            'showings' => 2, 'unused_minutes' => 10, 'active_showings' => 0, 'past' => false,
        ];
        $answer->assertExactJson(['data' => $expected]);

        $this->close($window)->assertOk()->assertExactJson(['data' => $expected]);
        $this->assertNotNull($window->refresh()->closed_at);
    }

    // Criterion 39

    public function test_every_reason_to_refuse_a_window_is_a_conflict(): void
    {
        $this->assertRefused(Refusal::WindowInPast, fn () => $this->open('06:00', '07:00'));
        $this->assertRefused(Refusal::ClockChange, fn () => $this->open('01:00', '05:00', '2027-03-14'));
        $this->assertRefused(Refusal::WindowTooShort, fn () => $this->open('13:00', '13:20'), named: 'A showing takes 30 minutes');
        $this->assertRefused(Refusal::WindowOverlaps, fn () => $this->open('11:00', '13:00'), named: '10:00');

        $this->showing('10:00', '10:30');
        $this->assertRefused(Refusal::WindowHasShowings, fn () => $this->close($this->window));

        $this->window->update(['closed_at' => now()]);
        $this->assertRefused(Refusal::WindowClosed, fn () => $this->close($this->window));
    }

    public function test_cancelling_once_and_before_the_start(): void
    {
        $showing = $this->showing('10:00', '10:30');
        $cancel = fn () => $this->api('POST', "/seller/showings/$showing->id/cancel", as: $this->seller);

        $this->at('2027-03-10 10:00:00');
        $this->assertRefused(Refusal::ShowingStarted, $cancel);

        $this->at('2027-03-10 09:59:59');
        $cancel()->assertOk()->assertJsonPath('data.status', 'cancelled_by_seller')->assertJsonPath('data.buyer_name', $showing->buyer->name);
        $this->assertSame(ShowingStatus::CancelledBySeller, $showing->refresh()->status);

        $this->assertRefused(Refusal::ShowingCancelled, $cancel);
    }

    // Criterion 37

    public function test_everything_of_someone_else_is_not_found_and_stays_the_same(): void
    {
        $photo = $this->photo();
        $showing = $this->showing('10:00', '10:30');
        $this->seller = User::factory()->seller()->create();
        $listing = "/seller/listings/{$this->listing->id}";
        $tables = fn () => [Listing::all()->toArray(), ListingPhoto::all()->toArray(), ShowingWindow::all()->toArray(), $showing->fresh()->toArray()];
        $before = $tables();

        foreach ([
            ['GET', $listing], ['PUT', $listing, self::INPUT], ['GET', "$listing/schedule"], ['POST', "$listing/photos"],
            ['PATCH', "$listing/photos/$photo->id", ['room' => 'other']], ['DELETE', "$listing/photos/$photo->id"],
            ['POST', "$listing/windows", ['date' => 'x']], ['POST', "/seller/windows/{$this->window->id}/close"],
            ['POST', "/seller/showings/$showing->id/cancel"],
        ] as $request) {
            $this->api($request[0], $request[1], $request[2] ?? [], $this->seller)->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        }

        $this->assertSame($before, $tables());
    }

    /** @return array<string, mixed> */
    private function fields(): array
    {
        return [
            'id' => $this->listing->id, 'address' => $this->listing->address, 'city' => 'Denver', 'state' => 'CO', 'price' => 450000,
            'bedrooms' => 3, 'bathrooms' => 2.5, 'description' => null, 'timezone' => 'America/Denver',
            'showing_duration_minutes' => 30, 'min_notice_hours' => 2,
        ];
    }

    private function photo(): ListingPhoto
    {
        return $this->listing->photos()->create(['path' => 'front.jpg', 'room' => 'exterior', 'position' => 1]);
    }

    /** @param list<string> $rooms */
    private function upload(array $rooms, string $name = 'a.jpg'): TestResponse
    {
        auth()->forgetGuards();

        return $this->withToken($this->token($this->seller))->post("/api/v1/seller/listings/{$this->listing->id}/photos", [
            'photos' => array_map(fn (string $room) => ['file' => UploadedFile::fake()->create($name, 1), 'room' => $room], $rooms),
        ]);
    }

    private function open(string $start, string $end, string $date = '2027-03-10'): TestResponse
    {
        return $this->api('POST', "/seller/listings/{$this->listing->id}/windows", ['date' => $date, 'start' => $start, 'end' => $end], $this->seller);
    }

    private function close(ShowingWindow $window): TestResponse
    {
        return $this->api('POST', "/seller/windows/$window->id/close", as: $this->seller);
    }

    /** @param callable(): TestResponse $act */
    private function assertRefused(Refusal $reason, callable $act, ?string $named = null): void
    {
        $before = ShowingWindow::orderBy('id')->get()->toArray();

        $answer = $act()->assertConflict()->assertJsonPath('reason', $reason->value);

        $this->assertSame(['message', 'reason'], array_keys($answer->json()));
        $this->assertStringContainsString($named ?? $reason->message(), $answer->json('message'));
        $this->assertSame($before, ShowingWindow::orderBy('id')->get()->toArray());
    }
}
