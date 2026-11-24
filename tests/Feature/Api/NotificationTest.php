<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ShowingChangedPush;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criterion 41. */
class NotificationTest extends TestCase
{
    use CallsApi, SchedulingScenario;

    public function test_booking_notifies_the_seller_who_reads_it(): void
    {
        $this->book();
        $this->assertTrue(DatabaseNotification::sole()->notifiable->is($this->seller));

        $answer = $this->api('GET', '/notifications', as: $this->seller)->assertOk()
            ->assertJsonPath('unread', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'booked')->assertJsonPath('data.0.read', false);
        $this->actingAs($this->seller)->get('/seller/notifications')
            ->assertInertia(fn ($page) => $page->where('notifications', $answer->json('data')));
        $this->api('GET', '/notifications', as: $this->buyer)->assertExactJson(['data' => [], 'unread' => 0]);

        $this->api('POST', '/notifications/read', as: $this->buyer)->assertNoContent();
        $this->api('GET', '/notifications', as: $this->seller)->assertJsonPath('unread', 1);

        $this->api('POST', '/notifications/read', as: $this->seller)->assertNoContent();
        $this->api('GET', '/notifications', as: $this->seller)->assertJsonPath('unread', 0)->assertJsonPath('data.0.read', true);
    }

    public function test_cancelling_notifies_the_other_side(): void
    {
        $first = $this->showing('10:00', '10:30', $this->buyer);
        $this->api('POST', "/buyer/showings/$first->id/cancel", as: $this->buyer)->assertOk();
        $this->assertTrue(DatabaseNotification::sole()->notifiable->is($this->seller));

        $second = $this->showing('11:00', '11:30', $this->buyer);
        $this->api('POST', "/seller/showings/$second->id/cancel", as: $this->seller)->assertOk();
        $this->assertSame(1, $this->buyer->notifications()->count());
    }

    public function test_a_refused_booking_notifies_nobody(): void
    {
        $this->window->update(['closed_at' => now()]);

        $this->api('POST', '/buyer/showings', ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:00:00Z'], $this->buyer)->assertConflict();

        $this->assertSame(0, DatabaseNotification::count());
    }

    public function test_push_is_queued_for_a_subscribed_browser_and_revoking_tokens_keeps_it(): void
    {
        Queue::fake();
        $this->seller->updatePushSubscription('https://fcm.googleapis.com/fcm/send/seller', 'key', 'token');
        $this->api('DELETE', '/tokens', as: $this->seller)->assertNoContent();

        $this->book();

        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof ShowingChangedPush
            && $job->notifiables->contains(fn (User $user) => $user->is($this->seller)));
    }

    public function test_it_lists_the_latest_fifty(): void
    {
        for ($i = 0; $i < 51; $i++) {
            $this->seller->notifications()->create(['id' => fake()->uuid(), 'type' => 'x', 'data' => ['kind' => 'booked']]);
        }

        $this->api('GET', '/notifications', as: $this->seller)->assertJsonCount(50, 'data')->assertJsonPath('unread', 51);
    }

    private function book(): void
    {
        $this->api('POST', '/buyer/showings', ['window_id' => $this->window->id, 'starts_at' => '2027-03-10T17:00:00Z'], $this->buyer)->assertCreated();
    }
}
