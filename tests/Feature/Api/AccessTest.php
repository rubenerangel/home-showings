<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Criteria 33, 36, 37 and 43, rules R16 to R18. */
class AccessTest extends TestCase
{
    use CallsApi, RefreshDatabase;

    private const BOTH = ['GET /me', 'DELETE /tokens/current', 'DELETE /tokens', 'GET /notifications', 'POST /notifications/read'];

    private const PUBLIC = ['GET /homes', 'GET /homes/{listing}'];

    private const BUYER = [
        'GET /buyer/homes/{listing}', 'POST /buyer/homes/{listing}/showings',
        'GET /buyer/availability', 'POST /buyer/availability', 'DELETE /buyer/availability/{range}',
        'GET /buyer/listings', 'GET /buyer/showings', 'POST /buyer/showings', 'POST /buyer/showings/{showing}/cancel',
    ];

    private const SELLER = [
        'GET /seller/listing-options', 'GET /seller/listings', 'POST /seller/listings',
        'GET /seller/listings/{listing}', 'PUT /seller/listings/{listing}', 'GET /seller/listings/{listing}/schedule',
        'POST /seller/listings/{listing}/photos', 'PUT /seller/listings/{listing}/photos/order', 'PATCH /seller/listings/{listing}/photos/{photo}',
        'DELETE /seller/listings/{listing}/photos/{photo}', 'POST /seller/listings/{listing}/windows',
        'POST /seller/windows/{window}/close', 'POST /seller/showings/{showing}/cancel',
    ];

    public function test_these_are_all_the_routes(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
            ->map(fn ($route) => collect($route->methods())->reject('HEAD')->implode('|').' '.substr($route->uri(), strlen('api/v1')))
            ->values()->all();

        $this->assertEqualsCanonicalizing(['POST /register', 'POST /tokens', ...self::PUBLIC, ...self::BOTH, ...self::BUYER, ...self::SELLER], $routes);
    }

    // Criterion 33

