<?php

namespace Tests\Feature\Homes;

use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criteria 52 and 54: the page of one home. */
class HomePageTest extends TestCase
{
    use SchedulingScenario;

    // Criterion 52

    public function test_a_guest_sees_the_days_and_no_times(): void
    {
        $photo = $this->listing->photos()->create(['path' => 'photos/front.jpg', 'room' => 'exterior', 'position' => 1]);
        $this->window('10:00', '11:00', attributes: ['local_starts_at' => '2027-03-12 10:00:00', 'local_ends_at' => '2027-03-12 11:00:00']);

        $this->get("/homes/{$this->listing->id}")->assertOk()->assertDontSee('T17:00')->assertDontSee('window_id')->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('home', [
                'id' => $this->listing->id,
                'address' => $this->listing->address,
                'city' => 'Denver',
                'state' => 'CO',
                'state_name' => 'Colorado',
                'price' => 450000,
                'bedrooms' => 3,
                'bathrooms' => 2.5,
                'description' => null,
                'timezone' => 'America/Denver',
                'photos' => [['url' => "/storage/$photo->path", 'room' => 'exterior']],
            ])
            ->where('openings', [['date' => '2027-03-10', 'count' => 4], ['date' => '2027-03-12', 'count' => 2]])
            ->where('booking', null));
    }

    public function test_a_seller_sees_what_a_guest_sees(): void
    {
        $this->actingAs($this->seller)->get("/homes/{$this->listing->id}")->assertOk()->assertDontSee('window_id')
            ->assertInertia(fn (Assert $page) => $page->where('openings.0.count', 4)->where('booking', null));
    }

    public function test_the_day_is_the_one_of_the_house(): void
    {
        $this->window->update(['closed_at' => now()]);
        $this->listing->update(['timezone' => 'America/Los_Angeles']);
        $this->window('20:00', '21:00');

        $this->get("/homes/{$this->listing->id}")->assertInertia(fn (Assert $page) => $page->where('openings', [['date' => '2027-03-10', 'count' => 2]]));
    }

    public function test_a_home_that_is_full_or_closed_has_no_openings(): void
    {
        $this->window->update(['closed_at' => now()]);

        $this->get("/homes/{$this->listing->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('openings', [])->where('home.id', $this->listing->id));
    }

    public function test_a_home_that_was_never_opened_is_not_found(): void
    {
        $this->window->delete();

        $this->get("/homes/{$this->listing->id}")->assertNotFound();
        $this->actingAs($this->buyer)->get("/homes/{$this->listing->id}")->assertNotFound();
    }

    #[TestWith(['0'])]
    #[TestWith(['999999'])]
    #[TestWith(['abc'])]
    #[TestWith(['9999999999999999999999'])]
    public function test_missing_and_malformed_ids_are_not_found(string $id): void
    {
        $this->get("/homes/$id")->assertNotFound();
    }

    // Criterion 54

    public function test_a_buyer_sees_every_slot_and_which_ones_fit(): void
    {
        AvailabilityRange::query()->delete();
        $this->available('10:00', '11:00');

        $this->actingAs($this->buyer)->get("/homes/{$this->listing->id}")->assertInertia(fn (Assert $page) => $page
            ->where('booking.showing', null)
            ->where('booking.slots', [
                ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:00:00Z', 'ends_at' => '2027-03-10T17:30:00Z', 'fits' => true],
                ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:30:00Z', 'ends_at' => '2027-03-10T18:00:00Z', 'fits' => true],
                ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T18:00:00Z', 'ends_at' => '2027-03-10T18:30:00Z', 'fits' => false],
                ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T18:30:00Z', 'ends_at' => '2027-03-10T19:00:00Z', 'fits' => false],
            ]));
    }

    public function test_without_availability_no_slot_fits_and_all_are_offered(): void
    {
        AvailabilityRange::query()->delete();

        $this->actingAs($this->buyer)->get("/homes/{$this->listing->id}")->assertInertia(fn (Assert $page) => $page
            ->has('booking.slots', 4)->where('booking.slots.0.fits', false));
    }

    public function test_slots_over_the_own_showings_elsewhere_are_not_offered(): void
    {
        $elsewhere = $this->window('10:00', '11:00', Listing::factory()->create());
        $this->showing('10:00', '10:30', $this->buyer, $elsewhere);

        $this->actingAs($this->buyer)->get("/homes/{$this->listing->id}")->assertInertia(fn (Assert $page) => $page
            ->has('booking.slots', 3)->where('booking.slots.0.starts_at', '2027-03-10T17:30:00Z')
            ->where('openings.0.count', 4));
    }

    public function test_with_an_active_showing_it_says_so_and_offers_nothing(): void
    {
        $showing = $this->showing('10:00', '10:30', $this->buyer);

        $this->actingAs($this->buyer)->get("/homes/{$this->listing->id}")->assertInertia(fn (Assert $page) => $page
            ->where('booking.slots', [])
            ->where('booking.showing.id', $showing->id)
            ->where('booking.showing.starts_at', '2027-03-10T17:00:00Z'));
    }

    public function test_the_showing_is_still_there_when_the_home_is_full(): void
    {
        $showing = $this->showing('10:00', '12:00', $this->buyer);

        $this->actingAs($this->buyer)->get("/homes/{$this->listing->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('openings', [])->where('booking.showing.id', $showing->id));
        $this->actingAs(User::factory()->buyer()->create())->get("/homes/{$this->listing->id}")->assertInertia(fn (Assert $page) => $page
            ->where('booking.showing', null)->where('booking.slots', []));
    }
}
