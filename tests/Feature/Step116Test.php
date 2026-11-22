<?php

namespace Tests\Feature;

use App\Filament\Resources\GalleryPictures\Pages\ListGalleryPictures;
use App\Models\Category;
use App\Models\GalleryPicture;
use App\Models\PictureRedirect;
use App\Models\Setting;
use App\Models\Story;
use App\Models\StoryChapter;
use App\Models\StorySlide;
use App\Models\User;
use App\Support\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 116: every gallery picture has an ID (I-12) and the admin can switch it on or off. */
class Step116Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Gallery::forget();
    }

    protected function tearDown(): void
    {
        Setting::forget();
        Gallery::forget();
        parent::tearDown();
    }

    private function story(int $n = 3): Story
    {
        $cat = Category::firstOrCreate(['slug' => 'history'], ['name' => ['en' => 'History']]);
        $story = Story::create(['title' => ['en' => 'The Walk'], 'slug' => 'the-walk', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
        $chapter = StoryChapter::create(['story_id' => $story->id, 'title' => ['en' => 'One'], 'sort_order' => 1]);
        foreach (range(1, $n) as $i) {
            StorySlide::create(['story_chapter_id' => $chapter->id, 'heading' => ['en' => "Slide $i"], 'image' => "stories/slide-$i.webp", 'sort_order' => $i]);
        }

        return $story;
    }

    private function staff(string $role = 'moderator'): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        $this->actingAs($user);

        return $user;
    }

    public function test_every_picture_gets_an_id_that_stays_and_the_pill_shows_it(): void
    {
        $this->story();

        $page = $this->get(route('gallery'))->assertOk();
        $ids = GalleryPicture::orderBy('id')->pluck('id')->all();

        $this->assertCount(3, $ids);
        foreach ($ids as $id) {
            $page->assertSee('I-'.$id);
        }
        $page->assertSee('gtile__id', false);

        Gallery::forget();
        $this->get(route('gallery'))->assertOk();
        $this->assertSame($ids, GalleryPicture::orderBy('id')->pluck('id')->all(), 'the same pictures keep their IDs');
    }

    public function test_a_picture_switched_off_is_left_out_of_the_gallery_but_not_of_its_page(): void
    {
        $story = $this->story();
        $this->get(route('gallery'));
        $off = GalleryPicture::where('path', 'stories/slide-2.webp')->first();

        $off->update(['is_shown' => false]);

        $this->get(route('gallery'))->assertOk()->assertSee('I-'.GalleryPicture::where('path', 'stories/slide-1.webp')->value('id'))->assertDontSee('I-'.$off->id.'<', false);
        $this->assertCount(2, Gallery::pictures());
        $this->get($story->url())->assertOk();
        $this->assertNotNull(StorySlide::where('image', 'stories/slide-2.webp')->first(), 'the slide and its picture are untouched');

        $off->update(['is_shown' => true]);
        $this->assertCount(3, Gallery::pictures());
    }

    public function test_a_picture_that_leaves_its_page_is_kept_in_the_register_and_comes_back_with_its_id(): void
    {
        $story = $this->story(2);
        $this->get(route('gallery'));
        $id = GalleryPicture::where('path', 'stories/slide-1.webp')->value('id');

        $story->update(['status' => 'draft']);
        Gallery::refresh();
        $this->assertFalse((bool) GalleryPicture::find($id)->present);
        $this->assertSame([], Gallery::pictures());

        $story->update(['status' => 'published']);
        Gallery::refresh();
        $this->assertTrue((bool) GalleryPicture::find($id)->present);
        $this->assertContains($id, array_column(Gallery::pictures(), 'id'));
    }

    public function test_a_renamed_picture_keeps_its_id_and_its_switch(): void
    {
        $this->story(1);
        $this->get(route('gallery'));
        $picture = GalleryPicture::first();
        $picture->update(['is_shown' => false]);

        PictureRedirect::remember('stories/slide-1.webp', 'stories/the-walk-slide-1-abc123.webp');

        $this->assertSame('stories/the-walk-slide-1-abc123.webp', $picture->fresh()->path);
        $this->assertFalse((bool) $picture->fresh()->is_shown);
    }

    public function test_the_id_address_opens_the_page_of_the_picture(): void
    {
        $story = $this->story(2);
        $this->get(route('gallery'));
        $picture = GalleryPicture::where('path', 'stories/slide-2.webp')->first();

        $this->get('/id/I-'.$picture->id)->assertRedirect($story->url().'#slide-2');
        $picture->update(['is_shown' => false]);
        $this->get('/id/I-'.$picture->id)->assertNotFound();
    }

    public function test_the_admin_list_shows_the_pictures_and_switches_them(): void
    {
        $this->story(3);
        $this->staff();

        $this->get('/admin/gallery-pictures')->assertOk()->assertSee('Gallery pictures');
        $first = GalleryPicture::where('path', 'stories/slide-1.webp')->first();
        $second = GalleryPicture::where('path', 'stories/slide-2.webp')->first();

        Livewire::test(ListGalleryPictures::class)->assertCanSeeTableRecords(GalleryPicture::all())
            ->searchTable('I-'.$first->id)->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second])
            ->searchTable('')->callTableBulkAction('hide', [$first, $second]);

        $this->assertFalse((bool) $first->fresh()->is_shown);
        $this->assertFalse((bool) $second->fresh()->is_shown);
        $this->assertCount(1, Gallery::pictures());

        Livewire::test(ListGalleryPictures::class)->callTableBulkAction('show', [$first, $second]);
        $this->assertCount(3, Gallery::pictures());
    }

    public function test_only_a_moderator_or_the_super_user_may_use_the_list(): void
    {
        $this->story();
        $this->staff('author');
        $this->get('/admin/gallery-pictures')->assertForbidden();

        $this->staff('superuser');
        $this->get('/admin/gallery-pictures')->assertOk();
    }
}
