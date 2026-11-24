<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

/** Criteria 34 and 35. */
class AccountTest extends TestCase
{
    use CallsApi, RefreshDatabase;

    private const REGISTRATION = [
        'name' => 'Ada Stone', 'email' => 'Ada@Example.com', 'password' => 'a long password',
        'password_confirmation' => 'a long password', 'role' => 'seller', 'device_name' => 'phone',
    ];

    private const LOGIN = ['email' => 'ada@example.com', 'password' => 'a long password', 'device_name' => 'phone'];

    private const WRONG = 'The email or the password is not right.';

    private const TOO_MANY = 'Too many attempts. Try again in a minute.';

    // Criterion 34

    public function test_registering_creates_the_account_and_a_token_and_opens_no_session(): void
    {
        $this->freezeSecond();
        $answer = $this->api('POST', '/register', self::REGISTRATION)->assertCreated();

        $user = User::sole();
        $this->assertSame(['Ada Stone', 'ada@example.com', Role::Seller], [$user->name, $user->email, $user->role]);
        $answer->assertExactJson(['data' => [
            'user' => ['id' => $user->id, 'name' => 'Ada Stone', 'email' => 'ada@example.com', 'role' => 'seller'],
            'token' => $answer->json('data.token'),
            'expires_at' => now()->addDays(30)->toIso8601ZuluString(),
        ]]);
        $this->assertSame([], $answer->headers->getCookies());
        $this->assertGuest('web');
        $this->api('GET', '/me', as: $answer->json('data.token'))->assertJsonPath('data.id', $user->id);
    }

