<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Criterion 22: the server side of the list and of the unread count. */
class NotificationListTest extends TestCase
{
    use RefreshDatabase;

    private const DATA = [
        'kind' => 'booked',
        'showing_id' => 1,
        'name' => 'Someone',
        'address' => '1 Main St, Denver, CO',
        'timezone' => 'America/Denver',
        'starts_at' => '2027-03-10T17:00:00Z',
        'ends_at' => '2027-03-10T17:30:00Z',
    ];

    #[TestWith(['seller'])]
    #[TestWith(['buyer'])]
    public function test_the_list_shows_only_own_notifications_newest_first_with_the_unread_count(string $role): void
    {
        $this->freezeSecond();
        $user = User::factory()->create(['role' => $role]);
        $this->notify(User::factory()->create(['role' => $role]), now());
        $older = $this->notify($user, now()->subMinutes(2), read: true);
        $newer = $this->notify($user, now()->subMinute());

        $this->actingAs($user)->get("/$role/notifications")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Notifications')
            ->where('unreadNotifications', 1)
            ->has('notifications', 2)
            ->where('notifications.0', [
                'id' => $newer, ...self::DATA, 'read' => false, 'created_at' => now()->subMinute()->toIso8601ZuluString(),
            ])
            ->where('notifications.1.id', $older)
            ->where('notifications.1.read', true));
    }

    #[TestWith(['seller'])]
    #[TestWith(['buyer'])]
    public function test_reading_all_marks_only_own_notifications(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $other = User::factory()->create(['role' => $role]);
        $this->notify($user, now());
        $this->notify($user, now());
        $this->notify($other, now());

        $this->actingAs($user)->post("/$role/notifications/read")->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    #[TestWith(['seller'])]
    #[TestWith(['buyer'])]
    public function test_the_unread_count_reaches_every_page_of_the_area(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->notify($user, now());
        $this->notify($user, now());

        $this->actingAs($user)->get("/$role")->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 2));
    }

    #[TestWith(['GET', '/seller/notifications'])]
    #[TestWith(['POST', '/seller/notifications/read'])]
    #[TestWith(['GET', '/buyer/notifications'])]
    #[TestWith(['POST', '/buyer/notifications/read'])]
    public function test_a_session_of_that_role_is_required(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertRedirect('/login');

        $other = str_starts_with($uri, '/seller') ? 'buyer' : 'seller';
        $this->actingAs(User::factory()->create(['role' => $other]))->call($method, $uri)->assertForbidden();
    }

    private function notify(User $user, CarbonInterface $at, bool $read = false): string
    {
        $notification = $user->notifications()->forceCreate([
            'id' => (string) Str::uuid(),
            'type' => 'showing',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => self::DATA,
            'read_at' => $read ? $at : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return $notification->id;
    }
}
