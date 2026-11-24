<?php

namespace Tests\Feature\Homes;

use App\Enums\ShowingStatus;
use App\Models\Listing;
use App\Models\ShowingWindow;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criteria 50, 51 and 52: the main page, for anyone. */
class HomesPageTest extends TestCase
{
    use SchedulingScenario;

    // Criterion 50

    public function test_a_guest_sees_the_available_homes_without_times(): void
    {
        $this->listing->photos()->create(['path' => 'photos/front.jpg', 'room' => 'exterior', 'position' => 1]);
        $this->listing->photos()->create(['path' => 'photos/back.jpg', 'room' => 'other', 'position' => 2]);

        $this->get('/')->assertOk()->assertDontSee('T17:00')->assertDontSee('window_id')->assertInertia(fn (Assert $page) => $page
            ->component('Homes')
            ->where('state', null)
            ->where('states', [['code' => 'CO', 'name' => 'Colorado', 'count' => 1]])
            ->where('homes', [[
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
                'cover' => ['url' => '/storage/photos/front.jpg', 'room' => 'exterior'],
                'openings' => 4,
                'next_opening' => '2027-03-10',
                'fits' => null,
            ]]));
    }

    #[TestWith(['seller'])]
    #[TestWith(['buyer'])]
    public function test_it_is_also_open_with_a_session(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))->get('/')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Homes')->has('homes', 1));
    }

    public function test_a_buyer_sees_which_ones_fit(): void
    {
        $this->window('10:00', '12:00', $other = Listing::factory()->create(['price' => 1]), [
            'local_starts_at' => '2027-03-20 10:00:00', 'local_ends_at' => '2027-03-20 12:00:00',
        ]);

        $this->actingAs($this->buyer)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('homes.0.id', $other->id)->where('homes.0.fits', false)
            ->where('homes.1.id', $this->listing->id)->where('homes.1.fits', true));
        $this->actingAs($this->seller)->get('/')->assertInertia(fn (Assert $page) => $page->where('homes.0.fits', null));
    }

    #[DataProvider('unavailable')]
    public function test_a_home_without_a_slot_that_can_be_booked_is_not_shown(callable $arrange): void
    {
        $arrange->call($this);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('homes', [])->where('states', []));
    }

    /** @return array<string, array{callable}> */
    public static function unavailable(): array
    {
        return [
            'no window' => [function () {
                $this->window->delete();
            }],
            'closed window' => [function () {
                $this->window->update(['closed_at' => now()]);
            }],
            'past window' => [function () {
                $this->at('2027-03-10 12:00:00');
            }],
            'not enough notice' => [function () {
                $this->listing->update(['min_notice_hours' => 48]);
            }],
            'every slot booked' => [function () {
                foreach ([['10:00', '10:30'], ['10:30', '11:00'], ['11:00', '11:30'], ['11:30', '12:00']] as [$start, $end]) {
                    $this->showing($start, $end);
                }
            }],
            'the rest cancelled by the seller' => [function () {
                $this->showing('10:00', '11:00');
                $this->showing('11:00', '12:00', status: ShowingStatus::CancelledBySeller);
            }],
        ];
    }

    public function test_the_availability_and_the_showings_of_the_buyer_do_not_hide_a_home(): void
    {
        $this->buyer->availabilityRanges()->delete();
        $this->showing('10:00', '10:30', $this->buyer);

        $this->actingAs($this->buyer)->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('homes', 1)->where('homes.0.openings', 3)->where('homes.0.fits', false));
    }

    public function test_the_next_opening_is_the_date_of_the_house(): void
    {
        $this->window->update(['closed_at' => now()]);
        $this->listing->update(['timezone' => 'America/Los_Angeles']);
        $this->window('20:00', '21:00');

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('homes.0.next_opening', '2027-03-10'));
    }

    public function test_they_go_by_state_name_then_city_then_price_then_id(): void
    {
        $this->window->update(['closed_at' => now()]);
        $home = function (string $state, string $city, int $price): int {
            $listing = Listing::factory()->create(['state' => $state, 'city' => $city, 'price' => $price]);
            ShowingWindow::factory()->for($listing)->between('2027-03-11', '10:00', '11:00')->create();

            return $listing->id;
        };
        $ak = $home('AK', 'Juneau', 5);
        $denver2 = $home('CO', 'denver', 2);
        $boulder = $home('CO', 'Boulder', 9);
        $denver1 = $home('CO', 'Denver', 1);
        $same = $home('CO', 'DENVER', 2);
        $al = $home('AL', 'Mobile', 7);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('homes', fn ($homes) => $homes->pluck('id')->all() === [$al, $ak, $boulder, $denver1, $denver2, $same])
            ->where('states.0.name', 'Alabama')->where('states.1.name', 'Alaska')->where('states.2.count', 4));
    }

    // Criterion 51

    public function test_the_filter_shows_one_state_and_counts_all_of_them(): void
    {
        $texas = Listing::factory()->create(['state' => 'TX', 'city' => 'Austin']);
        ShowingWindow::factory()->for($texas)->between('2027-03-11', '10:00', '11:00')->create();

        $this->get('/?state=TX')->assertInertia(fn (Assert $page) => $page
            ->where('state', 'TX')->has('homes', 1)->where('homes.0.id', $texas->id)
            ->where('states', [['code' => 'CO', 'name' => 'Colorado', 'count' => 1], ['code' => 'TX', 'name' => 'Texas', 'count' => 1]]));
    }

    public function test_a_state_without_homes_is_empty(): void
    {
        $this->get('/?state=WY')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('state', 'WY')->where('homes', [])->has('states', 1));
    }

    #[TestWith(['/?state=XX'])]
    #[TestWith(['/?state=co'])]
    #[TestWith(['/?state='])]
    #[TestWith(['/?state[]=CO'])]
    #[TestWith(['/?state=C%00O'])]
    #[TestWith(['/?state=%FF'])]
    public function test_a_value_that_is_not_a_state_shows_everything(string $uri): void
    {
        $this->get($uri)->assertOk()->assertInertia(fn (Assert $page) => $page->where('state', null)->has('homes', 1));
    }
}
