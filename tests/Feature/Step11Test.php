<?php

namespace Tests\Feature;

use App\Filament\Resources\Stories\Pages\EditStory;
use App\Filament\Resources\Stories\Pages\ListStories;
use App\Models\Story;
use App\Models\StoryChapter;
use App\Models\StorySlide;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Support\StoryImage;
use App\Support\StoryItems;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 11: a Visual Story. The slide viewer shows above the text. */
class Step11Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function article(string $title, array $extra = []): Story
    {
        return Story::create(array_merge([
            'title' => ['en' => $title], 'slug' => Story::uniqueSlug($title), 'category_id' => $this->cat->id,
            'status' => 'published', 'is_featured' => true, 'publish_date' => now()->subDay(),
        ], $extra));
    }

    private function chapter(Story $a, string $title, int $order = 1): StoryChapter
    {
        return $a->chapters()->create(['title' => ['en' => $title], 'sort_order' => $order]);
    }

    private function slide(StoryChapter $c, string $heading, array $extra = []): StorySlide
    {
        return $c->slides()->create(array_merge(['heading' => ['en' => $heading], 'body' => ['en' => 'Some words.'], 'sort_order' => 1], $extra));
    }

    /** A story: cover picture, two chapters, three slides. */
    private function story(): Story
    {
        $a = $this->article('A picture story', [
            'short_detail' => ['en' => 'The short words of the story.'], 'long_detail' => ['en' => '<p>The text under the viewer.</p>'],
            'main_image' => 'stories/cover.webp', 'main_image_alt' => ['en' => 'The cover picture'],
        ]);
        $one = $this->chapter($a, 'First chapter', 1);
        $this->slide($one, 'Slide one', ['image' => 'stories/one.webp', 'image_alt' => ['en' => 'A map'], 'image_credit' => 'Own work', 'sort_order' => 1]);
        $this->slide($one, 'Slide two', ['sort_order' => 2, 'body' => ['en' => "Para one.\n\nPara two with <b>tags</b> <script>x()</script>"]]);
        $two = $this->chapter($a, 'Second chapter', 2);
        $this->slide($two, 'Slide three', ['image' => 'stories/three.webp', 'sort_order' => 1]);

        return $a;
    }

    // ---------- the public page ----------

    public function test_a_story_with_slides_shows_the_viewer_above_the_text(): void
    {
        $a = $this->story();

        $html = $this->get($a->url())->assertOk()->getContent();

        $this->assertStringContainsString('data-story data-total="4"', $html, 'cover + 3 slides');
        $this->assertStringContainsString('data-viewer', $html);
        $this->assertStringNotContainsString('class="article__body"', $html, 'nothing of the story is written outside the viewer');
        $this->assertStringNotContainsString('The text under the viewer.', $html);
        $this->assertStringContainsString('js/story.js', $html);
        $this->assertStringContainsString('<h1 class="visually-hidden">A picture story', $html, 'the title is in the cover, so the heading is only for readers who use a screen reader');
        $this->assertStringNotContainsString('class="topic__master"', $html, 'the main picture is the cover of the viewer, not shown twice');
        $this->assertStringContainsString('class="slide__heading slide__heading--cover">A picture story', $html);
        $this->assertStringContainsString('The short words of the story.', $html);
        $this->assertStringContainsString('Slide 2 of 4', $html);
    }

    public function test_the_cover_lists_the_chapters_and_their_slides_and_the_chapter_buttons_are_there(): void
    {
        $html = $this->get($this->story()->url())->getContent();

        $this->assertStringContainsString('data-story-goto="2">First chapter</a>', $html, 'the first chapter starts at slide 2 (the cover is slide 1)');
        $this->assertStringContainsString('data-story-goto="2">Slide one</a>', $html);
        $this->assertStringContainsString('data-story-goto="3">Slide two</a>', $html);
        $this->assertStringContainsString('data-story-goto="4">Second chapter</a>', $html);
        $this->assertStringContainsString('data-chapter="0">First chapter</button>', $html);
        $this->assertStringContainsString('data-chapter="1">Second chapter</button>', $html);
        $this->assertStringContainsString('data-story-home', $html);
    }

    public function test_slides_are_in_their_order_and_the_numbers_run_from_the_cover(): void
    {
        $a = $this->article('Ordered');
        $c2 = $this->chapter($a, 'Later chapter', 2);
        $c1 = $this->chapter($a, 'Earlier chapter', 1);
        $this->slide($c2, 'Z last', ['sort_order' => 1]);
        $this->slide($c1, 'B second', ['sort_order' => 2]);
        $this->slide($c1, 'A first', ['sort_order' => 1]);
        $this->chapter($a, 'Empty chapter', 3);

        $items = StoryItems::build($a->fresh());

        $this->assertSame(['cover', 'slide', 'slide', 'slide'], array_column($items, 'type'));
        $this->assertSame([1, 2, 3, 4], array_column($items, 'n'));
        $this->assertSame(['A first', 'B second', 'Z last'], array_map(fn ($i) => $i['slide']->heading, array_slice($items, 1)));
        $this->assertSame(['Earlier chapter', 'Later chapter'], array_column($items[0]['index'], 'title'), 'an empty chapter is left out');
    }

    public function test_a_picture_credit_is_shown_and_an_empty_one_says_ai_generated(): void
    {
        $html = $this->get($this->story()->url())->getContent();

        $this->assertStringContainsString('Picture: Own work', $html);
        $this->assertStringContainsString('Picture: AI Generated', $html, 'the old rule: no credit means AI Generated');
        $this->assertStringContainsString('<span class="slide__caption-text">A map</span>', $html);
        $this->assertStringContainsString('alt="A map"', $html);
    }

    public function test_a_slide_without_a_picture_is_a_text_slide(): void
    {
        $a = $this->article('Words only');
        $this->slide($this->chapter($a, 'Only chapter'), 'No picture here');

        $this->assertStringContainsString('slide slide--text', $this->get($a->url())->getContent());
    }

    public function test_slide_text_is_shown_as_safe_paragraphs(): void
    {
        $html = $this->get($this->story()->url())->getContent();

        $this->assertStringContainsString('<p>Para one.</p><p>Para two with &lt;b&gt;tags&lt;/b&gt; &lt;script&gt;x()&lt;/script&gt;</p>', $html);
        $this->assertStringNotContainsString('<script>x()', $html);
    }

    public function test_an_article_without_slides_is_the_same_page_as_before(): void
    {
        $a = $this->article('Plain article', ['main_image' => 'stories/plain.webp', 'main_image_alt' => ['en' => 'A plain picture'], 'main_image_credit' => 'Some museum', 'main_image_licence' => 'Public domain']);

        $html = $this->get($a->url())->assertOk()->getContent();

        $this->assertStringNotContainsString('data-story', $html);
        $this->assertStringNotContainsString('js/story.js', $html);
        $this->assertStringContainsString('<h1 class="topic__title">Plain article', $html);
        $this->assertStringContainsString('class="topic__master"', $html);
        $this->assertStringContainsString('Picture: Some museum · Public domain', $html, 'the main picture shows its credit and licence');
    }

    public function test_the_story_pictures_are_protected_and_marked_like_the_old_viewer(): void
    {
        $html = $this->get($this->story()->url())->getContent();

        $this->assertStringContainsString('slide__media is-protected', $html);
        $this->assertStringContainsString('class="slide__shield"', $html);
        $this->assertStringContainsString('slide__mark-name">InfoFluxes', $html);

        Setting::put('story_protect_images', '0');
        Setting::put('story_watermark_on', '0');
        $off = $this->get($this->story()->url().'x')->getStatusCode();   // another address: just makes sure nothing breaks
        $this->assertSame(404, $off);
    }

    public function test_a_draft_story_is_not_public_but_staff_can_preview_it(): void
    {
        $a = $this->story();
        $a->update(['status' => 'draft']);
        $this->get($a->url())->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->get($a->url())->assertOk()->assertSee('data-story', false);
    }

    // ---------- the cards ----------

    public function test_cards_show_how_many_slides_a_story_has(): void
    {
        $this->story();                                         // 3 slides
        $one = $this->article('Single slide story');
        $this->slide($this->chapter($one, 'Only'), 'The one');
        $this->article('No slides at all');

        foreach (['/', '/category/shows', '/search?q=picture'] as $url) {
            $this->assertStringContainsString('3 slides', $this->get($url)->assertOk()->getContent(), $url);
        }
        $home = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('/\b1 slide\b/', $home);
        // One note on each card that has slides: the story of 3 slides and the one of 1 slide (the small card names the slides once, in its last line).
        $this->assertSame(2, preg_match_all('/\d+ slides?\b/', $home), 'every story card carries a note');
    }

    public function test_the_story_viewer_script_and_its_style_exist(): void
    {
        $this->assertFileExists(public_path('js/story.js'));
        $this->assertStringContainsString('.story__stage', (string) file_get_contents(public_path('css/site.css')));
        $this->assertStringContainsString('.slide__media', (string) file_get_contents(public_path('css/site.css')));
    }

    // ---------- the admin ----------

    public function test_chapters_and_slides_are_saved_from_the_story_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $a = $this->article('To tell as a story', ['status' => 'draft']);

        Livewire::test(EditStory::class, ['record' => $a->getRouteKey()])
            ->fillForm(['chapters' => [
                ['title' => ['en' => 'Chapter A'], 'slides' => [
                    ['heading' => ['en' => 'First slide'], 'body' => ['en' => 'First words.'], 'image_credit' => 'Me'],
                    ['heading' => ['en' => 'Second slide'], 'body' => ['en' => 'Second words.']],
                ]],
                ['title' => ['en' => 'Chapter B'], 'slides' => [['heading' => ['en' => 'Third slide']]]],
            ]])
            ->call('save')->assertHasNoFormErrors();

        $a = $a->fresh();
        $this->assertSame(['Chapter A', 'Chapter B'], $a->chapters->map->getTranslation('title', 'en')->all());
        $this->assertSame(['First slide', 'Second slide', 'Third slide'], $a->slides()->orderBy('story_chapters.sort_order')->orderBy('story_slides.sort_order')->get()->map->getTranslation('heading', 'en')->all());
        $this->assertTrue($a->hasSlides());
        $this->assertSame('Me', $a->slides()->orderBy('story_slides.id')->first()->image_credit);
    }

    public function test_a_chapter_needs_a_name_and_a_slide_needs_a_heading(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $a = $this->article('Needs names', ['status' => 'draft']);

        Livewire::test(EditStory::class, ['record' => $a->getRouteKey()])
            ->fillForm(['chapters' => [['title' => ['en' => ''], 'slides' => [['heading' => ['en' => '']]]]]])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame(0, $a->fresh()->chapters()->count());
    }

    public function test_the_edit_page_shows_the_story_slides_and_what_is_already_there(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $a = $this->story();

        Livewire::test(EditStory::class, ['record' => $a->getRouteKey()])
            ->assertSee('Chapters and slides')->assertSee('NEVER show or draw the Prophets')
            ->assertFormSet(fn (array $state) => count($state['chapters']) === 2);
    }

    public function test_a_clone_gets_its_own_copy_of_the_chapters_and_slides(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $a = $this->story();

        Livewire::test(ListStories::class)->callAction(TestAction::make('replicate')->table($a));

        $copy = Story::where('id', '!=', $a->id)->first();
        $this->assertSame(2, $copy->chapters()->count());
        $this->assertSame(3, $copy->slides()->count());
        $this->assertSame(3, $a->fresh()->slides()->count(), 'the original keeps its own');
        $this->assertSame('Own work', $copy->slides()->whereNotNull('image_credit')->first()->image_credit);
        $this->assertSame('draft', $copy->status);
    }

    public function test_deleting_a_story_for_good_removes_its_slides(): void
    {
        $a = $this->story();
        $a->forceDelete();

        $this->assertSame(0, StoryChapter::count());
        $this->assertSame(0, StorySlide::count());
    }

    // ---------- the pictures ----------

    public function test_a_picture_becomes_a_small_webp_in_the_shape_of_the_site(): void
    {
        Storage::fake('public');
        $im = imagecreatetruecolor(1000, 1000);                    // a square picture
        imagefilledrectangle($im, 0, 0, 1000, 1000, imagecolorallocate($im, 200, 40, 40));
        $path = tempnam(sys_get_temp_dir(), 'pic').'.png';
        imagepng($im, $path);

        $stored = StoryImage::store($path);

        Storage::disk('public')->assertExists($stored);
        $this->assertStringStartsWith('stories/', $stored);
        $this->assertStringEndsWith('.webp', $stored);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($stored));
        $this->assertEqualsWithDelta(16 / 9, $w / $h, 0.02, 'cut to 16:9');
        $this->assertLessThanOrEqual(1200, $w);
        unlink($path);
    }

    public function test_a_file_that_is_not_a_picture_is_refused(): void
    {
        Storage::fake('public');
        $path = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        StoryImage::store($path);
    }
}