    #[DataProvider('protected')]
    public function test_a_token_is_required(string $method, string $uri): void
    {
        $this->api($method, $uri)->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->api($method, $uri, as: '1|unknown')->assertUnauthorized();
        // No Accept header.
        $this->call($method, "/api/v1$uri")->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    #[DataProvider('protected')]
    public function test_a_web_session_is_not_enough(string $method, string $uri): void
    {
        $user = User::factory()->create(['role' => str_contains($uri, 'seller') ? 'seller' : 'buyer']);

        $this->actingAs($user)->call($method, "/api/v1$uri")->assertUnauthorized();
    }

    /** @return array<string, array{string, string}> */
    public static function protected(): array
    {
        return self::cases([...self::BOTH, ...self::BUYER, ...self::SELLER]);
    }

    public function test_the_token_decides_who_it_is_and_not_the_session(): void
    {
        $user = User::factory()->seller()->create();
        $token = $this->token($user);

        $this->actingAs(User::factory()->buyer()->create())
            ->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])
            ->assertExactJson(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'seller']]);
    }

    public function test_a_token_does_not_open_the_web(): void
    {
        $token = $this->token(User::factory()->buyer()->create());

        $this->get('/buyer', ['Authorization' => "Bearer $token"])->assertRedirect('/login');
    }

    // Criterion 36

    public function test_a_token_is_stored_hashed(): void
    {
        $token = $this->token(User::factory()->create());

        $this->assertStringNotContainsString(explode('|', $token)[1], DB::table('personal_access_tokens')->get()->toJson());
    }

    public function test_a_token_lasts_thirty_days_whatever_its_use(): void
    {
        $this->freezeSecond();
        $token = $this->api('POST', '/tokens', [
            'email' => User::factory()->create()->email, 'password' => 'password', 'device_name' => 'phone',
        ])->assertCreated()->assertJsonPath('data.expires_at', now()->addDays(30)->toIso8601ZuluString())->json('data.token');

        $this->travel(15)->days();
        $this->api('GET', '/me', as: $token)->assertOk();
        $this->travel(15 * 86400 - 1)->seconds();
        $this->api('GET', '/me', as: $token)->assertOk();
        $this->travel(1)->seconds();
        $this->api('GET', '/me', as: $token)->assertUnauthorized();
    }

    public function test_revoking_the_current_token_keeps_the_others(): void
    {
        $user = User::factory()->create();
        [$first, $second] = [$this->token($user), $this->token($user)];

        $this->api('DELETE', '/tokens/current', as: $first)->assertNoContent();

        $this->api('GET', '/me', as: $first)->assertUnauthorized();
        $this->api('GET', '/me', as: $second)->assertOk();
    }

    public function test_revoking_all_of_them_keeps_the_ones_of_other_accounts(): void
    {
        $user = User::factory()->create();
        [$first, $second, $other] = [$this->token($user), $this->token($user), $this->token(User::factory()->create())];

        $this->api('DELETE', '/tokens', as: $first)->assertNoContent();

        $this->api('GET', '/me', as: $first)->assertUnauthorized();
        $this->api('GET', '/me', as: $second)->assertUnauthorized();
        $this->api('GET', '/me', as: $other)->assertOk();
    }

    public function test_leaving_the_web_revokes_nothing(): void
    {
        $user = User::factory()->buyer()->create();
        $token = $this->token($user);

        $this->actingAs($user)->delete('/session')->assertRedirect('/');

        $this->api('GET', '/me', as: $token)->assertOk();
    }

    // Criterion 37

    #[DataProvider('ofARole')]
    public function test_the_other_role_is_forbidden(string $method, string $uri): void
    {
        $other = User::factory()->create(['role' => str_starts_with($uri, '/buyer') ? 'seller' : 'buyer']);

        $this->api($method, $uri, as: $other)->assertForbidden()->assertExactJson(['message' => 'Forbidden']);
    }

    /** @return array<string, array{string, string}> */
    public static function ofARole(): array
    {
        return self::cases([...self::BUYER, ...self::SELLER]);
    }

    #[TestWith(['abc'])]
    #[TestWith(['0'])]
    #[TestWith(['999999'])]
    #[TestWith(['9999999999999999999999'])]
    public function test_missing_and_malformed_ids_get_the_same_answer_as_someone_else_s(string $id): void
    {
        $seller = User::factory()->seller()->create();
        $theirs = Listing::factory()->create();

        $this->api('GET', "/seller/listings/$theirs->id", as: $seller)->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->api('GET', "/seller/listings/$id", as: $seller)->assertNotFound()->assertExactJson(['message' => 'Not Found']);
    }

    // R17

    #[TestWith(['GET', '/api', 404, 'Not Found'])]
    #[TestWith(['GET', '/api/v1/nothing', 404, 'Not Found'])]
    #[TestWith(['GET', '/api/v2/me', 404, 'Not Found'])]
    #[TestWith(['DELETE', '/api/v1/me', 405, 'Method Not Allowed'])]
    public function test_every_error_is_json(string $method, string $uri, int $status, string $message): void
    {
        $this->call($method, $uri)->assertStatus($status)->assertExactJson(['message' => $message]);
    }

    public function test_invalid_input_is_json_without_an_accept_header(): void
    {
        $this->call('POST', '/api/v1/tokens')->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_the_web_still_answers_pages(): void
    {
        $this->get('/nothing')->assertNotFound()->assertHeader('content-type', 'text/html; charset=utf-8');
    }

    // Criterion 43

    public function test_an_account_makes_sixty_requests_a_minute_with_all_its_tokens(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $tokens = [$this->token($user), $this->token($user)];

        for ($i = 0; $i < 60; $i++) {
            $this->api('GET', '/me', as: $tokens[$i % 2])->assertOk();
        }
        foreach ($tokens as $token) {
            $this->api('GET', '/me', as: $token)->assertTooManyRequests()->assertExactJson(['message' => 'Too Many Requests'])->assertHeader('Retry-After');
        }
        $this->api('GET', '/me', as: User::factory()->create())->assertOk();

        $this->travel(61)->seconds();
        $this->api('GET', '/me', as: $tokens[0])->assertOk();
    }

    #[TestWith(['GET', '/me', '1|unknown', 401])]
    #[TestWith(['GET', '/nothing', null, 404])]
    #[TestWith(['DELETE', '/me', null, 405])]
    public function test_a_bad_token_an_unknown_path_and_a_wrong_method_do_not_count(string $method, string $uri, ?string $token, int $status): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        for ($i = 0; $i < 61; $i++) {
            $this->api($method, $uri, as: $token ?? $user)->assertStatus($status);
        }

        $this->api('GET', '/me', as: $user)->assertOk();
        $this->api('POST', '/tokens')->assertUnprocessable();
    }

    public function test_a_missing_resource_counts(): void
    {
        $this->freezeSecond();
        $seller = User::factory()->seller()->create();
        for ($i = 0; $i < 60; $i++) {
            $this->api('GET', '/seller/listings/999999', as: $seller)->assertNotFound();
        }

        $this->api('GET', '/seller/listings/999999', as: $seller)->assertTooManyRequests();
    }

    public function test_an_address_makes_sixty_guest_requests_a_minute(): void
    {
        $this->freezeSecond();
        for ($i = 0; $i < 60; $i++) {
            $this->api('POST', $i % 2 ? '/tokens' : '/register')->assertUnprocessable();
        }

        $this->api('POST', '/tokens')->assertTooManyRequests();
        $this->api('POST', '/register')->assertTooManyRequests();
        $this->api('GET', '/me', as: User::factory()->create())->assertOk();

        $this->travel(61)->seconds();
        $this->api('POST', '/tokens')->assertUnprocessable();
    }

    /**
     * @param  list<string>  $routes
     * @return array<string, array{string, string}>
     */
    private static function cases(array $routes): array
    {
        $cases = [];
        foreach ($routes as $route) {
            $cases[$route] = explode(' ', preg_replace('/\{\w+\}/', '1', $route));
        }

        return $cases;
    }
}
