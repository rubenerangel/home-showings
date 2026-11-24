<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_picker_lists_users_by_role(): void
    {
        $seller = User::factory()->seller()->create();
        $buyer = User::factory()->buyer()->create();

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Picker')
            ->has('sellers', 1)
            ->where('sellers.0.id', $seller->id)
            ->has('buyers', 1)
            ->where('buyers.0.id', $buyer->id));
    }

    #[DataProvider('roles')]
    public function test_continuing_as_a_user_opens_the_area_of_the_role(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->post('/session', ['user_id' => $user->id])->assertRedirect("/$role");

        $this->assertAuthenticatedAs($user);
        $this->get("/$role")->assertOk();
    }

    #[DataProvider('roles')]
    public function test_without_a_session_both_areas_redirect_to_the_picker(string $role): void
    {
        $this->get("/$role")->assertRedirect('/');
    }

    public function test_the_area_of_the_other_role_is_forbidden(): void
    {
        $this->actingAs(User::factory()->buyer()->create())->get('/seller')->assertForbidden();
        $this->actingAs(User::factory()->seller()->create())->get('/buyer')->assertForbidden();
    }

    public function test_an_unknown_user_is_not_found(): void
    {
        $this->post('/session', ['user_id' => 999])->assertNotFound();
        $this->assertGuest();
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
}
