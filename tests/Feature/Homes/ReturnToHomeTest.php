<?php

namespace Tests\Feature\Homes;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criterion 53: from a home, through the account, and back. */
class ReturnToHomeTest extends TestCase
{
    use SchedulingScenario;

    #[TestWith(['/register', 'Register'])]
    #[TestWith(['/login', 'Login'])]
    public function test_the_page_knows_the_home(string $path, string $component): void
    {
        $this->get("$path?home={$this->listing->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component($component)->where('home', ['id' => $this->listing->id, 'address' => $this->listing->address]));
        $this->get($path)->assertInertia(fn (Assert $page) => $page->where('home', null));
    }

    #[TestWith(['/register'])]
    #[TestWith(['/login'])]
    public function test_a_home_that_cannot_be_visited_is_ignored(string $path): void
    {
        $this->window->update(['closed_at' => now()]);
        foreach ([$this->listing->id, 999999, 'abc', '0', '9999999999999999999999', '//evil.example', '-1', '1.5'] as $home) {
            $this->get("$path?home=$home")->assertOk()->assertInertia(fn (Assert $page) => $page->where('home', null));
        }
        $this->get("$path?home[]=1")->assertOk()->assertInertia(fn (Assert $page) => $page->where('home', null));
    }

    public function test_a_buyer_goes_back_to_the_home(): void
    {
        $this->register(['home' => $this->listing->id])->assertRedirect("/homes/{$this->listing->id}");
        $this->delete('/session');

        $this->logIn(['home' => (string) $this->listing->id])->assertRedirect("/homes/{$this->listing->id}");
    }

    public function test_a_seller_goes_to_the_own_area(): void
    {
        $this->register(['home' => $this->listing->id, 'role' => 'seller'])->assertRedirect('/seller');
        $this->delete('/session');

        $this->logIn(['home' => $this->listing->id])->assertRedirect('/seller');
    }

    #[DataProvider('ignored')]
    public function test_anything_else_opens_the_usual_area(mixed $home): void
    {
        $this->register(['home' => $home])->assertSessionHasNoErrors()->assertRedirect('/buyer');
        $this->delete('/session');

        $this->logIn(['home' => $home])->assertSessionHasNoErrors()->assertRedirect('/buyer');
    }

    /** @return array<string, array{mixed}> */
    public static function ignored(): array
    {
        return [
            'nothing' => [null], 'missing' => [999999], 'malformed' => ['abc'], 'zero' => [0], 'too large' => ['9999999999999999999999'],
            'another site' => ['https://evil.example'], 'no scheme' => ['//evil.example'], 'a list' => [[1]], 'a boolean' => [true],
        ];
    }

    public function test_a_home_that_filled_up_in_the_meantime_opens_the_usual_area(): void
    {
        $this->get("/login?home={$this->listing->id}");
        $this->window->update(['closed_at' => now()]);
        User::factory()->buyer()->create(['email' => 'ada@example.com', 'password' => 'a long password']);

        $this->logIn(['home' => $this->listing->id])->assertRedirect('/buyer');
    }

    public function test_a_wrong_password_does_not_lose_the_home(): void
    {
        User::factory()->buyer()->create(['email' => 'ada@example.com', 'password' => 'a long password']);
        $from = "/login?home={$this->listing->id}";

        $this->from($from)->logIn(['home' => $this->listing->id, 'password' => 'another password'])->assertRedirect($from);
        $this->from($from)->logIn(['home' => $this->listing->id])->assertRedirect("/homes/{$this->listing->id}");
    }

    public function test_each_form_carries_its_own_home(): void
    {
        $other = Listing::factory()->create();
        $this->window('10:00', '11:00', $other);
        User::factory()->buyer()->create(['email' => 'ada@example.com', 'password' => 'a long password']);

        $this->get("/login?home={$this->listing->id}");
        $this->get("/login?home={$other->id}");

        $this->logIn(['home' => $this->listing->id])->assertRedirect("/homes/{$this->listing->id}");
    }

    public function test_an_abandoned_home_does_not_come_back(): void
    {
        User::factory()->buyer()->create(['email' => 'ada@example.com', 'password' => 'a long password']);
        $this->get("/login?home={$this->listing->id}");

        $this->logIn()->assertRedirect('/buyer');
    }

    #[TestWith(['/login'])]
    #[TestWith(['/register'])]
    public function test_with_a_session_a_buyer_goes_to_the_home_and_a_seller_to_the_area(string $path): void
    {
        $this->actingAs($this->buyer)->get("$path?home={$this->listing->id}")->assertRedirect("/homes/{$this->listing->id}");
        $this->actingAs($this->buyer)->get("$path?home=999999")->assertRedirect('/buyer');
        $this->actingAs($this->buyer)->get($path)->assertRedirect('/buyer');
        $this->actingAs($this->seller)->get("$path?home={$this->listing->id}")->assertRedirect('/seller');
    }

    /** @param array<string, mixed> $input */
    private function register(array $input): TestResponse
    {
        return $this->post('/register', [
            'name' => 'Ada Stone', 'email' => 'ada@example.com', 'password' => 'a long password',
            'password_confirmation' => 'a long password', 'role' => 'buyer', ...$input,
        ]);
    }

    /** @param array<string, mixed> $input */
    private function logIn(array $input = []): TestResponse
    {
        return $this->post('/session', ['email' => 'ada@example.com', 'password' => 'a long password', ...$input]);
    }
}
