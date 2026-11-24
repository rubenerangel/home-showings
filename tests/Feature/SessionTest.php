<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Criteria 29, 30 and 32. */
class SessionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a long password';

    private const WRONG = 'The email or the password is not right.';

    public function test_the_page_is_open_to_guests(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Login'));
    }

    // Criterion 29

    #[DataProvider('roles')]
    public function test_logging_in_opens_the_area_of_the_role(string $role): void
    {
        $user = $this->user(['role' => $role]);

        $this->logIn()->assertSessionHasNoErrors()->assertRedirect("/$role");

        $this->assertAuthenticatedAs($user);
        $this->get("/$role")->assertOk();
    }

    public function test_the_case_of_the_email_does_not_matter(): void
    {
        $user = $this->user();

        $this->logIn(['email' => ' Ada@Example.COM '])->assertRedirect('/buyer');

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_session_id_changes(): void
    {
        $this->user();
        $id = Str::random(40);
        $this->withCookie(config('session.cookie'), $id)->get('/');
        $this->assertSame($id, session()->getId());

        $this->logIn()->assertRedirect('/buyer');

        $this->assertNotSame($id, session()->getId());
    }

    /** @param array<string, mixed> $input */
    #[TestWith([['password' => 'another password']], 'wrong password')]
    #[TestWith([['password' => 'A long password']], 'another case')]
    #[TestWith([['email' => 'eve@example.com']], 'unknown email')]
    public function test_a_wrong_password_and_an_unknown_email_give_the_same_message(array $input): void
    {
        $this->user();

        $this->from('/')->logIn($input)->assertRedirect('/')->assertSessionHasErrors(['email' => self::WRONG]);

        $this->assertGuest();
    }

    /** @param array<string, mixed> $input */
    #[TestWith(['email', ['email' => '']], 'no email')]
    #[TestWith(['email', ['email' => ['ada@example.com']]], 'email as a list')]
    #[TestWith(['email', ['email' => "ada\0@example.com"]], 'email with a null byte')]
    #[TestWith(['email', ['email' => "ada\xff@example.com"]], 'email that is not UTF-8')]
    #[TestWith(['password', ['password' => '']], 'no password')]
    #[TestWith(['password', ['password' => ['a long password']]], 'password as a list')]
    public function test_invalid_input_is_refused(string $field, array $input): void
    {
        $this->user();

        $this->from('/')->logIn($input)->assertRedirect('/')->assertSessionHasErrors($field);

        $this->assertGuest();
    }

    public function test_an_id_without_credentials_is_not_enough(): void
    {
        $this->post('/session', ['user_id' => $this->user()->id])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    // Criterion 30

    public function test_after_five_failures_even_the_right_password_is_refused_for_a_minute(): void
    {
        $this->freezeSecond();
        $user = $this->user();
        $this->failFiveTimes();

        $this->logIn()->assertSessionHasErrors(['email' => 'Too many attempts. Try again in a minute.']);
        $this->assertGuest();

        $this->travel(59)->seconds();
        $this->logIn(['email' => 'ADA@example.com'])->assertSessionHasErrors(['email' => 'Too many attempts. Try again in a minute.']);
        $this->assertGuest();

        $this->travel(2)->seconds();
        $this->logIn()->assertRedirect('/buyer');
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_limit_is_per_email_and_address(): void
    {
        $this->user();
        $other = $this->user(['email' => 'eve@example.com']);
        $this->failFiveTimes();

        $this->logIn(['email' => 'eve@example.com'])->assertRedirect('/buyer');
        $this->assertAuthenticatedAs($other);
        $this->delete('/session');

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->logIn()->assertRedirect('/buyer');
    }

    public function test_the_limit_also_holds_for_an_email_without_an_account(): void
    {
        $this->freezeSecond();
        $this->failFiveTimes();

        $this->logIn()->assertSessionHasErrors(['email' => 'Too many attempts. Try again in a minute.']);
    }

    public function test_logging_in_starts_the_count_again(): void
    {
        $this->user();

        foreach ([1, 2] as $_) {
            for ($i = 0; $i < 4; $i++) {
                $this->logIn(['password' => 'another password'])->assertSessionHasErrors(['email' => self::WRONG]);
            }
            $this->logIn()->assertRedirect('/buyer');
            $this->delete('/session');
        }
    }

    // Criterion 32

    #[DataProvider('roles')]
    public function test_with_a_session_the_page_redirects_to_the_area(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))->get('/')->assertRedirect("/$role");
    }

    public function test_failing_to_log_in_as_someone_else_keeps_the_session(): void
    {
        $this->user();
        $current = User::factory()->seller()->create();
        $this->actingAs($current);

        $this->logIn(['password' => 'another password'])->assertSessionHasErrors(['email' => self::WRONG]);

        $this->assertAuthenticatedAs($current);
    }

    // R10

    #[DataProvider('roles')]
    public function test_without_a_session_both_areas_redirect_to_the_login(string $role): void
    {
        $this->get("/$role")->assertRedirect('/');
    }

    public function test_the_area_of_the_other_role_is_forbidden(): void
    {
        $this->actingAs(User::factory()->buyer()->create())->get('/seller')->assertForbidden();
        $this->actingAs(User::factory()->seller()->create())->get('/buyer')->assertForbidden();
    }

    public function test_leaving_ends_the_session(): void
    {
        $this->actingAs(User::factory()->buyer()->create())->delete('/session')->assertRedirect('/');
        $this->assertGuest();
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['seller' => ['seller'], 'buyer' => ['buyer']];
    }

    /** @param array<string, mixed> $attributes */
    private function user(array $attributes = []): User
    {
        return User::factory()->buyer()->create(['email' => 'ada@example.com', 'password' => self::PASSWORD, ...$attributes]);
    }

    /** @param array<string, mixed> $input */
    private function logIn(array $input = []): TestResponse
    {
        return $this->post('/session', ['email' => 'ada@example.com', 'password' => self::PASSWORD, ...$input]);
    }

    private function failFiveTimes(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->logIn(['password' => 'another password'])->assertSessionHasErrors(['email' => self::WRONG]);
        }
    }
}
