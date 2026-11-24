<?php

namespace Tests\Feature\Seller;

use App\Enums\ShowingStatus;
use App\Models\Listing;
use App\Models\ShowingWindow;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

class SchedulePageTest extends TestCase
{
    use SchedulingScenario;

    public function test_it_shows_the_listing_and_its_windows_in_house_time(): void
    {
        $this->window('13:00', '14:10');
        $this->props(fn (Assert $page) => $page
            ->component('seller/listings/Schedule')
            ->where('listing.id', $this->listing->id)
            ->where('listing.timezone', 'America/Denver')
            ->where('listing.showing_duration_minutes', 30)
            ->where('today', '2027-03-10')
            ->has('windows', 2)
            ->where('windows.0', [
                'id' => $this->window->id, 'date' => '2027-03-10', 'start' => '10:00', 'end' => '12:00',
                'showings' => 4, 'unused_minutes' => 0, 'active_showings' => 0, 'past' => false,
            ])
            ->where('windows.1.start', '13:00')
            ->where('windows.1.showings', 2)
            ->where('windows.1.unused_minutes', 10));
    }

    public function test_windows_keep_the_duration_they_were_opened_with(): void
    {
        $this->listing->update(['showing_duration_minutes' => 60]);

        $this->props(fn (Assert $page) => $page
            ->where('listing.showing_duration_minutes', 60)
            ->where('windows.0.showings', 4));
    }

    public function test_windows_are_ordered_and_closed_ones_or_those_of_other_listings_are_left_out(): void
    {
        $earlier = $this->window('08:00', '09:00');
        $this->window('13:00', '14:00', attributes: ['closed_at' => now()]);
        $sibling = Listing::factory()->for($this->seller, 'seller')->create();
        ShowingWindow::factory()->for($sibling)->create();

        $this->props(fn (Assert $page) => $page
            ->has('windows', 2)
            ->where('windows.0.id', $earlier->id)
            ->where('windows.1.id', $this->window->id));
    }

    #[DataProvider('houseClocks')]
    public function test_a_window_is_past_once_it_ends_on_the_clock_of_the_house(string $denver, bool $past): void
    {
        $this->at($denver);

        $this->props(fn (Assert $page) => $page->has('windows', 1)->where('windows.0.past', $past));
    }

    /** @return array<string, array{string, bool}> */
    public static function houseClocks(): array
    {
        return [
            'one second before the end' => ['2027-03-10 11:59:59', false],
            'at the end' => ['2027-03-10 12:00:00', true],
        ];
    }

    public function test_the_date_of_today_is_the_one_of_the_house(): void
    {
        $this->at('2027-03-10 23:30:00');

        $this->props(fn (Assert $page) => $page->where('today', '2027-03-10'));
    }

    public function test_only_active_showings_are_counted_in_a_window(): void
    {
        $this->showing('10:00', '10:30');
        $this->showing('10:30', '11:00');
        $this->showing('11:00', '11:30', status: ShowingStatus::CancelledByBuyer);
        $this->showing('11:30', '12:00', status: ShowingStatus::CancelledBySeller);

        $this->at('2027-03-10 10:29:59');
        $this->props(fn (Assert $page) => $page->where('windows.0.active_showings', 2));
        $this->at('2027-03-10 10:30:00');
        $this->props(fn (Assert $page) => $page->where('windows.0.active_showings', 1));
    }

    public function test_it_shows_the_showings_of_the_listing_as_instants(): void
    {
        $showing = $this->showing('10:00', '10:30', $this->buyer);
        $sibling = Listing::factory()->for($this->seller, 'seller')->create();
        $this->showing('10:00', '10:30', window: $this->window('10:00', '12:00', $sibling));

        $this->props(fn (Assert $page) => $page
            ->has('showings', 1)
            ->where('showings.0', [
                'id' => $showing->id,
                'buyer_name' => $this->buyer->name,
                'listing' => [
                    'address' => $this->listing->address, 'city' => 'Denver', 'state' => 'CO', 'timezone' => 'America/Denver',
                ],
                'starts_at' => '2027-03-10T17:00:00Z',
                'ends_at' => '2027-03-10T17:30:00Z',
                'status' => 'confirmed',
                'upcoming' => true,
                'cancellable' => true,
            ]));
    }

    #[DataProvider('moments')]
    public function test_a_confirmed_showing_at_each_moment(string $denver, bool $upcoming, bool $cancellable): void
    {
        $this->showing('10:00', '10:30');
        $this->at($denver);

        $this->props(fn (Assert $page) => $page
            ->where('showings.0.upcoming', $upcoming)
            ->where('showings.0.cancellable', $cancellable));
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function moments(): array
    {
        return [
            'before the start' => ['2027-03-10 09:59:59', true, true],
            'at the start' => ['2027-03-10 10:00:00', true, false],
            'before the end' => ['2027-03-10 10:29:59', true, false],
            'at the end' => ['2027-03-10 10:30:00', false, false],
        ];
    }

    #[DataProvider('cancelled')]
    public function test_a_cancelled_showing_is_neither_upcoming_nor_cancellable(ShowingStatus $status): void
    {
        $this->showing('10:00', '10:30', status: $status);

        $this->props(fn (Assert $page) => $page
            ->where('showings.0.status', $status->value)
            ->where('showings.0.upcoming', false)
            ->where('showings.0.cancellable', false));
    }

    /** @return array<string, array{ShowingStatus}> */
    public static function cancelled(): array
    {
        return [
            'by the buyer' => [ShowingStatus::CancelledByBuyer],
            'by the seller' => [ShowingStatus::CancelledBySeller],
        ];
    }

    public function test_upcoming_showings_come_first_by_start_and_the_rest_by_latest_start(): void
    {
        $first = $this->showing('10:00', '10:30');
        $late = $this->showing('11:30', '12:00');
        $soon = $this->showing('11:00', '11:30');
        $cancelled = $this->showing('10:30', '11:00', status: ShowingStatus::CancelledByBuyer);
        $this->at('2027-03-10 10:30:00');

        $this->props(fn (Assert $page) => $page->where(
            'showings',
            fn ($showings) => $showings->pluck('id')->all() === [$soon->id, $late->id, $cancelled->id, $first->id],
        ));
    }

    public function test_guests_buyers_and_other_sellers_cannot_see_it(): void
    {
        $path = "/seller/listings/{$this->listing->id}/schedule";

        $this->get($path)->assertRedirect('/');
        $this->actingAs($this->buyer)->get($path)->assertForbidden();
        $this->get('/seller/listings/999999/schedule')->assertForbidden();

        $this->actingAs(User::factory()->seller()->create())->get($path)->assertNotFound();
        $this->get('/seller/listings/99999999999999999999/schedule')->assertNotFound();
    }

    private function props(callable $assert): void
    {
        $this->actingAs($this->seller)->get("/seller/listings/{$this->listing->id}/schedule")->assertOk()->assertInertia($assert);
    }
}
