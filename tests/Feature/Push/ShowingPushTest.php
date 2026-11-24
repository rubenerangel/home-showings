<?php

namespace Tests\Feature\Push;

use App\Enums\ShowingEvent;
use App\Enums\ShowingStatus;
use App\Models\Showing;
use App\Models\User;
use App\Notifications\ShowingChangedPush;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Mockery;
use NotificationChannels\WebPush\WebPushChannel;
use RuntimeException;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criterion 25: each side gets only its own pushes, queued with the action and sent by the worker. */
class ShowingPushTest extends TestCase
{
    use SchedulingScenario;

    public function test_a_booking_is_queued_for_the_seller(): void
    {
        Queue::fake();
        $this->subscribeEverybody();

        $this->book()->assertSessionHasNoErrors();

        $this->assertQueuedOnlyFor($this->seller);
    }

    public function test_a_cancellation_by_the_buyer_is_queued_for_the_seller(): void
    {
        Queue::fake();
        $this->subscribeEverybody();

        $this->cancel('buyer', $this->showing('10:00', '10:30', $this->buyer))->assertSessionHasNoErrors();

        $this->assertQueuedOnlyFor($this->seller);
    }

    public function test_a_cancellation_by_the_seller_is_queued_for_the_buyer(): void
    {
        Queue::fake();
        $this->subscribeEverybody();

        $this->cancel('seller', $this->showing('10:00', '10:30', $this->buyer))->assertSessionHasNoErrors();

        $this->assertQueuedOnlyFor($this->buyer);
    }

    public function test_nothing_is_queued_for_someone_without_a_subscription(): void
    {
        Queue::fake();
        $this->buyer->updatePushSubscription('https://fcm.googleapis.com/fcm/send/buyer', 'key', 'token');

        $this->book()->assertSessionHasNoErrors();

        Queue::assertNothingPushed();
        $this->assertSame(1, DatabaseNotification::count());
    }

    public function test_nothing_is_queued_when_the_action_is_refused(): void
    {
        Queue::fake();
        $this->subscribeEverybody();
        $this->showing('10:00', '10:30');

        $this->book()->assertSessionHasErrors('refusal');
        $this->cancel('buyer', $this->showing('11:00', '11:30', $this->buyer, status: ShowingStatus::CancelledByBuyer))
            ->assertSessionHasErrors('refusal');

        Queue::assertNothingPushed();
    }

    public function test_the_job_is_written_with_the_showing_or_not_at_all(): void
    {
        config(['queue.default' => 'database']);
        $this->subscribeEverybody();

        $this->app['events']->listen('eloquent.created: '.DatabaseNotification::class, fn () => throw new RuntimeException('Failed.'));
        $this->book()->assertServerError();
        $this->assertSame([0, 0], [Showing::count(), DB::table('jobs')->count()]);

        $this->app['events']->forget('eloquent.created: '.DatabaseNotification::class);
        $this->book()->assertSessionHasNoErrors();
        $this->assertSame([1, 1], [Showing::count(), DB::table('jobs')->count()]);
    }

    public function test_it_is_delivered_to_every_browser_of_that_user_and_to_no_other(): void
    {
        $this->subscribeEverybody();
        $this->seller->updatePushSubscription('https://fcm.googleapis.com/fcm/send/seller-phone', 'key', 'token');
        $sent = $this->captureDeliveries();

        $this->book()->assertSessionHasNoErrors();

        $this->assertSame([
            'https://fcm.googleapis.com/fcm/send/seller',
            'https://fcm.googleapis.com/fcm/send/seller-phone',
        ], array_keys($sent->getArrayCopy()));
    }

    public function test_the_message_says_what_happened_and_leads_to_the_own_list(): void
    {
        $showing = $this->showing('10:00', '10:30', $this->buyer);
        $place = "{$this->listing->address}, Denver, CO. Wed, Mar 10, 10:00 AM MST";

        $this->assertSame(
            ['New showing booked', "{$this->buyer->name}: $place", '/seller/notifications'],
            $this->message($showing, ShowingEvent::Booked, $this->seller),
        );
        $this->assertSame(
            ['Showing cancelled by the buyer', "{$this->buyer->name}: $place", '/seller/notifications'],
            $this->message($showing, ShowingEvent::CancelledByBuyer, $this->seller),
        );
        $this->assertSame(
            ['Showing cancelled by the seller', "{$this->seller->name}: $place", '/buyer/notifications'],
            $this->message($showing, ShowingEvent::CancelledBySeller, $this->buyer),
        );
    }

    /** @return array{string, string, string} */
    private function message(Showing $showing, ShowingEvent $event, User $user): array
    {
        $message = (new ShowingChangedPush($showing, $event))->toWebPush($user)->toArray();

        return [$message['title'], $message['body'], $message['data']['url']];
    }

    private function subscribeEverybody(): void
    {
        $this->seller->updatePushSubscription('https://fcm.googleapis.com/fcm/send/seller', 'key', 'token');
        $this->buyer->updatePushSubscription('https://fcm.googleapis.com/fcm/send/buyer', 'key', 'token');
        User::factory()->seller()->create()->updatePushSubscription('https://fcm.googleapis.com/fcm/send/other-seller', 'key', 'token');
        User::factory()->buyer()->create()->updatePushSubscription('https://fcm.googleapis.com/fcm/send/other-buyer', 'key', 'token');
    }

    private function assertQueuedOnlyFor(User $user): void
    {
        Queue::assertPushedTimes(SendQueuedNotifications::class, 1);
        Queue::assertPushed(fn (SendQueuedNotifications $job) => $job->notification instanceof ShowingChangedPush
            && $job->channels === [WebPushChannel::class]
            && $job->notifiables->count() === 1
            && $job->notifiables->first()->is($user));
    }

    /** @return \ArrayObject<string, string> payload by endpoint, filled as the channel sends */
    private function captureDeliveries(): \ArrayObject
    {
        $sent = new \ArrayObject;
        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->andReturnUsing(
            fn (Subscription $subscription, string $payload) => $sent[$subscription->getEndpoint()] = $payload,
        );
        $webPush->shouldReceive('flush')->andReturnUsing(fn () => (function () {
            yield from [];
        })());
        $this->app->when(WebPushChannel::class)->needs(WebPush::class)->give(fn () => $webPush);

        return $sent;
    }

    private function book(): TestResponse
    {
        return $this->actingAs(User::findOrFail($this->buyer->id))->post('/buyer/showings', [
            'window_id' => $this->window->id,
            'starts_at' => $this->instant('10:00')->toIso8601ZuluString(),
        ]);
    }

    private function cancel(string $side, Showing $showing): TestResponse
    {
        $actor = $side === 'buyer' ? $this->buyer : $this->seller;

        return $this->actingAs(User::findOrFail($actor->id))->post("/$side/showings/{$showing->id}/cancel");
    }
}
