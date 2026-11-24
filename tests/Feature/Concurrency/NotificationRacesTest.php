<?php

namespace Tests\Feature\Concurrency;

use App\Enums\ShowingStatus;
use App\Models\Showing;
use Illuminate\Notifications\DatabaseNotification;
use PHPUnit\Framework\Attributes\TestWith;

/** The "1 notification" of criteria 9 and 13. */
class NotificationRacesTest extends ConcurrencyTestCase
{
    public function test_ten_buyers_on_one_slot_produce_one_notification(): void
    {
        $window = $this->window('10:00', '12:00');

        $this->race(...array_map(fn () => $this->book($this->buyer(), $window, '10:00'), range(1, 10)));

        $this->assertSame(1, $this->confirmed());
        $this->assertTrue(DatabaseNotification::sole()->notifiable->is($window->listing->seller));
    }

    #[TestWith([1])]
    #[TestWith([2])]
    #[TestWith([3])]
    public function test_both_sides_cancelling_at_once_produce_one_notification_for_the_other_side(int $round): void
    {
        $buyer = $this->buyer();
        $window = $this->window('10:00', '11:00');
        $showing = Showing::factory()->for($window, 'window')->for($buyer, 'buyer')->create([
            'starts_at' => $this->instant('10:00'),
            'ends_at' => $this->instant('10:30'),
        ]);

        $this->race(['cancel', $showing->id, 'buyer'], ['cancel', $showing->id, 'seller']);

        $notified = $showing->refresh()->status === ShowingStatus::CancelledByBuyer ? $window->listing->seller : $buyer;
        $this->assertTrue(DatabaseNotification::sole()->notifiable->is($notified));
    }
}
