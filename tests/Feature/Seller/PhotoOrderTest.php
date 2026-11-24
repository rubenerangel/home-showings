<?php

namespace Tests\Feature\Seller;

use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\ShowingWindow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Criteria 46, 47 and 48, rule R20, in the pages. */
class PhotoOrderTest extends TestCase
{
    use RefreshDatabase;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listing = Listing::factory()->create();
        $this->actingAs($this->listing->seller);
    }

    // Criterion 46

    /**
     * @param  list<int>  $positions
     * @param  list<int>  $order  indexes of the photos, in the order sent
     */
    #[TestWith([[1, 2, 3], [2, 0, 1]], 'last to first')]
    #[TestWith([[1, 2, 3], [1, 0, 2]], 'swap')]
    #[TestWith([[1, 2, 3], [2, 1, 0]], 'reverse')]
    #[TestWith([[1, 2, 3], [0, 1, 2]], 'the same')]
    #[TestWith([[1, 3, 4, 5, 6], [4, 3, 2, 1, 0]], 'with gaps')]
    #[TestWith([[0, 1, 2], [2, 0, 1]], 'from zero')]
    #[TestWith([[1], [0]], 'only one')]
    #[TestWith([[1, 2], [1, 0]], 'two')]
    #[TestWith([[2, 1, 5, 4, 3], [0, 1, 2, 3, 4]], 'stored in another order')]
    public function test_the_order_is_the_one_sent_and_positions_start_at_one(array $positions, array $order): void
    {
        $photos = array_map(fn (int $position) => $this->photo($position), $positions);
        usort($photos, fn ($a, $b) => $a->position <=> $b->position);
        $ids = array_map(fn (int $index) => $photos[$index]->id, $order);

        $this->order($ids)->assertSessionHasNoErrors()->assertRedirect("/seller/listings/{$this->listing->id}/edit");

        $saved = $this->listing->photos()->get();
        $this->assertSame($ids, $saved->pluck('id')->all());
        $this->assertSame(range(1, count($ids)), $saved->pluck('position')->all());
    }

    public function test_the_first_photo_is_the_cover_everywhere(): void
    {
        $window = ShowingWindow::factory()->for($this->listing)->between('2027-03-10', '10:00', '12:00')->create();
        $first = $this->photo(1);
        $second = $this->photo(2, 'kitchen');

        $this->order([$second->id, $first->id])->assertSessionHasNoErrors();

        $this->get('/seller')->assertInertia(fn (Assert $page) => $page->where('listings.0.cover.id', $second->id));
        $this->get("/seller/listings/{$this->listing->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('photos.0.id', $second->id)->where('photos.1.id', $first->id));
        $buyer = User::factory()->buyer()->create();
        $this->travelTo('2027-03-10 07:00:00');
        $buyer->availabilityRanges()->create(['local_starts_at' => '2027-03-10 10:00', 'local_ends_at' => '2027-03-10 12:00']);
        $this->assertSame(1, $window->listing->windows()->count());
        $this->actingAs($buyer)->get('/buyer/listings')->assertInertia(fn (Assert $page) => $page
            ->where('listings.0.photos.0.room', 'kitchen'));
    }

    public function test_a_new_photo_goes_last_and_removing_the_cover_promotes_the_next(): void
    {
        Storage::fake('public');
        [$first, $second] = [$this->photo(1), $this->photo(2)];
        $this->order([$second->id, $first->id]);

        $this->post("/seller/listings/{$this->listing->id}/photos", [
            'photos' => [['file' => UploadedFile::fake()->create('a.jpg', 1, 'image/jpeg'), 'room' => 'other']],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$second->id, $first->id], $this->listing->photos()->take(2)->pluck('id')->all());
        $this->assertSame(3, $this->listing->photos()->count());

        $this->delete("/seller/listings/{$this->listing->id}/photos/{$second->id}");
        $this->assertSame($first->id, $this->listing->photos()->firstOrFail()->id);
    }

    public function test_the_page_says_so(): void
    {
        $photo = $this->photo(1);

        $this->order([$photo->id])->assertRedirect();

        $this->get("/seller/listings/{$this->listing->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->hasFlash('message', 'Order saved.'));
    }

    // Criterion 48

    /** @param callable(list<int>): mixed $sent */
    #[DataProvider('wrong')]
    public function test_a_list_that_is_not_the_photos_of_the_home_is_refused_and_nothing_changes(callable $sent, string $field): void
    {
        $ids = array_map(fn (int $position) => $this->photo($position)->id, [1, 2, 3]);
        $theirs = Listing::factory()->create()->photos()->create(['path' => 'x.jpg', 'room' => 'other', 'position' => 1]);
        $before = ListingPhoto::orderBy('id')->get(['id', 'position'])->toArray();

        $this->from("/seller/listings/{$this->listing->id}/edit")
            ->put("/seller/listings/{$this->listing->id}/photos/order", ['photos' => $sent($ids, $theirs->id)])
            ->assertRedirect("/seller/listings/{$this->listing->id}/edit")
            ->assertSessionHasErrors($field);

        $this->assertSame($before, ListingPhoto::orderBy('id')->get(['id', 'position'])->toArray());
    }

    /** @return array<string, array{callable, string}> */
    public static function wrong(): array
    {
        return [
            'missing one' => [fn (array $ids) => [$ids[0], $ids[1]], 'photos'],
            'repeated' => [fn (array $ids) => [$ids[0], $ids[0], $ids[1]], 'photos.0'],
            'one more' => [fn (array $ids) => [...$ids, 999999], 'photos'],
            'someone else s' => [fn (array $ids, int $theirs) => [$ids[0], $ids[1], $theirs], 'photos'],
            'unknown' => [fn (array $ids) => [$ids[0], $ids[1], 999999], 'photos'],
            'too large for an integer' => [fn (array $ids) => [$ids[0], $ids[1], '99999999999999999999'], 'photos.2'],
            'not a number' => [fn (array $ids) => [$ids[0], $ids[1], 'x'], 'photos.2'],
            'a list inside' => [fn (array $ids) => [$ids[0], $ids[1], [$ids[2]]], 'photos.2'],
            'not a list' => [fn () => 'x', 'photos'],
            'keys' => [fn (array $ids) => ['a' => $ids[0], 'b' => $ids[1], 'c' => $ids[2]], 'photos'],
            'empty' => [fn () => [], 'photos'],
            'nothing' => [fn () => null, 'photos'],
        ];
    }

    public function test_the_message_tells_to_check_the_order(): void
    {
        $this->photo(1);

        $this->order([999999])->assertSessionHasErrors(['photos' => 'The photos changed. Check the order and try again.']);
    }

    // Criterion 47

    public function test_a_home_of_someone_else_is_not_found_whatever_the_input(): void
    {
        $theirs = Listing::factory()->create();

        $this->put("/seller/listings/{$theirs->id}/photos/order", ['photos' => 'x'])->assertNotFound();
        $this->put('/seller/listings/999999/photos/order', ['photos' => [1]])->assertNotFound();
        $this->put('/seller/listings/abc/photos/order', ['photos' => [1]])->assertNotFound();
    }

    public function test_buyers_are_forbidden_and_guests_go_to_the_login(): void
    {
        $uri = "/seller/listings/{$this->listing->id}/photos/order";

        $this->actingAs(User::factory()->buyer()->create())->put($uri, ['photos' => [1]])->assertForbidden();
        auth()->logout();
        $this->put($uri, ['photos' => [1]])->assertRedirect('/login');
    }

    private function photo(int $position, string $room = 'exterior'): ListingPhoto
    {
        return $this->listing->photos()->create(['path' => "listings/x/$position.jpg", 'room' => $room, 'position' => $position]);
    }

    /** @param list<int> $ids */
    private function order(array $ids): TestResponse
    {
        return $this->put("/seller/listings/{$this->listing->id}/photos/order", ['photos' => $ids]);
    }
}
