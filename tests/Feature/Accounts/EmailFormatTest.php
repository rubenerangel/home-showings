<?php

namespace Tests\Feature\Accounts;

use App\Accounts\EmailFormat;
use App\Accounts\RegisterDevice;
use App\Accounts\RegisterUser;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\Api\CallsApi;
use Tests\TestCase;

/** Criteria 62 to 67: an email that is not complete (R26) never gets into the table, whoever writes it. */
class EmailFormatTest extends TestCase
{
    use CallsApi, RefreshDatabase;

    private const MESSAGE = 'Enter a complete email, like name@example.com.';

    private const REGISTRATION = [
        'name' => 'Ada Stone', 'email' => 'test@testco', 'password' => 'a long password',
        'password_confirmation' => 'a long password', 'role' => 'buyer',
    ];

    // Criteria 62 and 65

    #[DataProvider('complete')]
    public function test_the_server_and_the_database_accept_a_complete_email(string $email): void
    {
        $this->assertTrue(EmailFormat::isComplete($email));

        DB::table('users')->insert($this->row($email));

        $this->assertSame($email, DB::table('users')->value('email'));
    }

    #[DataProvider('incomplete')]
    public function test_the_server_and_the_database_refuse_one_that_is_not(string $email): void
    {
        $this->assertFalse(EmailFormat::isComplete($email));

        $this->assertRefusedByTheDatabase(fn () => DB::table('users')->insert($this->row($email)));
        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('incomplete')]
    public function test_the_database_refuses_changing_it_to_one_that_is_not(string $email): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->assertRefusedByTheDatabase(fn () => DB::table('users')->update(['email' => $email]));
        $this->assertSame('ada@example.com', $user->refresh()->email);
    }

    /** @return array<string, array{string}> */
    public static function complete(): array
    {
        return self::cases('complete');
    }

    /** @return array<string, array{string}> */
    public static function incomplete(): array
    {
        return self::cases('incomplete');
    }

    // Criterion 63

    #[TestWith(['test@testco'])]
    #[TestWith([' Test@TestCo '])]
    #[TestWith(['ada@example.c'])]
    #[TestWith(['"ada stone"@example.com'])]
    #[TestWith(['ada@1.2.3.4'])]
    public function test_registering_says_which_field_and_writes_nothing(string $email): void
    {
        $this->from('/register')->post('/register', [...self::REGISTRATION, 'email' => $email])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => self::MESSAGE]);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_it_is_said_together_with_the_other_fields(): void
    {
        $this->post('/register', [...self::REGISTRATION, 'name' => '', 'password' => 'short'])
            ->assertSessionHasErrors(['email' => self::MESSAGE, 'name', 'password']);
    }

    public function test_an_invisible_character_around_it_is_removed(): void
    {
        $this->post('/register', [...self::REGISTRATION, 'email' => "\u{FEFF}ada@example.com\u{200B}"])->assertRedirect('/buyer');

        $this->assertSame('ada@example.com', User::sole()->email);
    }

    /** The last label is complete for R26, and too long for the rule that logging in has. */
    public function test_an_email_that_could_not_log_in_is_not_registered(): void
    {
        $this->post('/register', [...self::REGISTRATION, 'email' => 'ada@example.'.str_repeat('c', 63)])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registering_by_api_too(): void
    {
        $this->api('POST', '/register', [...self::REGISTRATION, 'device_name' => 'phone'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email', [self::MESSAGE]);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // Criterion 64: the validation of the server is out of the way.

    public function test_the_action_alone_is_stopped_by_the_database(): void
    {
        $this->assertSaysIt(fn () => app(RegisterUser::class)->handle($this->row('test@testco')));
    }

    public function test_and_so_is_the_one_that_gives_a_token(): void
    {
        $this->assertSaysIt(fn () => app(RegisterDevice::class)->handle($this->row('test@testco'), 'phone'));

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_an_email_that_gets_past_the_server_is_an_error_of_the_field_in_the_page(): void
    {
        User::creating(fn (User $user) => $user->email = 'test@testco');

        $this->from('/register')->post('/register', [...self::REGISTRATION, 'email' => 'ada@example.com'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => self::MESSAGE]);

        $this->assertGuest();
    }

    public function test_and_in_the_api(): void
    {
        User::creating(fn (User $user) => $user->email = 'test@testco');

        $this->api('POST', '/register', [...self::REGISTRATION, 'email' => 'ada@example.com', 'device_name' => 'phone'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email', [self::MESSAGE]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_another_constraint_is_not_taken_for_an_email(): void
    {
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_name_check CHECK (name <> 'Ada Stone')");

        $this->expectException(QueryException::class);

        app(RegisterUser::class)->handle($this->row('ada@example.com'));
    }

    /** PostgreSQL writes the refused row below the name of the constraint. */
    public function test_a_name_does_not_pass_for_the_constraint(): void
    {
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_name_check CHECK (char_length(name) < 10)');

        $this->expectException(QueryException::class);

        app(RegisterUser::class)->handle([...$this->row('ada@example.com'), 'name' => '"users_email_check"']);
    }

    // Criterion 65

    public function test_javascript_runs_the_real_pattern(): void
    {
        $this->assertSame(EmailFormat::PATTERN, self::shared()['pattern']);
    }

    public function test_the_database_has_the_same_pattern(): void
    {
        $definition = DB::scalar('SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname = ?', [EmailFormat::CONSTRAINT]);

        $this->assertStringContainsString("~ '".str_replace("'", "''", EmailFormat::PATTERN)."'", $definition);
    }

    public function test_the_page_receives_the_same_rule(): void
    {
        $this->get('/register')->assertInertia(fn (Assert $page) => $page
            ->where('emailFormat', ['pattern' => EmailFormat::PATTERN, 'message' => self::MESSAGE]));
    }

    // Criterion 66

    public function test_logging_in_with_one_is_an_unknown_email(): void
    {
        $this->post('/session', ['email' => 'test@testco', 'password' => 'a long password'])
            ->assertSessionHasErrors(['email' => 'The email or the password is not right.']);
    }

    public function test_logging_in_with_what_is_not_an_email_is_still_invalid(): void
    {
        $this->post('/session', ['email' => 'ada', 'password' => 'a long password'])
            ->assertSessionHasErrors(['email' => 'The email field must be a valid email address.']);
    }

    // Criterion 67

    public function test_the_migration_keeps_the_complete_emails(): void
    {
        $this->migration()->down();
        DB::table('users')->insert($this->row('ada@example.com'));

        $this->migration()->up();

        $this->assertSame('ada@example.com', DB::table('users')->value('email'));
        $this->assertRefusedByTheDatabase(fn () => DB::table('users')->insert($this->row('test@testco')));
    }

    public function test_the_migration_fails_and_changes_nothing_if_there_is_one_that_is_not(): void
    {
        $this->migration()->down();
        DB::table('users')->insert($this->row('test@testco'));

        $this->assertRefusedByTheDatabase(fn () => $this->migration()->up());

        $this->assertSame('test@testco', DB::table('users')->value('email'));
        $this->assertRefusedByTheDatabase(fn () => DB::table('users')->insert($this->row('Eve@example.com')));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_24_000002_check_the_format_of_emails.php');
    }

    /** @return array<string, string> */
    private function row(string $email): array
    {
        return ['name' => 'Ada Stone', 'email' => $email, 'password' => 'a long password', 'role' => 'buyer'];
    }

    /** In a savepoint, so the rest of the test can still read. */
    private function assertRefusedByTheDatabase(callable $write): void
    {
        try {
            DB::transaction($write);
            $this->fail('It was accepted.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
            $this->assertStringContainsString('users_email_check', $exception->getMessage());
        }
    }

    private function assertSaysIt(callable $register): void
    {
        try {
            DB::transaction($register);
            $this->fail('It was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['email' => [self::MESSAGE]], $exception->errors());
        }
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * The same list that JavaScript runs.
     *
     * @return array<string, array{string}>
     */
    private static function cases(string $kind): array
    {
        $emails = self::shared()[$kind];

        return array_combine(array_map(json_encode(...), $emails), array_map(fn (string $email) => [$email], $emails));
    }

    /** @return array{pattern: string, complete: list<string>, incomplete: list<string>} */
    private static function shared(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../../resources/js/emailFormat.cases.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
