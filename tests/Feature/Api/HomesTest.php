<?php

namespace Tests\Feature\Api;

use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use App\Scheduling\Refusal;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criterion 58: the homes, in the API. */
class HomesTest extends TestCase
{
    use CallsApi, SchedulingScenario;

    public function test_anyone_lists_the_homes_without_times(): void
    {
        $this->listing->photos()->create(['path' => 'photos/front.jpg', 'room' => 'exterior', 'position' => 1]);

        $answer = $this->api('GET', '/homes')->assertOk()->assertDontSee('T17:00')->assertDontSee('window_id')
            ->assertJsonPath('state', null)
            ->assertJsonPath('states', [['code' => 'CO', 'name' => 'Colorado', 'count' => 1]])
            ->assertJsonPath('data.0.cover.url', 'http://localhost:8080/storage/photos/front.jpg');
        $homes = $answer->json('data');
        $homes[0]['cover']['url'] = '/storage/photos/front.jpg';

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('homes', $homes));
    }

    #[TestWith(['TX', 'TX', 1])]
    #[TestWith(['WY', 'WY', 0])]
    #[TestWith(['xx', null, 2])]
    public function test_the_filter_is_the_one_of_the_page(string $state, ?string $chosen, int $count): void
    {
        ShowingWindow::factory()->for(Listing::factory()->create(['state' => 'TX']))->between('2027-03-11', '10:00', '11:00')->create();

        $this->api('GET', "/homes?state=$state")->assertOk()->assertJsonPath('state', $chosen)->assertJsonCount($count, 'data')->assertJsonCount(2, 'states');
    }

    public function test_anyone_sees_one_home_without_times(): void
    {
        $this->api('GET', "/homes/{$this->listing->id}")->assertOk()->assertDontSee('T17:00')->assertDontSee('window_id')
            ->assertJsonPath('data.address', $this->listing->address)
            ->assertJsonPath('data.openings', [['date' => '2027-03-10', 'count' => 4]]);
    }

    #[TestWith(['0'])]
    #[TestWith(['abc'])]
    #[TestWith(['999999'])]
    public function test_a_home_that_does_not_exist_is_not_found(string $id): void
    {
        $this->api('GET', "/homes/$id")->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->api('GET', "/buyer/homes/$id", as: $this->buyer)->assertNotFound();
    }

    public function test_a_home_that_was_never_opened_is_not_found(): void
    {
        $this->window->delete();

        $this->api('GET', "/homes/{$this->listing->id}")->assertNotFound();
        $this->api('GET', "/buyer/homes/{$this->listing->id}", as: $this->buyer)->assertNotFound();
    }

    public function test_a_buyer_reads_the_slots_and_books_one_outside_the_availability(): void
    {
        AvailabilityRange::query()->delete();

        $slot = $this->api('GET', "/buyer/homes/{$this->listing->id}", as: $this->buyer)->assertOk()
            ->assertJsonCount(4, 'data.slots')->assertJsonPath('data.slots.0.fits', false)->assertJsonPath('data.showing', null)
            ->assertJsonPath('data.home.id', $this->listing->id)
            ->json('data.slots.0');
        $this->book($slot['starts_at'])->assertCreated()->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(1, Showing::count());
        $this->api('GET', "/buyer/homes/{$this->listing->id}", as: $this->buyer)
            ->assertJsonPath('data.slots', [])->assertJsonPath('data.showing.id', Showing::sole()->id);
    }

    public function test_a_refusal_is_a_conflict(): void
    {
        $this->showing('10:00', '10:30');

        $this->book()->assertConflict()->assertExactJson(['message' => Refusal::JustBooked->message(), 'reason' => 'just_booked']);
    }

    public function test_the_search_still_asks_for_the_availability(): void
    {
        AvailabilityRange::query()->delete();

        $this->api('POST', '/buyer/showings', ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:00:00Z'], $this->buyer)
            ->assertConflict()->assertJsonPath('reason', 'outside_availability');
    }

    public function test_a_window_of_another_home_is_not_found(): void
    {
        $other = $this->window('10:00', '12:00', Listing::factory()->create());

        $this->api('POST', "/buyer/homes/{$this->listing->id}/showings", ['window_id' => $other->id, 'starts_at' => '2027-03-10T17:00:00Z'], $this->buyer)
            ->assertNotFound();
        $this->assertSame(0, Showing::count());
    }

    public function test_the_public_routes_have_their_own_limit(): void
    {
        $this->freezeSecond();
        for ($i = 0; $i < 60; $i++) {
            $this->api('GET', $i % 2 ? '/homes' : "/homes/{$this->listing->id}")->assertOk();
        }

        $this->api('GET', '/homes')->assertTooManyRequests()->assertHeader('Retry-After');
        $this->api('POST', '/tokens')->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->api('GET', '/homes')->assertOk();
    }

    public function test_the_public_routes_ignore_the_token(): void
    {
        $this->api('GET', '/homes', as: '1|unknown')->assertOk();
        $this->api('GET', '/homes', as: User::factory()->buyer()->create())->assertOk()->assertJsonPath('data.0.fits', null);
    }

    private function book(string $startsAt = '2027-03-10T17:00:00Z'): TestResponse
    {
        return $this->api('POST', "/buyer/homes/{$this->listing->id}/showings", ['window_id' => $this->window->id, 'starts_at' => $startsAt], $this->buyer);
    }
}
