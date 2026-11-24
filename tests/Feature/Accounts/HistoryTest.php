<?php

namespace Tests\Feature\Accounts;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Criterion 61: what someone saw is not left in the browser for the next person. */
class HistoryTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['/buyer'])]
    #[TestWith(['/buyer/availability'])]
    #[TestWith(['/buyer/showings'])]
    #[TestWith(['/buyer/notifications'])]
    #[TestWith(['/'])]
    public function test_with_a_session_pages_are_encrypted_in_the_history_and_not_kept_by_the_browser(string $path): void
    {
        $response = $this->actingAs(User::factory()->buyer()->create())->get($path)->assertOk();

        $this->assertTrue($this->page($response)['encryptHistory']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_the_pages_of_a_seller_are_too(): void
    {
        $listing = Listing::factory()->create();
        foreach (['/seller', "/seller/listings/$listing->id/edit", "/seller/listings/$listing->id/schedule"] as $path) {
            $response = $this->actingAs($listing->seller)->get($path)->assertOk();

            $this->assertTrue($this->page($response)['encryptHistory'], $path);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'), $path);
        }
    }

    public function test_a_redirect_with_a_session_is_not_kept_either(): void
    {
        $response = $this->actingAs(User::factory()->buyer()->create())->get('/login')->assertRedirect('/buyer');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    #[TestWith(['/'])]
    #[TestWith(['/login'])]
    #[TestWith(['/register'])]
    public function test_a_guest_page_can_be_kept(string $path): void
    {
        $this->assertStringNotContainsString('no-store', $this->get($path)->assertOk()->headers->get('Cache-Control'));
    }

    public function test_logging_out_ends_the_key_of_the_history(): void
    {
        $this->actingAs(User::factory()->buyer()->create())->delete('/session')->assertRedirect('/');

        $this->assertTrue($this->page($this->get('/'))['clearHistory']);
        $this->assertFalse($this->page($this->get('/'))['clearHistory'] ?? false);
    }

    public function test_logging_in_ends_the_key_of_whoever_was_here_before(): void
    {
        $user = User::factory()->buyer()->create(['password' => 'a long password']);
        $this->actingAs(User::factory()->seller()->create());

        $this->post('/session', ['email' => $user->email, 'password' => 'a long password'])->assertRedirect('/buyer');

        $this->assertTrue($this->page($this->get('/buyer'))['clearHistory']);
        $this->assertFalse($this->page($this->get('/buyer'))['clearHistory'] ?? false);
    }

    public function test_registering_ends_it_too(): void
    {
        $this->post('/register', [
            'name' => 'Ada Stone', 'email' => 'ada@example.com', 'password' => 'a long password',
            'password_confirmation' => 'a long password', 'role' => 'buyer',
        ])->assertRedirect('/buyer');

        $this->assertTrue($this->page($this->get('/buyer'))['clearHistory']);
    }

    public function test_failing_to_log_in_does_not(): void
    {
        $this->actingAs(User::factory()->buyer()->create());

        $this->post('/session', ['email' => 'eve@example.com', 'password' => 'a long password'])->assertSessionHasErrors('email');

        $this->assertFalse($this->page($this->get('/buyer'))['clearHistory'] ?? false);
    }

    /** @return array<string, mixed> */
    private function page(TestResponse $response): array
    {
        return $response->viewData('page');
    }
}
