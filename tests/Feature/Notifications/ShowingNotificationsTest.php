<?php

namespace Tests\Feature\Notifications;

use App\Enums\ShowingStatus;
use App\Models\Showing;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criterion 18, decision D17. */
class ShowingNotificationsTest extends TestCase
{
    use SchedulingScenario;

    public function test_booking_notifies_the_seller(): void
    {
        $this->book()->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotified($this->seller, 'booked', $this->buyer);
    }

    public function test_a_cancellation_by_the_buyer_notifies_the_seller(): void
    {
        $this->cancel('buyer', $this->showing('10:00', '10:30', $this->buyer))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotified($this->seller, 'cancelled_by_buyer', $this->buyer);
    }

    public function test_a_cancellation_by_the_seller_notifies_the_buyer(): void
    {
        $this->cancel('seller', $this->showing('10:00', '10:30', $this->buyer))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotified($this->buyer, 'cancelled_by_seller', $this->seller);
    }

    public function test_a_refused_booking_notifies_nobody(): void
    {
        $this->showing('10:00', '10:30');

        $this->book()->assertSessionHasErrors('refusal');

        $this->assertSame(0, DatabaseNotification::count());
    }

    #[TestWith(['buyer'])]
    #[TestWith(['seller'])]
    public function test_a_refused_cancellation_notifies_nobody(string $side): void
    {
        $this->cancel($side, $this->showing('10:00', '10:30', $this->buyer, status: ShowingStatus::CancelledByBuyer))
            ->assertSessionHasErrors('refusal');

        $this->assertSame(0, DatabaseNotification::count());
    }

    public function test_a_booking_whose_notification_fails_is_undone(): void
    {
        $this->failWhenNotifying();

        $this->book()->assertServerError();

        $this->assertSame(0, Showing::count());
        $this->assertSame(0, DatabaseNotification::count());
    }

    #[TestWith(['buyer'])]
    #[TestWith(['seller'])]
    public function test_a_cancellation_whose_notification_fails_is_undone(string $side): void
    {
        $showing = $this->showing('10:00', '10:30', $this->buyer);
        $this->failWhenNotifying();

        $this->cancel($side, $showing)->assertServerError();

        $this->assertSame(ShowingStatus::Confirmed, $showing->refresh()->status);
        $this->assertSame(0, DatabaseNotification::count());
    }

    private function failWhenNotifying(): void
    {
        // After the row is written, so an action that does not undo it would leave it behind.
        $this->app['events']->listen('eloquent.created: '.DatabaseNotification::class, function () {
            throw new RuntimeException('Notifying failed.');
        });
    }

    private function book(): TestResponse
    {
        return $this->actingAs($this->buyer)->post('/buyer/showings', [
            'window_id' => $this->window->id,
            'starts_at' => $this->instant('10:00')->toIso8601ZuluString(),
        ]);
    }

    private function cancel(string $side, Showing $showing): TestResponse
    {
        return $this->actingAs($side === 'buyer' ? $this->buyer : $this->seller)->post("/$side/showings/{$showing->id}/cancel");
    }

    private function assertNotified(User $user, string $kind, User $other): void
    {
        $notification = DatabaseNotification::sole();

        $this->assertTrue($notification->notifiable->is($user));
        $this->assertNull($notification->read_at);
        $this->assertEquals([
            'kind' => $kind,
            'showing_id' => Showing::sole()->id,
            'name' => $other->name,
            'address' => "{$this->listing->address}, Denver, CO",
            'timezone' => 'America/Denver',
            'starts_at' => '2027-03-10T17:00:00Z',
            'ends_at' => '2027-03-10T17:30:00Z',
        ], $notification->data);
    }
}
