<?php

namespace Tests\Feature\Api;

use App\Enums\ShowingStatus;
use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\User;
use App\Scheduling\Refusal;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criteria 37, 38, 39 and 42, for the buyer. The rules are tested through the web: here, that the API reaches them. */
class BuyerTest extends TestCase
{
    use CallsApi, SchedulingScenario;

    // Availability

    public function test_the_own_availability_is_listed_in_order_without_the_past(): void
    {
        AvailabilityRange::factory()->for($this->buyer, 'buyer')->between('2027-03-09', '08:00', '09:00')->create();
        AvailabilityRange::factory()->create();
        $early = AvailabilityRange::factory()->for($this->buyer, 'buyer')->between('2027-03-10', '07:00', '07:30')->create();

        $this->api('GET', '/buyer/availability', as: $this->buyer)->assertOk()->assertExactJson(['data' => [
            ['id' => $early->id, 'date' => '2027-03-10', 'start' => '07:00', 'end' => '07:30'],
            ['id' => $this->range()->id, 'date' => '2027-03-10', 'start' => '08:00', 'end' => '18:00'],
        ]]);
    }

    public function test_availability_is_added(): void
    {
        $answer = $this->api('POST', '/buyer/availability', ['date' => '2027-03-11', 'start' => '10:00', 'end' => '12:00'], $this->buyer);

        $range = $this->buyer->availabilityRanges()->latest('id')->firstOrFail();
        $answer->assertCreated()->assertExactJson(['data' => ['id' => $range->id, 'date' => '2027-03-11', 'start' => '10:00', 'end' => '12:00']]);
        $this->assertSame('2027-03-11 10:00', $range->local_starts_at->format('Y-m-d H:i'));
    }

    public function test_past_and_malformed_availability_is_refused(): void
    {
        $this->api('POST', '/buyer/availability', ['date' => '2027-03-09', 'start' => '10:00', 'end' => '12:00'], $this->buyer)
            ->assertUnprocessable()->assertJsonValidationErrors(['end' => 'This time is already over.']);
        $this->api('POST', '/buyer/availability', ['date' => 'x', 'start' => ['10:00'], 'end' => true], $this->buyer)
            ->assertUnprocessable()->assertJsonValidationErrors(['date', 'start', 'end']);

        $this->assertSame(1, AvailabilityRange::count());
    }

    public function test_availability_is_removed_and_someone_else_s_is_not_found(): void
    {
        $theirs = AvailabilityRange::factory()->create();

        $this->api('DELETE', "/buyer/availability/$theirs->id", as: $this->buyer)->assertNotFound();
        $this->api('DELETE', "/buyer/availability/{$this->range()->id}", as: $this->buyer)->assertNoContent();

        $this->assertTrue(AvailabilityRange::sole()->is($theirs));
    }

    // Matches

    public function test_the_matches_are_the_ones_of_the_web_with_absolute_photo_urls(): void
    {
        $this->listing->photos()->create(['path' => 'photos/front.jpg', 'room' => 'exterior', 'position' => 0]);
        $this->showing('10:30', '11:00');
        $this->window('09:30', '10:30', Listing::factory()->create());

        $answer = $this->api('GET', '/buyer/listings', as: $this->buyer)->assertOk()
            ->assertJsonPath('has_availability', true)
            ->assertJsonPath('data.1.photos', [['url' => 'http://localhost:8080/storage/photos/front.jpg', 'room' => 'exterior']])
            ->assertJsonPath('data.1.slots.*.starts_at', ['2027-03-10T17:00:00Z', '2027-03-10T18:00:00Z', '2027-03-10T18:30:00Z']);
        $matches = $answer->json('data');
        $matches[1]['photos'][0]['url'] = '/storage/photos/front.jpg';

        $this->actingAs($this->buyer)->get('/buyer/listings')->assertInertia(fn ($page) => $page->where('listings', $matches));
    }

    public function test_without_availability_it_says_so(): void
    {
        $this->range()->delete();

        $this->api('GET', '/buyer/listings', as: $this->buyer)->assertOk()->assertExactJson(['data' => [], 'has_availability' => false]);
    }

    // Booking

    public function test_booking_and_listing_the_showings(): void
    {
        $answer = $this->book()->assertCreated();

        $showing = Showing::sole();
        $this->assertSame([$this->buyer->id, $this->window->id, ShowingStatus::Confirmed], [$showing->buyer_id, $showing->showing_window_id, $showing->status]);
        $expected = [
            'id' => $showing->id,
            'listing' => $this->listing->only('address', 'city', 'state') + ['timezone' => 'America/Denver'],
            'starts_at' => '2027-03-10T17:00:00Z',
            'ends_at' => '2027-03-10T17:30:00Z',
            'status' => 'confirmed',
            'upcoming' => true,
            'cancellable' => true,
        ];
        $answer->assertExactJson(['data' => $expected]);
        $this->showing('11:00', '11:30');
        $this->api('GET', '/buyer/showings', as: $this->buyer)->assertOk()->assertExactJson(['data' => [$expected]]);
    }

