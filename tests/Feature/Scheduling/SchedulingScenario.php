<?php

namespace Tests\Feature\Scheduling;

use App\Enums\ShowingStatus;
use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** A Denver home with a 10:00 to 12:00 window on 2027-03-10, and a buyer who is free that day. */
trait SchedulingScenario
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private Listing $listing;

    private ShowingWindow $window;

    protected function setUp(): void
    {
        parent::setUp();

        $this->at('2027-03-10 07:00:00');
        $this->seller = User::factory()->seller()->create();
        $this->buyer = User::factory()->buyer()->create();
        $this->listing = Listing::factory()->for($this->seller, 'seller')->create();
        $this->window = $this->window('10:00', '12:00');
        $this->available('08:00', '18:00');
    }

    private function at(string $denver): void
    {
        $this->travelTo(CarbonImmutable::parse($denver, 'America/Denver'));
    }

    private function instant(string $time, string $date = '2027-03-10'): CarbonImmutable
    {
        return CarbonImmutable::parse("$date $time", 'America/Denver')->utc();
    }

    /** @param array<string, mixed> $attributes */
    private function window(string $start, string $end, ?Listing $listing = null, array $attributes = []): ShowingWindow
    {
        return ShowingWindow::factory()->for($listing ?? $this->listing)->between('2027-03-10', $start, $end)->create($attributes);
    }

    private function available(string $start, string $end): void
    {
        AvailabilityRange::factory()->for($this->buyer, 'buyer')->between('2027-03-10', $start, $end)->create();
    }

    private function showing(
        string $start,
        string $end,
        ?User $buyer = null,
        ?ShowingWindow $window = null,
        ShowingStatus $status = ShowingStatus::Confirmed,
    ): Showing {
        return Showing::factory()->create([
            'showing_window_id' => ($window ?? $this->window)->id,
            'buyer_id' => $buyer->id ?? User::factory()->buyer(),
            'starts_at' => $this->instant($start),
            'ends_at' => $this->instant($end),
            'status' => $status,
            'cancelled_at' => $status === ShowingStatus::Confirmed ? null : now(),
        ]);
    }
}
