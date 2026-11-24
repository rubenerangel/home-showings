<?php

namespace Tests\Feature\Scheduling;

use App\Enums\ShowingStatus;
use App\Models\Showing;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Criterion 13 (the sequential part), rule R8. */
class CancellingTest extends TestCase
{
    use SchedulingScenario;

    #[DataProvider('sides')]
    public function test_each_side_can_cancel_before_the_start(string $side, ShowingStatus $status): void
    {
        $showing = $this->showing('10:00', '10:30', $this->buyer);
        $this->at('2027-03-10 09:59:59');

        $this->cancel($side, $showing)->assertRedirect()->assertSessionHasNoErrors();

        $showing->refresh();
        $this->assertSame($status, $showing->status);
        $this->assertTrue($showing->cancelled_at->equalTo(now()));
    }

    #[TestWith(['buyer'])]
    #[TestWith(['seller'])]
    public function test_a_started_showing_cannot_be_cancelled(string $side): void
    {
        $showing = $this->showing('10:00', '10:30', $this->buyer);
        $this->at('2027-03-10 10:00:00');

        $this->assertNotCancelled('This showing has already started.', $side, $showing);
    }

    #[TestWith(['buyer'])]
    #[TestWith(['seller'])]
    public function test_a_showing_is_cancelled_only_once(string $side): void
    {
        foreach ([ShowingStatus::CancelledByBuyer, ShowingStatus::CancelledBySeller] as $status) {
            $showing = $this->showing('10:00', '10:30', $this->buyer, status: $status);
            $this->travel(1)->minutes();

            $this->assertNotCancelled('This showing was already cancelled.', $side, $showing);
        }
    }

    /** @return array<string, array{string, ShowingStatus}> */
    public static function sides(): array
    {
        return [
            'buyer' => ['buyer', ShowingStatus::CancelledByBuyer],
            'seller' => ['seller', ShowingStatus::CancelledBySeller],
        ];
    }

    private function cancel(string $side, Showing $showing): TestResponse
    {
        $actor = $side === 'buyer' ? $this->buyer : $this->seller;

        return $this->actingAs(User::findOrFail($actor->id))->post("/$side/showings/{$showing->id}/cancel");
    }

    private function assertNotCancelled(string $message, string $side, Showing $showing): void
    {
        $before = $showing->refresh()->toArray();

        $this->cancel($side, $showing)->assertRedirect()->assertSessionHasErrors(['refusal' => $message]);

        $this->assertSame($before, $showing->refresh()->toArray());
    }
}
