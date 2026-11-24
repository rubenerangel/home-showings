<?php

namespace Tests\Feature\Push;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use NotificationChannels\WebPush\PushSubscription;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Criterion 25: who a browser is subscribed for, and when that ends. */
class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/this-browser';

    private const ELSEWHERE = 'https://fcm.googleapis.com/fcm/send/another-browser';

    public function test_a_browser_is_subscribed_for_the_signed_in_user(): void
    {
        $user = User::factory()->seller()->create();

        $this->actingAs($user);
        $this->subscribe()->assertNoContent();
        $this->subscribe()->assertNoContent();

        $subscription = PushSubscription::sole();
        $this->assertTrue($subscription->subscribable->is($user));
        $this->assertSame(self::ENDPOINT, $subscription->endpoint);
        $this->assertSame(str_repeat('p', 87), $subscription->public_key);
        $this->assertSame(str_repeat('a', 22), $subscription->auth_token);
    }

    public function test_a_browser_belongs_to_one_user_only(): void
    {
        $first = User::factory()->buyer()->create();
        $second = User::factory()->seller()->create();
        $first->updatePushSubscription(self::ENDPOINT, 'key', 'token');

        $this->actingAs($second);
        $this->subscribe()->assertNoContent();

        $this->assertTrue(PushSubscription::sole()->subscribable->is($second));
    }

    public function test_turning_it_off_removes_only_the_own_subscription_of_that_browser(): void
    {
        $user = User::factory()->buyer()->create();
        $user->updatePushSubscription(self::ENDPOINT, 'key', 'token');
        $user->updatePushSubscription(self::ELSEWHERE, 'key', 'token');
        $other = User::factory()->buyer()->create();
        $other->updatePushSubscription('https://fcm.googleapis.com/fcm/send/other', 'key', 'token');

        $this->actingAs($user)->deleteJson('/push-subscription', ['endpoint' => self::ENDPOINT])->assertNoContent();
        $this->actingAs($user)->deleteJson('/push-subscription', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/other'])->assertNoContent();

        $this->assertSame([self::ELSEWHERE], $user->pushSubscriptions()->pluck('endpoint')->all());
        $this->assertSame(1, $other->pushSubscriptions()->count());
    }

    // Switching user

    public function test_leaving_removes_the_subscription_of_this_browser_and_keeps_the_others(): void
    {
        $user = User::factory()->buyer()->create();
        $user->updatePushSubscription(self::ELSEWHERE, 'key', 'token');
        $this->actingAs($user);
        $this->subscribe();

        $this->delete('/session')->assertRedirect('/');

        $this->assertSame([self::ELSEWHERE], PushSubscription::pluck('endpoint')->all());
    }

    public function test_logging_in_as_someone_else_without_leaving_removes_it_too(): void
    {
        $this->actingAs(User::factory()->buyer()->create());
        $this->subscribe();
        $other = User::factory()->seller()->create(['password' => 'a long password']);

        $this->post('/session', ['email' => $other->email, 'password' => 'a long password'])->assertRedirect('/seller');

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_failing_to_log_in_as_someone_else_keeps_it(): void
    {
        $this->actingAs(User::factory()->buyer()->create());
        $this->subscribe();
        $other = User::factory()->seller()->create(['password' => 'a long password']);

        $this->post('/session', ['email' => $other->email, 'password' => 'another password'])->assertSessionHasErrors('email');

        $this->assertSame([self::ENDPOINT], PushSubscription::pluck('endpoint')->all());
    }

    public function test_leaving_does_not_remove_a_browser_that_already_belongs_to_someone_else(): void
    {
        $first = User::factory()->buyer()->create();
        $this->actingAs($first);
        $this->subscribe();
        $second = User::factory()->buyer()->create();
        $second->updatePushSubscription(self::ENDPOINT, 'key', 'token');

        $this->delete('/session');

        $this->assertTrue(PushSubscription::sole()->subscribable->is($second));
    }

    // Input: the server sends requests to this address, so only known push services are accepted

    #[TestWith(['https://fcm.googleapis.com/fcm/send/x'])]
    #[TestWith(['https://updates.push.services.mozilla.com/wpush/v2/x'])]
    #[TestWith(['https://wns2-by3p.notify.windows.com/w/?token=x'])]
    #[TestWith(['https://web.push.apple.com/x'])]
    public function test_known_push_services_are_accepted(string $endpoint): void
    {
        $this->actingAs(User::factory()->buyer()->create());

        $this->subscribe(['endpoint' => $endpoint])->assertNoContent();
    }

    #[TestWith(['http://fcm.googleapis.com/fcm/send/x'])]
    #[TestWith(['https://localhost/x'])]
    #[TestWith(['https://db:5432/x'])]
    #[TestWith(['https://example.com/fcm.googleapis.com'])]
    #[TestWith(['https://fcm.googleapis.com.example.com/x'])]
    #[TestWith(['https://evilfcm.googleapis.com/x'])]
    #[TestWith(['https://evilnotify.windows.com/x'])]
    #[TestWith(['https://fcm.googleapis.com:8443/x'])]
    #[TestWith(['https://fcm.googleapis.com@example.com/x'])]
    #[TestWith(['not a url'])]
    public function test_any_other_address_is_rejected(string $endpoint): void
    {
        $this->actingAs(User::factory()->buyer()->create());

        $this->subscribe(['endpoint' => $endpoint])->assertJsonValidationErrors('endpoint');

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_the_keys_are_required_and_have_the_right_length(): void
    {
        $this->actingAs(User::factory()->buyer()->create());

        $this->subscribe(['keys' => []])->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
        $this->subscribe(['keys' => ['p256dh' => 'short', 'auth' => 'short']])->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    }

    #[TestWith(['POST'])]
    #[TestWith(['DELETE'])]
    public function test_a_session_is_required(string $method): void
    {
        $this->call($method, '/push-subscription')->assertRedirect('/login');
    }

    #[TestWith(['seller'])]
    #[TestWith(['buyer'])]
    public function test_the_notifications_page_carries_the_public_key(string $role): void
    {
        config(['webpush.vapid.public_key' => 'the-public-key', 'webpush.vapid.private_key' => 'the-private-key']);

        $this->actingAs(User::factory()->create(['role' => $role]))->get("/$role/notifications")
            ->assertInertia(fn (Assert $page) => $page->where('pushPublicKey', 'the-public-key'))
            ->assertDontSee('the-private-key');
    }

    /** @param array<string, mixed> $input */
    private function subscribe(array $input = []): TestResponse
    {
        return $this->postJson('/push-subscription', [
            'endpoint' => self::ENDPOINT,
            'keys' => ['p256dh' => str_repeat('p', 87), 'auth' => str_repeat('a', 22)],
            ...$input,
        ]);
    }
}