    /** @param array<string, mixed> $input */
    #[DataProvider('invalid')]
    public function test_invalid_input_is_refused_and_writes_nothing(string $field, array $input): void
    {
        $this->api('POST', '/register', [...self::REGISTRATION, ...$input])->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function invalid(): array
    {
        return [
            'no name' => ['name', ['name' => null]],
            'name with a null byte' => ['name', ['name' => "Ada\0".'Stone']],
            'name as a number' => ['name', ['name' => 5]],
            'not an email' => ['email', ['email' => 'ada']],
            'email as a list' => ['email', ['email' => ['ada@example.com']]],
            'short password' => ['password', ['password' => '1234567', 'password_confirmation' => '1234567']],
            'password of 73 bytes' => ['password', ['password' => str_repeat('x', 73), 'password_confirmation' => str_repeat('x', 73)]],
            'not confirmed' => ['password', ['password_confirmation' => null]],
            'unknown role' => ['role', ['role' => 'admin']],
            'role as a boolean' => ['role', ['role' => true]],
            ...self::invalidDeviceNames(),
        ];
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function invalidDeviceNames(): array
    {
        return [
            'no device name' => ['device_name', ['device_name' => null]],
            'device name as a list' => ['device_name', ['device_name' => ['phone']]],
            'device name too long' => ['device_name', ['device_name' => str_repeat('x', 101)]],
            'device name with a null byte' => ['device_name', ['device_name' => "my\0phone"]],
        ];
    }

    public function test_a_taken_email_is_refused(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->api('POST', '/register', self::REGISTRATION)
            ->assertUnprocessable()->assertJsonValidationErrors(['email' => 'The email has already been taken.']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_email_taken_in_the_meantime_is_refused(): void
    {
        User::creating(fn () => DB::table('users')->insert([
            'name' => 'Someone else', 'email' => 'ada@example.com', 'password' => 'x', 'role' => 'buyer',
        ]));

        $this->api('POST', '/register', self::REGISTRATION)
            ->assertUnprocessable()->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function test_the_account_and_the_token_are_written_together_or_not_at_all(): void
    {
        PersonalAccessToken::creating(fn () => throw new RuntimeException('Interrupted'));

        $this->api('POST', '/register', self::REGISTRATION)->assertServerError()->assertExactJson(['message' => 'Server Error']);

        $this->assertDatabaseCount('users', 0);
    }

    // Criterion 35

    public function test_logging_in_gives_a_token_whatever_the_case_of_the_email(): void
    {
        $user = $this->user();

        $answer = $this->api('POST', '/tokens', [...self::LOGIN, 'email' => 'ADA@example.com'])->assertCreated();

        $this->assertSame(['user', 'token', 'expires_at'], array_keys($answer->json('data')));
        $this->assertSame([], $answer->headers->getCookies());
        $this->api('GET', '/me', as: $answer->json('data.token'))->assertJsonPath('data.id', $user->id);
    }

    public function test_the_same_device_name_gives_another_token(): void
    {
        $this->user();

        $first = $this->api('POST', '/tokens', self::LOGIN)->json('data.token');
        $second = $this->api('POST', '/tokens', self::LOGIN)->json('data.token');

        $this->api('GET', '/me', as: $first)->assertOk();
        $this->api('GET', '/me', as: $second)->assertOk();
    }

    public function test_a_new_token_removes_the_expired_ones_of_the_account(): void
    {
        $user = $this->user();
        $user->createToken('old', expiresAt: now()->subSecond());
        $other = User::factory()->create();
        $other->createToken('old', expiresAt: now()->subSecond());

        $this->api('POST', '/tokens', self::LOGIN)->assertCreated();

        $this->assertSame(['phone'], $user->tokens()->pluck('name')->all());
        $this->assertSame(1, $other->tokens()->count());
    }

    /** @param array<string, mixed> $input */
    #[TestWith([['password' => 'another password']])]
    #[TestWith([['email' => 'eve@example.com']])]
    public function test_a_wrong_password_and_an_unknown_email_give_the_same_answer(array $input): void
    {
        $this->user();

        $this->api('POST', '/tokens', [...self::LOGIN, ...$input])->assertUnprocessable()
            ->assertExactJson(['message' => self::WRONG, 'errors' => ['email' => [self::WRONG]]]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @param array<string, mixed> $input */
    #[DataProvider('invalidDeviceNames')]
    public function test_a_bad_device_name_is_not_an_attempt_and_says_nothing_about_the_password(string $field, array $input): void
    {
        $this->user();

        for ($i = 0; $i < 6; $i++) {
            $this->api('POST', '/tokens', [...self::LOGIN, 'password' => $i % 2 ? 'another password' : 'a long password', ...$input])
                ->assertUnprocessable()->assertOnlyJsonValidationErrors($field);
        }

        $this->api('POST', '/tokens', self::LOGIN)->assertCreated();
    }

    public function test_the_limit_of_failures_is_shared_with_the_web(): void
    {
        $this->freezeSecond();
        $this->user();
        for ($i = 0; $i < 5; $i++) {
            $i % 2
                ? $this->api('POST', '/tokens', [...self::LOGIN, 'password' => 'another password'])->assertUnprocessable()
                : $this->post('/session', [...self::LOGIN, 'password' => 'another password'])->assertSessionHasErrors('email');
        }

        $this->api('POST', '/tokens', self::LOGIN)->assertTooManyRequests()
            ->assertExactJson(['message' => self::TOO_MANY])->assertHeader('Retry-After', '60');
        $this->post('/session', self::LOGIN)->assertSessionHasErrors(['email' => self::TOO_MANY]);

        $this->travel(61)->seconds();
        $this->api('POST', '/tokens', self::LOGIN)->assertCreated();
    }

    public function test_guest_routes_ignore_the_token_and_the_session(): void
    {
        $this->user();
        $someone = User::factory()->create();

        $this->api('POST', '/tokens', self::LOGIN, $someone)->assertCreated()->assertJsonPath('data.user.email', 'ada@example.com');
        $this->actingAs($someone)->postJson('/api/v1/tokens', self::LOGIN)->assertCreated()->assertJsonPath('data.user.email', 'ada@example.com');
        $this->api('POST', '/tokens', self::LOGIN, '1|unknown')->assertCreated();
    }

    private function user(): User
    {
        return User::factory()->buyer()->create(['email' => 'ada@example.com', 'password' => 'a long password']);
    }
}
