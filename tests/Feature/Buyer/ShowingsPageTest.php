<?php

namespace Tests\Feature\Buyer;

use App\Enums\ShowingStatus;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criterion 20: the showings a buyer sees. */
class ShowingsPageTest extends TestCase
{
    use SchedulingScenario;

    public function test_a_buyer_sees_only_own_showings_with_what_the_screen_needs(): void
    {
        $this->showing('10:30', '11:00');
        $showing = $this->showing('10:00', '10:30', $this->buyer);

        $this->actingAs($this->buyer)->get('/buyer/showings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('buyer/Showings')
            ->where('showings', [[
                'id' => $showing->id,
                'listing' => [
                    'address' => $this->listing->address,
                    'city' => 'Denver',
                    'state' => 'CO',
                    'timezone' => 'America/Denver',
                ],
                'starts_at' => '2027-03-10T17:00:00Z',
                'ends_at' => '2027-03-10T17:30:00Z',
                'status' => 'confirmed',
                'upcoming' => true,
                'cancellable' => true,
            ]]));
    }

    #[DataProvider('moments')]
    public function test_upcoming_lasts_until_the_end_and_cancelling_until_the_start(string $time, bool $upcoming, bool $cancellable): void
    {
        $this->showing('10:00', '10:30', $this->buyer);
        $this->at("2027-03-10 $time");

        $this->actingAs($this->buyer)->get('/buyer/showings')->assertInertia(fn (Assert $page) => $page
            ->where('showings.0.upcoming', $upcoming)
            ->where('showings.0.cancellable', $cancellable));
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function moments(): array
    {
        return [
            'one second before the start' => ['09:59:59', true, true],
            'at the start' => ['10:00:00', true, false],
            'one second before the end' => ['10:29:59', true, false],
            'at the end' => ['10:30:00', false, false],
        ];
    }

    public function test_a_cancelled_showing_is_neither_upcoming_nor_cancellable(): void
    {
        $this->showing('10:00', '10:30', $this->buyer, status: ShowingStatus::CancelledBySeller);

        $this->actingAs($this->buyer)->get('/buyer/showings')->assertInertia(fn (Assert $page) => $page
            ->where('showings.0.status', 'cancelled_by_seller')
            ->where('showings.0.upcoming', false)
            ->where('showings.0.cancellable', false));
    }

    public function test_showings_are_ordered_by_start(): void
    {
        $later = $this->showing('11:00', '11:30', $this->buyer, status: ShowingStatus::CancelledByBuyer);
        $earlier = $this->showing('10:00', '10:30', $this->buyer);

        $this->actingAs($this->buyer)->get('/buyer/showings')->assertInertia(fn (Assert $page) => $page
            ->where('showings.0.id', $earlier->id)
            ->where('showings.1.id', $later->id));
    }

    public function test_a_session_of_a_buyer_is_required(): void
    {
        $this->get('/buyer/showings')->assertRedirect('/');
        $this->actingAs(User::factory()->seller()->create())->get('/buyer/showings')->assertForbidden();
    }
}
