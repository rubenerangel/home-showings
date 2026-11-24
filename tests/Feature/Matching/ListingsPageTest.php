<?php

namespace Tests\Feature\Matching;

use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\ShowingWindow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Criterion 3 as the buyer sees it. */
class ListingsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2027-03-10 07:00', 'America/Denver'));
        $this->buyer = User::factory()->buyer()->create();
        $this->actingAs($this->buyer);
    }

    public function test_a_match_carries_what_the_screen_and_the_booking_need(): void
    {
        $listing = Listing::factory()->create();
        $window = ShowingWindow::factory()->for($listing)->between('2027-03-10', '10:00', '10:30')->create();
        $listing->photos()->create(['path' => 'photos/front.jpg', 'room' => 'exterior', 'position' => 0]);
        AvailabilityRange::factory()->for($this->buyer, 'buyer')->between('2027-03-10', '10:00', '12:00')->create();

        $this->get('/buyer/listings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('buyer/Listings')
            ->where('hasAvailability', true)
            ->has('listings', 1)
            ->has('listings.0', fn (Assert $match) => $match
                ->where('id', $listing->id)
                ->where('address', $listing->address)
                ->where('city', 'Denver')
                ->where('state', 'CO')
                ->where('price', 450000)
                ->where('bedrooms', 3)
                ->where('bathrooms', 2.5)
                ->where('description', null)
                ->where('timezone', 'America/Denver')
                ->where('photos', [['url' => '/storage/photos/front.jpg', 'room' => 'exterior']])
                ->where('slots', [[
                    'window_id' => $window->id,
                    'starts_at' => '2027-03-10T17:00:00Z',
                    'ends_at' => '2027-03-10T17:30:00Z',
                ]])));
    }

    public function test_without_ranges_the_page_says_so(): void
    {
        ShowingWindow::factory()->between('2027-03-10', '10:00', '10:30')->create();

        $this->assertPage(hasAvailability: false);
    }

    public function test_a_past_range_counts_as_no_range(): void
    {
        AvailabilityRange::factory()->for($this->buyer, 'buyer')->between('2027-03-09', '10:00', '12:00')->create();

        $this->assertPage(hasAvailability: false);
    }

    public function test_a_range_is_past_only_once_it_is_over_in_pacific_time(): void
    {
        AvailabilityRange::factory()->for($this->buyer, 'buyer')->between('2027-03-10', '10:00', '12:00')->create();

        $this->travelTo(CarbonImmutable::parse('2027-03-10 11:59:59', 'America/Los_Angeles'));
        $this->assertPage(hasAvailability: true);

        $this->travelTo(CarbonImmutable::parse('2027-03-10 12:00:00', 'America/Los_Angeles'));
        $this->assertPage(hasAvailability: false);
    }

    public function test_with_ranges_and_no_match_the_list_is_empty(): void
    {
        AvailabilityRange::factory()->for($this->buyer, 'buyer')->between('2027-03-10', '10:00', '12:00')->create();

        $this->assertPage(hasAvailability: true);
    }

    private function assertPage(bool $hasAvailability): void
    {
        $this->get('/buyer/listings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('hasAvailability', $hasAvailability)
            ->where('listings', []));
    }
}
