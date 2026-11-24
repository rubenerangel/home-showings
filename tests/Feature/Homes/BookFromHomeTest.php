<?php

namespace Tests\Feature\Homes;

use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\User;
use App\Scheduling\Refusal;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criteria 55 and 57: picking a time in the page of a home. */
class BookFromHomeTest extends TestCase
{
    use SchedulingScenario;

    public function test_a_slot_outside_the_availability_is_booked_and_no_availability_is_added(): void
    {
        AvailabilityRange::query()->delete();

        $this->book()->assertRedirect("/homes/{$this->listing->id}")->assertSessionHasNoErrors();

        $showing = Showing::sole();
        $this->assertSame([$this->buyer->id, $this->window->id], [$showing->buyer_id, $showing->showing_window_id]);
        $this->assertSame('2027-03-10T17:00:00Z', $showing->starts_at->toIso8601ZuluString());
        $this->assertTrue(DatabaseNotification::sole()->notifiable->is($this->seller));
        $this->assertSame(0, AvailabilityRange::count());
    }

    public function test_the_search_still_asks_for_the_availability(): void
    {
        AvailabilityRange::query()->delete();

        $this->actingAs($this->buyer)->post('/buyer/showings', ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:00:00Z'])
            ->assertSessionHasErrors(['refusal' => Refusal::OutsideAvailability->message()]);

        $this->assertSame(0, Showing::count());
    }

    public function test_the_other_refusals_are_the_same_and_write_nothing(): void
    {
        $this->listing->update(['min_notice_hours' => 0]);
        $elsewhere = $this->window('10:00', '12:00', Listing::factory()->create(['min_notice_hours' => 0]));

        $this->assertRefused(Refusal::NotAvailable, '2027-03-10T17:10:00Z');

        $this->listing->update(['min_notice_hours' => 48]);
        $this->assertRefused(Refusal::Notice);
        $this->listing->update(['min_notice_hours' => 0]);

        $this->showing('10:00', '10:30');
        $this->assertRefused(Refusal::JustBooked);

        $this->showing('10:30', '11:00', $this->buyer, $elsewhere);
        $this->assertRefused(Refusal::Overlaps, '2027-03-10T17:30:00Z');

        $this->showing('11:30', '12:00', $this->buyer);
        $this->assertRefused(Refusal::AlreadyHasShowing, '2027-03-10T18:00:00Z');

        $this->window->update(['closed_at' => now()]);
        $this->assertRefused(Refusal::NotAvailable, '2027-03-10T18:00:00Z');
    }

    public function test_a_window_of_another_home_is_not_found(): void
    {
        $other = $this->window('10:00', '12:00', Listing::factory()->create());

        $this->book(window: $other->id)->assertNotFound();
        $this->book(window: 999999)->assertNotFound();

        $this->assertSame(0, Showing::count());
    }

    #[TestWith(['0'])]
    #[TestWith(['abc'])]
    #[TestWith(['999999'])]
    public function test_a_home_that_does_not_exist_is_not_found(string $id): void
    {
        $this->actingAs($this->buyer)->post("/homes/$id/showings", ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:00:00Z'])
            ->assertNotFound();
    }

    public function test_malformed_input_is_refused(): void
    {
        $this->actingAs($this->buyer)->post("/homes/{$this->listing->id}/showings", ['window_id' => ['x'], 'starts_at' => 'x'])
            ->assertSessionHasErrors(['window_id', 'starts_at']);
    }

    // Criterion 57

    public function test_a_guest_goes_to_the_login_with_the_home(): void
    {
        auth()->logout();

        $this->post("/homes/{$this->listing->id}/showings", ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:00:00Z'])
            ->assertRedirect("/login?home={$this->listing->id}");

        $this->assertSame(0, Showing::count());
    }

    public function test_a_seller_is_forbidden(): void
    {
        $this->actingAs(User::factory()->seller()->create())
            ->post("/homes/{$this->listing->id}/showings", ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:00:00Z'])
            ->assertForbidden();
    }

    private function book(string $startsAt = '2027-03-10T17:00:00Z', ?int $window = null): TestResponse
    {
        return $this->actingAs($this->buyer)->from("/homes/{$this->listing->id}")->post("/homes/{$this->listing->id}/showings", [
            'window_id' => $window ?? $this->window->id, 'starts_at' => $startsAt,
        ]);
    }

    private function assertRefused(Refusal $reason, string $startsAt = '2027-03-10T17:00:00Z'): void
    {
        $before = [Showing::orderBy('id')->get()->toArray(), DatabaseNotification::count()];

        $this->book($startsAt)->assertRedirect("/homes/{$this->listing->id}")->assertSessionHasErrors(['refusal' => $reason->message()]);

        $this->assertSame($before, [Showing::orderBy('id')->get()->toArray(), DatabaseNotification::count()]);
    }
}
