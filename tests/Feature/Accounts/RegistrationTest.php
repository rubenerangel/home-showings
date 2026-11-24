<?php

namespace Tests\Feature\Accounts;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Criteria 27, 28, 31 and 32. */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a long password';

    // Criterion 27

    public function test_the_page_is_open_to_guests(): void
    {
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Register'));
    }

    #[TestWith(['seller'])]
    #[TestWith(['buyer'])]
    public function test_registering_creates_the_account_and_opens_the_area_of_the_role(string $role): void
    {
        $this->register(['role' => $role])->assertSessionHasNoErrors()->assertRedirect("/$role");

        $user = User::sole();
        $this->assertSame('Ada Stone', $user->name);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame(Role::from($role), $user->role);
        $this->assertAuthenticatedAs($user);
        $this->get("/$role")->assertOk();
    }

    public function test_the_session_id_changes(): void
    {
        $id = Str::random(40);
        $this->withCookie(config('session.cookie'), $id)->get('/register');
        $this->assertSame($id, session()->getId());

        $this->register()->assertRedirect('/buyer');

        $this->assertNotSame($id, session()->getId());
    }

    public function test_the_new_account_can_log_in(): void
    {
        $this->register();
        $this->delete('/session');
        $this->assertGuest();

        $this->post('/session', ['email' => 'ada@example.com', 'password' => self::PASSWORD])->assertRedirect('/buyer');

        $this->assertAuthenticatedAs(User::sole());
    }

    public function test_the_email_is_kept_in_lower_case_and_trimmed(): void
    {
        $this->register(['email' => ' Ada@Example.COM '])->assertRedirect('/buyer');

        $this->assertSame('ada@example.com', User::sole()->email);
    }

    #[TestWith([8])]
    #[TestWith([72])]
    public function test_passwords_at_both_limits_are_accepted(int $length): void
    {
        $password = str_repeat('x', $length);

        $this->register(['password' => $password, 'password_confirmation' => $password])->assertRedirect('/buyer');

        $this->assertTrue(Hash::check($password, User::sole()->password));
    }

    // Criterion 28

    /** @param array<string, mixed> $input */
    #[DataProvider('invalid')]
    public function test_invalid_input_is_refused_and_writes_nothing(string $field, array $input): void
    {
        $this->from('/register')->register($input)->assertRedirect('/register')->assertSessionHasErrors($field);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function invalid(): array
    {
        $password = fn (mixed $value) => ['password' => $value, 'password_confirmation' => $value];

        return [
            'no name' => ['name', ['name' => ' ']],
            'name too long' => ['name', ['name' => str_repeat('x', 256)]],
            'name as a list' => ['name', ['name' => ['Ada']]],
            'name with a null byte' => ['name', ['name' => "Ada\0Stone"]],
            'name that is not UTF-8' => ['name', ['name' => "Ada\xff"]],
            'no email' => ['email', ['email' => '']],
            'not an email' => ['email', ['email' => 'ada']],
            'email too long' => ['email', ['email' => str_repeat('x', 250).'@example.com']],
            'email as a list' => ['email', ['email' => ['ada@example.com']]],
            'email with a null byte' => ['email', ['email' => "ada\0@example.com"]],
            'email outside ASCII' => ['email', ['email' => 'İda@example.com']],
            'no password' => ['password', $password('')],
            'password of 7' => ['password', $password('1234567')],
            'password of 73 bytes' => ['password', $password(str_repeat('x', 73))],
            'password of 74 bytes in 37 characters' => ['password', $password(str_repeat('é', 37))],
            'password as a list' => ['password', $password(['a long password'])],
            'password with a null byte' => ['password', $password("a long\0password")],
            'not confirmed' => ['password', ['password_confirmation' => null]],
            'confirmed with another' => ['password', ['password_confirmation' => 'another password']],
            'no role' => ['role', ['role' => null]],
            'unknown role' => ['role', ['role' => 'admin']],
            'role as a list' => ['role', ['role' => ['seller']]],
        ];
    }

    #[TestWith(['ada@example.com'])]
    #[TestWith(['ADA@example.com'])]
    public function test_a_taken_email_is_refused(string $email): void
    {
        $owner = User::factory()->seller()->create(['email' => 'ada@example.com']);

        $this->register(['email' => $email])->assertSessionHasErrors(['email' => 'The email has already been taken.']);

        $this->assertTrue(User::sole()->is($owner));
        $this->assertGuest();
    }

    /** Both passed the validation, and the other one was written first. */
    public function test_an_email_taken_in_the_meantime_is_refused_with_the_same_message(): void
    {
        User::creating(fn () => DB::table('users')->insert([
            'name' => 'Someone else', 'email' => 'ada@example.com', 'password' => 'x', 'role' => 'buyer',
        ]));

        $this->register()->assertSessionHasErrors(['email' => 'The email has already been taken.']);

        $this->assertGuest();
    }

    /** @param array<string, mixed> $columns */
    #[TestWith(['23505', ['email' => 'ada@example.com']], 'same email')]
    #[TestWith(['23514', ['email' => 'Eve@example.com']], 'upper case')]
    #[TestWith(['23502', ['email' => null]], 'no email')]
    #[TestWith(['23502', ['email' => 'eve@example.com', 'password' => null]], 'no password')]
    public function test_the_database_rejects_it_too(string $state, array $columns): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        try {
            DB::table('users')->insert(['name' => 'Eve', 'role' => 'buyer', 'password' => 'x', ...$columns]);
            $this->fail('It was accepted.');
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->getCode());
        }
    }

    // Criterion 31

    public function test_the_password_is_hashed_and_never_reaches_the_client(): void
    {
        $this->register();

        $hash = DB::table('users')->value('password');
        $this->assertNotSame(self::PASSWORD, $hash);
        $this->assertTrue(Hash::check(self::PASSWORD, $hash));
        $this->assertArrayNotHasKey('password', User::sole()->toArray());
        $this->get('/buyer')
            ->assertInertia(fn (Assert $page) => $page->where('user', ['id' => User::sole()->id, 'name' => 'Ada Stone', 'role' => 'buyer']))
            ->assertDontSee($hash);
    }

    public function test_a_refused_password_is_not_kept_in_the_session(): void
    {
        $this->register(['role' => 'admin']);

        $this->assertStringNotContainsString(self::PASSWORD, json_encode(session()->all()));
    }

    // Criterion 32

    #[TestWith(['seller'])]
    #[TestWith(['buyer'])]
    public function test_with_a_session_it_redirects_to_the_area_and_writes_nothing(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        $this->get('/register')->assertRedirect("/$role");
        $this->register()->assertRedirect("/$role");

        $this->assertTrue(User::sole()->is($user));
        $this->assertAuthenticatedAs($user);
    }

    /** @param array<string, mixed> $input */
    private function register(array $input = []): TestResponse
    {
        return $this->post('/register', [
            'name' => 'Ada Stone',
            'email' => 'ada@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => 'buyer',
            ...$input,
        ]);
    }
}
