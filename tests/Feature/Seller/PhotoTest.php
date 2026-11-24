<?php

namespace Tests\Feature\Seller;

use App\Enums\Room;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** R14. Real files, because the fakes of the framework report the type from the name. */
class PhotoTest extends TestCase
{
    use RefreshDatabase;

    private const JPEG = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

    private const PNG = "\x89PNG\r\n\x1A\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00";

    private const WEBP = "RIFF\x24\x00\x00\x00WEBPVP8 \x18\x00\x00\x00";

    private const FIVE_MB = 5 * 1024 * 1024;

    private Listing $listing;

    /** @var list<string> */
    private array $temporary = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->listing = Listing::factory()->create();
        $this->actingAs($this->listing->seller);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), array_filter($this->temporary, file_exists(...)));
        parent::tearDown();
    }

    public function test_photos_are_saved_in_order_with_their_room_and_a_url_without_host(): void
    {
        $this->upload([
            $this->photo('front.jpg', self::JPEG, 'exterior'),
            $this->photo('room.png', self::PNG, 'living_room'),
            $this->photo('cook.webp', self::WEBP, 'kitchen'),
        ])->assertSessionHasNoErrors()->assertRedirect("/seller/listings/{$this->listing->id}/edit");

        $photos = $this->listing->photos;
        $this->assertSame([Room::Exterior, Room::LivingRoom, Room::Kitchen], $photos->pluck('room')->all());
        $this->assertSame(['jpg', 'png', 'webp'], $photos->map(fn ($photo) => pathinfo($photo->path, PATHINFO_EXTENSION))->all());
        Storage::disk('public')->assertExists($photos->pluck('path')->all());

        $this->get("/seller/listings/{$this->listing->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->has('photos', 3)
            ->where('photos.0', ['id' => $photos[0]->id, 'url' => "/storage/{$photos[0]->path}", 'room' => 'exterior'])
            ->where('photos.2.room', 'kitchen')
            ->where('options.rooms', array_column(Room::cases(), 'value')));
    }

    public function test_the_seller_home_shows_the_first_photo_as_the_cover(): void
    {
        $this->upload([$this->photo(room: 'kitchen'), $this->photo(room: 'exterior')]);
        $cover = $this->listing->photos()->firstOrFail();

        $this->get('/seller')->assertInertia(fn (Assert $page) => $page
            ->where('listings.0.cover', ['id' => $cover->id, 'url' => "/storage/{$cover->path}", 'room' => 'kitchen']));

        $this->delete("/seller/listings/{$this->listing->id}/photos/{$cover->id}");
        $this->get('/seller')->assertInertia(fn (Assert $page) => $page->where('listings.0.cover.room', 'exterior'));

        $this->listing->photos()->delete();
        $this->get('/seller')->assertInertia(fn (Assert $page) => $page->where('listings.0.cover', null));
    }

    public function test_five_photos_fit_in_one_upload_and_a_sixth_is_refused(): void
    {
        $this->upload(array_fill(0, 6, $this->photo()))->assertSessionHasErrors('photos');
        $this->assertNothingSaved();

        $this->upload(array_map(fn () => $this->photo(), range(1, 5)))->assertSessionHasNoErrors();
        $this->assertCount(5, $this->listing->photos);

        $this->upload([$this->photo()])->assertSessionHasErrors('photos');
        $this->assertCount(5, $this->listing->photos()->get());
        $this->assertCount(5, Storage::disk('public')->allFiles());
    }

    public function test_the_limit_counts_the_photos_already_saved(): void
    {
        $this->upload(array_map(fn () => $this->photo(), range(1, 3)));

        $this->upload(array_map(fn () => $this->photo(), range(1, 3)))->assertSessionHasErrors('photos');
        $this->assertCount(3, $this->listing->photos()->get());

        $this->upload(array_map(fn () => $this->photo(), range(1, 2)))->assertSessionHasNoErrors();
        $this->assertCount(5, $this->listing->photos()->get());
    }

    public function test_exactly_five_megabytes_is_accepted_and_one_byte_more_is_refused(): void
    {
        $this->upload([$this->photo(content: str_pad(self::JPEG, self::FIVE_MB + 1, "\0"))])
            ->assertSessionHasErrors(['photos.0.file' => 'Each photo can be up to 5 MB.']);
        $this->assertNothingSaved();

        $this->upload([$this->photo(content: str_pad(self::JPEG, self::FIVE_MB, "\0"))])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->listing->photos);
    }

    #[DataProvider('otherFiles')]
    public function test_other_files_are_refused_whatever_their_name(string $name, string $content): void
    {
        $this->upload([$this->photo(), $this->photo($name, $content)])
            ->assertSessionHasErrors('photos.1.file')
            ->assertSessionDoesntHaveErrors('photos.0.file');
        $this->assertNothingSaved();
    }

    /** @return array<string, array{string, string}> */
    public static function otherFiles(): array
    {
        return [
            'gif named jpg' => ['a.jpg', "GIF89a\x01\x00\x01\x00\x00\x00\x00;"],
            'text named jpg' => ['a.jpg', 'just text'],
            'empty' => ['a.jpg', ''],
            'html named png' => ['a.png', '<!DOCTYPE html><script>alert(1)</script>'],
            'svg' => ['a.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'],
            'jpeg named php' => ['a.php', self::JPEG],
        ];
    }

    public function test_the_stored_extension_comes_from_the_content(): void
    {
        $this->upload([$this->photo('a.jpg', self::PNG)])->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.png', $this->listing->photos()->sole()->path);
    }

    /** @param array<string, mixed> $change */
    #[DataProvider('incompleteEntries')]
    public function test_incomplete_entries_are_refused(array $change, string $field): void
    {
        $this->upload([[...$this->photo(), ...$change]])->assertSessionHasErrors($field);
        $this->assertNothingSaved();
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function incompleteEntries(): array
    {
        return [
            'no room' => [['room' => ''], 'photos.0.room'],
            'unknown room' => [['room' => 'garage'], 'photos.0.room'],
            'no file' => [['file' => null], 'photos.0.file'],
            'text instead of a file' => [['file' => 'x'], 'photos.0.file'],
        ];
    }

    public function test_an_empty_or_malformed_upload_is_refused(): void
    {
        $this->upload([])->assertSessionHasErrors('photos');
        $this->post("/seller/listings/{$this->listing->id}/photos", ['photos' => 'x'])->assertSessionHasErrors('photos');
    }

    public function test_no_file_stays_behind_when_saving_fails(): void
    {
        Exceptions::fake();
        $saved = 0;
        ListingPhoto::creating(function () use (&$saved) {
            throw_if(++$saved === 2, new RuntimeException('Second photo'));
        });

        $this->upload([$this->photo(), $this->photo()])->assertServerError();
        Exceptions::assertReported(RuntimeException::class);

        $this->assertNothingSaved();
    }

    public function test_removing_a_photo_deletes_its_file_and_new_ones_go_last(): void
    {
        $this->upload([$this->photo(room: 'exterior'), $this->photo(room: 'kitchen'), $this->photo(room: 'bedroom')]);
        [$first, $second, $third] = $this->listing->photos->all();

        $this->delete("/seller/listings/{$this->listing->id}/photos/{$second->id}")
            ->assertRedirect("/seller/listings/{$this->listing->id}/edit");
        Storage::disk('public')->assertMissing($second->path);
        Storage::disk('public')->assertExists([$first->path, $third->path]);

        $this->upload([$this->photo(room: 'bathroom')])->assertSessionHasNoErrors();
        $this->assertSame(
            [Room::Exterior, Room::Bedroom, Room::Bathroom],
            $this->listing->photos()->pluck('room')->all(),
        );
    }

    public function test_the_room_of_a_photo_can_change(): void
    {
        $this->upload([$this->photo(room: 'exterior')]);
        $photo = $this->listing->photos()->sole();
        $path = "/seller/listings/{$this->listing->id}/photos/{$photo->id}";

        $this->patch($path, ['room' => 'garage'])->assertSessionHasErrors('room');
        $this->assertSame(Room::Exterior, $photo->refresh()->room);

        $this->patch($path, ['room' => 'bedroom'])->assertSessionHasNoErrors();
        $this->assertSame(Room::Bedroom, $photo->refresh()->room);
    }

    public function test_photos_of_another_seller_or_of_another_listing_are_not_found(): void
    {
        $this->upload([$this->photo()]);
        $photo = $this->listing->photos()->sole();
        $sibling = Listing::factory()->for($this->listing->seller, 'seller')->create();

        $attempt = function (int|string $listing, int|string $photo): void {
            $this->delete("/seller/listings/$listing/photos/$photo")->assertNotFound();
            $this->patch("/seller/listings/$listing/photos/$photo", ['room' => 'other'])->assertNotFound();
        };
        $attempt($sibling->id, $photo->id);
        $attempt($this->listing->id, $photo->id + 1);
        $attempt($this->listing->id, '99999999999999999999');

        $this->actingAs(User::factory()->seller()->create());
        $attempt($this->listing->id, $photo->id);
        $this->upload([$this->photo()])->assertNotFound();

        $this->assertSame(Room::Exterior, $this->listing->photos()->sole()->room);
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_buyers_are_forbidden_and_guests_go_to_the_picker(): void
    {
        $this->upload([$this->photo()]);
        $photo = $this->listing->photos()->sole();
        $path = "/seller/listings/{$this->listing->id}/photos";

        $this->actingAs(User::factory()->buyer()->create());
        $this->upload([$this->photo()])->assertForbidden();
        $this->patch("$path/{$photo->id}", ['room' => 'other'])->assertForbidden();
        $this->delete("$path/{$photo->id}")->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->upload([$this->photo()])->assertRedirect('/');
        $this->patch("$path/{$photo->id}", ['room' => 'other'])->assertRedirect('/');
        $this->delete("$path/{$photo->id}")->assertRedirect('/');

        $this->assertSame(Room::Exterior, $this->listing->photos()->sole()->room);
    }

    /** @return array{file: UploadedFile, room: string} */
    private function photo(string $name = 'a.jpg', string $content = self::JPEG, string $room = 'exterior'): array
    {
        $path = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($path, $content);
        $this->temporary[] = $path;

        return ['file' => new UploadedFile($path, $name, null, null, true), 'room' => $room];
    }

    /** @param list<array<string, mixed>> $photos */
    private function upload(array $photos): TestResponse
    {
        return $this->post("/seller/listings/{$this->listing->id}/photos", ['photos' => $photos]);
    }

    private function assertNothingSaved(): void
    {
        $this->assertDatabaseCount('listing_photos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