    public function test_an_unknown_window_is_not_found_and_malformed_input_is_refused(): void
    {
        $this->book(window: 999999)->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->book('2027-03-10T17:00:00.000Z')->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->api('POST', '/buyer/showings', ['window_id' => ['x'], 'starts_at' => ['x']], $this->buyer)
            ->assertUnprocessable()->assertJsonValidationErrors(['window_id', 'starts_at']);

        $this->assertSame(0, Showing::count());
    }

    // Criterion 39

    public function test_every_reason_to_refuse_a_booking_is_a_conflict(): void
    {
        $this->listing->update(['min_notice_hours' => 0]);
        $elsewhere = $this->window('10:00', '12:00', Listing::factory()->create(['min_notice_hours' => 0]));

        $this->assertRefused(Refusal::NotAvailable, fn () => $this->book('2027-03-10T17:10:00Z'));

        $this->listing->update(['min_notice_hours' => 48]);
        $this->assertRefused(Refusal::Notice, fn () => $this->book());
        $this->listing->update(['min_notice_hours' => 0]);

        $this->range()->update(['local_starts_at' => '2027-03-10 10:15:00']);
        $this->assertRefused(Refusal::OutsideAvailability, fn () => $this->book());
        $this->range()->update(['local_starts_at' => '2027-03-10 08:00:00']);

        $this->showing('10:00', '10:30');
        $this->assertRefused(Refusal::JustBooked, fn () => $this->book());

        $this->showing('10:30', '11:00', $this->buyer, $elsewhere);
        $this->assertRefused(Refusal::Overlaps, fn () => $this->book('2027-03-10T17:30:00Z'));

        $this->showing('11:30', '12:00', $this->buyer);
        $this->assertRefused(Refusal::AlreadyHasShowing, fn () => $this->book('2027-03-10T18:00:00Z'));
    }

    public function test_cancelling_once_and_before_the_start(): void
    {
        $showing = $this->showing('10:00', '10:30', $this->buyer);
        $cancel = fn () => $this->api('POST', "/buyer/showings/$showing->id/cancel", as: $this->buyer);

        $this->at('2027-03-10 10:00:00');
        $this->assertRefused(Refusal::ShowingStarted, $cancel);

        $this->at('2027-03-10 09:59:59');
        $cancel()->assertOk()->assertJsonPath('data.status', 'cancelled_by_buyer')->assertJsonPath('data.cancellable', false);
        $this->assertSame(ShowingStatus::CancelledByBuyer, $showing->refresh()->status);

        $this->assertRefused(Refusal::ShowingCancelled, $cancel);
    }

    public function test_a_showing_of_someone_else_is_not_found(): void
    {
        $showing = $this->showing('10:00', '10:30');

        $this->api('POST', "/buyer/showings/$showing->id/cancel", as: $this->buyer)->assertNotFound();

        $this->assertSame(ShowingStatus::Confirmed, $showing->refresh()->status);
    }

    // Criterion 42

    public function test_a_slot_booked_through_one_door_is_refused_through_the_other(): void
    {
        $other = User::factory()->buyer()->create();
        AvailabilityRange::factory()->for($other, 'buyer')->between('2027-03-10', '08:00', '18:00')->create();

        $this->book()->assertCreated();
        $this->actingAs($other)->post('/buyer/showings', ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:00:00Z'])
            ->assertSessionHasErrors(['refusal' => Refusal::JustBooked->message()]);

        $this->post('/buyer/showings', ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:30:00Z'])->assertSessionHasNoErrors();
        $third = User::factory()->buyer()->create();
        AvailabilityRange::factory()->for($third, 'buyer')->between('2027-03-10', '08:00', '18:00')->create();
        $this->buyer = $third;
        $this->assertRefused(Refusal::JustBooked, fn () => $this->book('2027-03-10T17:30:00Z'));
    }

    private function book(string $startsAt = '2027-03-10T17:00:00Z', ?int $window = null): TestResponse
    {
        return $this->api('POST', '/buyer/showings', ['window_id' => $window ?? $this->window->id, 'starts_at' => $startsAt], $this->buyer);
    }

    private function range(): AvailabilityRange
    {
        return $this->buyer->availabilityRanges()->where('local_ends_at', '2027-03-10 18:00:00')->firstOrFail();
    }

    /** @param callable(): TestResponse $act */
    private function assertRefused(Refusal $reason, callable $act): void
    {
        $before = Showing::orderBy('id')->get()->toArray();

        $act()->assertConflict()->assertExactJson(['message' => $reason->message(), 'reason' => $reason->value]);

        $this->assertSame($before, Showing::orderBy('id')->get()->toArray());
    }
}
