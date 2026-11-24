<?php

namespace Tests\Feature\Scheduling;

use App\Models\AvailabilityRange;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Criterion 17, rule R10, for the scheduling routes. */
class PermissionsTest extends TestCase
{
    use SchedulingScenario;

    #[DataProvider('routes')]
    public function test_without_a_session_every_route_redirects_to_the_login(string $method, string $uri): void
    {
        $this->call($method, $this->fill($uri))->assertRedirect('/login');
    }

    #[DataProvider('routes')]
    public function test_the_other_role_is_forbidden(string $method, string $uri): void
    {
        $other = str_starts_with($uri, '/buyer') ? $this->seller : $this->buyer;

        $this->actingAs($other)->call($method, $this->fill($uri))->assertForbidden();
    }

    #[DataProvider('ownedRoutes')]
    public function test_a_resource_of_someone_else_is_not_found_and_stays_the_same(string $method, string $uri): void
    {
        $role = str_starts_with($uri, '/buyer') ? 'buyer' : 'seller';
        $uri = $this->fill($uri);
        $before = $this->snapshot();

        $this->actingAs(User::factory()->create(['role' => $role]))
            ->call($method, $uri, ['date' => '2027-03-11', 'start' => '10:00', 'end' => '11:00'])
            ->assertNotFound();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_a_listing_of_someone_else_is_not_found_whatever_the_input(): void
    {
        $this->actingAs(User::factory()->seller()->create())
            ->post("/seller/listings/{$this->listing->id}/windows", ['date' => 'x'])
            ->assertNotFound();
    }

    /** @return array<string, array{string, string}> */
    public static function routes(): array
    {
        return [
            'matching listings' => ['GET', '/buyer/listings'],
            'availability' => ['GET', '/buyer/availability'],
            'add availability' => ['POST', '/buyer/availability'],
            'book' => ['POST', '/buyer/showings'],
            ...self::ownedRoutes(),
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function ownedRoutes(): array
    {
        return [
            'remove availability' => ['DELETE', '/buyer/availability/{range}'],
            'cancel as buyer' => ['POST', '/buyer/showings/{showing}/cancel'],
            'cancel as seller' => ['POST', '/seller/showings/{showing}/cancel'],
            'open a window' => ['POST', '/seller/listings/{listing}/windows'],
            'close a window' => ['POST', '/seller/windows/{window}/close'],
        ];
    }

    private function fill(string $uri): string
    {
        $showing = Showing::first() ?? $this->showing('10:00', '10:30', $this->buyer);

        return strtr($uri, [
            '{range}' => AvailabilityRange::firstOrFail()->id,
            '{showing}' => $showing->id,
            '{listing}' => $this->listing->id,
            '{window}' => $this->window->id,
        ]);
    }

    /** @return list<array<mixed>> */
    private function snapshot(): array
    {
        return [AvailabilityRange::all()->toArray(), Showing::all()->toArray(), ShowingWindow::all()->toArray()];
    }
}
