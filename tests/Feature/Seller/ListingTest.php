<?php

namespace Tests\Feature\Seller;

use App\Enums\HouseTimezone;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seller_home_lists_only_their_own_listings(): void
    {
        $own = Listing::factory()->create();
        Listing::factory()->create();

        $this->actingAs($own->seller)->get('/seller')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('seller/Home')
            ->has('listings', 1)
            ->where('listings.0.id', $own->id)
            ->where('listings.0.address', $own->address));
    }

    public function test_the_create_page_offers_the_allowed_options(): void
    {
        $this->actingAs(User::factory()->seller()->create())->get('/seller/listings/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('seller/listings/Create')
                ->where('options.timezones', array_column(HouseTimezone::cases(), 'value'))
                ->where('options.durations', [15, 30, 45, 60])
                ->where('options.notices', [0, 2, 12, 24, 48])
                ->has('options.states', 51));
    }

    public function test_a_seller_creates_a_listing_and_continues_to_its_edit_page(): void
    {
        $seller = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();

        $response = $this->actingAs($seller)->post('/seller/listings', [...self::valid(), 'seller_id' => $other->id]);

        $listing = Listing::sole();
        $response->assertRedirect("/seller/listings/{$listing->id}/edit");
        $this->assertSame($seller->id, $listing->seller_id);
        $this->assertSaved(self::valid(), $listing);
    }

    public function test_a_seller_edits_a_listing(): void
    {
        $listing = Listing::factory()->create();
        $changes = [
            ...self::valid(), 'price' => 510_000, 'bathrooms' => 1.5, 'description' => null,
            'timezone' => 'America/Phoenix', 'showing_duration_minutes' => 60, 'min_notice_hours' => 48,
        ];

        $this->actingAs($listing->seller)->get("/seller/listings/{$listing->id}/edit")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('seller/listings/Edit')
                ->where('listing.id', $listing->id)
                ->where('listing.timezone', 'America/Denver')
                ->where('listing.showing_duration_minutes', 30));

        $this->put("/seller/listings/{$listing->id}", $changes)
            ->assertSessionHasNoErrors()
            ->assertRedirect("/seller/listings/{$listing->id}/edit");

        $this->assertSaved($changes, $listing->refresh());
    }

    /** @param array<string, mixed> $change */
    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_refused_on_create_and_on_edit(array $change): void
    {
        $listing = Listing::factory()->create();
        $before = $listing->refresh()->getAttributes();
        $field = array_key_first($change);
        $this->actingAs($listing->seller);

        $this->post('/seller/listings', [...self::valid(), ...$change])->assertSessionHasErrors($field);
        $this->put("/seller/listings/{$listing->id}", [...self::valid(), ...$change])->assertSessionHasErrors($field);

        $this->assertSame($before, Listing::sole()->getAttributes());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidValues(): array
    {
        return [
            'no address' => [['address' => ' ']],
            'address not text' => [['address' => ['x']]],
            'long address' => [['address' => str_repeat('a', 256)]],
            'no city' => [['city' => '']],
            'long city' => [['city' => str_repeat('a', 256)]],
            'no state' => [['state' => '']],
            'unknown state' => [['state' => 'ZZ']],
            'no price' => [['price' => null]],
            'price zero' => [['price' => 0]],
            'price with cents' => [['price' => 450_000.5]],
            'price above the column' => [['price' => 2_147_483_648]],
            'negative bedrooms' => [['bedrooms' => -1]],
            'bedrooms above the column' => [['bedrooms' => 32_768]],
            'no bathrooms' => [['bathrooms' => 0]],
            'bathrooms step' => [['bathrooms' => 1.3]],
            'bathrooms above the column' => [['bathrooms' => 100]],
            'bathrooms not a number' => [['bathrooms' => 'two']],
            'long description' => [['description' => str_repeat('a', 1001)]],
            'zone outside the list' => [['timezone' => 'America/Anchorage']],
            'no zone' => [['timezone' => null]],
            'duration outside the list' => [['showing_duration_minutes' => 20]],
            'no duration' => [['showing_duration_minutes' => null]],
            'notice outside the list' => [['min_notice_hours' => 1]],
            'no notice' => [['min_notice_hours' => null]],
        ];
    }

    /** @param array<string, mixed> $change */
    #[DataProvider('limits')]
    public function test_values_on_the_limit_are_accepted(array $change): void
    {
        $this->actingAs(User::factory()->seller()->create())
            ->post('/seller/listings', [...self::valid(), ...$change])
            ->assertSessionHasNoErrors();

        $this->assertSaved($change, Listing::sole());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function limits(): array
    {
        return [
            'price 1' => [['price' => 1]],
            'highest price' => [['price' => 2_147_483_647]],
            'studio' => [['bedrooms' => 0]],
            'half bathroom' => [['bathrooms' => 0.5]],
            'highest bathrooms' => [['bathrooms' => 99.5]],
            'long description' => [['description' => str_repeat('a', 1000)]],
            'no notice' => [['min_notice_hours' => 0]],
        ];
    }

    #[DataProvider('routes')]
    public function test_without_a_session_every_route_redirects_to_the_login(string $method, string $path): void
    {
        $this->call($method, self::path($path, Listing::factory()->create()->id), self::valid())->assertRedirect('/');
        $this->assertDatabaseCount('listings', 1);
    }

    #[DataProvider('routes')]
    public function test_buyers_are_forbidden_whether_or_not_the_listing_exists(string $method, string $path): void
    {
        $listing = Listing::factory()->create();
        $before = $listing->refresh()->getAttributes();
        $this->actingAs(User::factory()->buyer()->create());

        foreach ([$listing->id, $listing->id + 1] as $id) {
            $this->call($method, self::path($path, $id), self::valid())->assertForbidden();
        }
        $this->assertSame($before, Listing::sole()->getAttributes());
    }

    public function test_the_listing_of_another_seller_or_an_unknown_one_is_not_found(): void
    {
        $listing = Listing::factory()->create();
        $before = $listing->refresh()->getAttributes();
        $this->actingAs(User::factory()->seller()->create());

        foreach ([$listing->id, $listing->id + 1, '99999999999999999999', 'x'] as $id) {
            $this->get("/seller/listings/$id/edit")->assertNotFound();
            $this->put("/seller/listings/$id", self::valid())->assertNotFound();
            $this->put("/seller/listings/$id", [])->assertNotFound();
        }
        $this->assertSame($before, Listing::sole()->getAttributes());
    }

    /** @return array<string, array{string, string}> */
    public static function routes(): array
    {
        return [
            'create' => ['GET', '/seller/listings/create'],
            'store' => ['POST', '/seller/listings'],
            'edit' => ['GET', '/seller/listings/{id}/edit'],
            'update' => ['PUT', '/seller/listings/{id}'],
        ];
    }

    /** @param array<string, mixed> $expected */
    private function assertSaved(array $expected, Listing $listing): void
    {
        $actual = array_intersect_key($listing->toArray(), $expected);
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    private static function path(string $path, int $id): string
    {
        return str_replace('{id}', (string) $id, $path);
    }

    /** @return array<string, mixed> */
    private static function valid(): array
    {
        return [
            'address' => '120 Spruce St', 'city' => 'Boulder', 'state' => 'CO', 'price' => 450_000,
            'bedrooms' => 3, 'bathrooms' => 2.5, 'description' => 'Quiet street.', 'timezone' => 'America/Denver',
            'showing_duration_minutes' => 30, 'min_notice_hours' => 2,
        ];
    }
}
