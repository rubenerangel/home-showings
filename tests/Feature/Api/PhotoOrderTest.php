<?php

namespace Tests\Feature\Api;

use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Criteria 46, 47 and 48, in the API. */
class PhotoOrderTest extends TestCase
{
    use CallsApi, RefreshDatabase;

    private Listing $listing;

    /** @var list<ListingPhoto> */
    private array $photos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listing = Listing::factory()->create();
        foreach ([1, 2, 3] as $position) {
            $this->photos[] = $this->listing->photos()->create(['path' => "$position.jpg", 'room' => 'kitchen', 'position' => $position]);
        }
    }

    public function test_the_answer_is_all_the_photos_in_the_new_order(): void
    {
        $ids = [$this->photos[2]->id, $this->photos[0]->id, $this->photos[1]->id];

        $this->order($ids)->assertOk()->assertExactJson(['data' => array_map(fn (int $index) => [
            'id' => $this->photos[$index]->id,
            'url' => 'http://localhost:8080/storage/'.$this->photos[$index]->path,
            'room' => 'kitchen',
        ], [2, 0, 1])]);

        $seller = $this->listing->seller;
        $this->api('GET', "/seller/listings/{$this->listing->id}", as: $seller)->assertJsonPath('data.photos.0.id', $ids[0]);
        $this->api('GET', '/seller/listings', as: $seller)->assertJsonPath('data.0.cover.id', $ids[0]);
    }

    #[TestWith([[0, 1]])]
    #[TestWith([[0, 0, 1]])]
    #[TestWith([[]])]
    public function test_a_list_that_is_not_the_photos_of_the_home_is_refused(array $indexes): void
    {
        $this->order(array_map(fn (int $index) => $this->photos[$index]->id, $indexes))->assertUnprocessable();

        $this->assertSame([1, 2, 3], $this->listing->photos()->pluck('position')->all());
    }

    public function test_someone_else_s_home_is_not_found_whatever_the_input(): void
    {
        $this->api('PUT', "/seller/listings/{$this->listing->id}/photos/order", ['photos' => 'x'], User::factory()->seller()->create())
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
    }

    public function test_only_put_is_accepted(): void
    {
        $this->api('POST', "/seller/listings/{$this->listing->id}/photos/order", ['photos' => [1]], $this->listing->seller)
            ->assertStatus(405);
    }

    /** @param list<int> $ids */
    private function order(array $ids): TestResponse
    {
        return $this->api('PUT', "/seller/listings/{$this->listing->id}/photos/order", ['photos' => $ids], $this->listing->seller);
    }
}
