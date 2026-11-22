<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Setting;
use App\Models\Story;
use App\Models\StoryChapter;
use App\Models\StorySlide;
use App\Support\Gallery;
use App\Support\Thumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Step 115: the picture gallery: a fluid grid of pictures from the whole site, each leading to its page. */
class Step115Test extends TestCase
{
    use RefreshDatabase;

    private Category $master;
    private Category $leaf;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Gallery::forget();
        Storage::fake('public');
        $this->master = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);
        $this->leaf = Category::create(['name' => ['en' => 'Conquests'], 'slug' => 'conquests', 'parent_id' => $this->master->id]);
    }

    protected function tearDown(): void
    {
        Setting::forget();
        Gallery::forget();
        parent::tearDown();
    }

    private function realPicture(string $path): void
    {
        $im = imagecreatetruecolor(1200, 800);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 150));
        ob_start();
        imagepng($im);
        Storage::disk('public')->put($path, ob_get_clean());
    }

    /** A published story with $n slides, each with its own picture. */
    private function story(string $title, int $n, string $status = 'published'): Story
    {
        $story = Story::create(['title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'category_id' => $this->leaf->id, 'status' => $status, 'publish_date' => now()->subDay()]);
        $chapter = StoryChapter::create(['story_id' => $story->id, 'title' => ['en' => 'One'], 'sort_order' => 1]);
        foreach (range(1, $n) as $i) {
            StorySlide::create(['story_chapter_id' => $chapter->id, 'heading' => ['en' => "Slide $i of $title"], 'image' => 'stories/'.str($title)->slug().'-'.$i.'.webp', 'image_alt' => ['en' => "Picture $i"], 'sort_order' => $i]);
        }

        return $story;
    }

    public function test_the_gallery_lists_pictures_of_published_pages_and_each_leads_to_its_page(): void
    {
        $story = $this->story('The Long Walk', 3);
        $this->story('A Draft Story', 2, 'draft');
        Article::create(['title' => ['en' => 'An article'], 'slug' => 'an-article', 'category_id' => $this->leaf->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'main_image' => 'articles/an-article.webp']);
        DidYouKnow::create(['title' => ['en' => 'A fact'], 'slug' => 'a-fact', 'body' => ['en' => 'x'], 'source' => 'Book', 'status' => 'published', 'image' => 'facts/a-fact.webp']);

        $page = $this->get(route('gallery'))->assertOk();

        $page->assertSee('Picture gallery')->assertSee('data-gallery-grid', false)
            ->assertSeeHtml('href="'.$story->url().'#slide-2"')->assertSeeHtml('href="'.route('article.show', 'an-article').'"')->assertSeeHtml('href="'.route('category.show', 'history').'"', false)
            ->assertSee('alt="Picture 1"', false)->assertDontSee('A Draft Story');
        $this->assertCount(5, Gallery::pictures(), '3 slides, the article, the fact (a draft story and the hidden ones are left out)');
    }

    public function test_a_picture_used_in_two_places_is_listed_once_and_a_hidden_category_is_left_out(): void
    {
        $story = $this->story('Shared', 1);
        $story->update(['main_image' => 'stories/shared-1.webp']);
        Category::create(['name' => ['en' => 'Secret'], 'slug' => 'secret', 'is_visible' => false, 'image' => 'categories/secret.webp']);

        $sources = array_column(Gallery::pictures(), 'src');

        $this->assertSame(['stories/shared-1.webp'], $sources);
    }

    public function test_the_order_is_random_but_stays_the_same_for_a_visitor_and_pages_never_repeat_a_picture(): void
    {
        $this->story('Many', 30);
        $one = Gallery::shuffled(Gallery::pictures(), 'seed-a');
        $same = Gallery::shuffled(Gallery::pictures(), 'seed-a');
        $other = Gallery::shuffled(Gallery::pictures(), 'seed-b');

        $this->assertSame($one, $same);
        $this->assertNotSame(array_column($one, 'src'), array_column($other, 'src'));

        $first = $this->get(route('gallery', ['per' => 25]))->assertOk();
        $second = $this->get(route('gallery', ['per' => 25, 'page' => 2]))->assertOk();
        preg_match_all('#storage/(stories/[a-z0-9-]+\.webp)#', $first->getContent(), $a);
        preg_match_all('#storage/(stories/[a-z0-9-]+\.webp)#', $second->getContent(), $b);
        $this->assertCount(25, array_unique($a[1]));
        $this->assertSame([], array_intersect($a[1], $b[1]), 'the second page shows other pictures');
    }

    public function test_the_number_of_pictures_a_page_and_the_page_buttons(): void
    {
        $this->story('Many', 30);

        $this->get(route('gallery'))->assertOk()->assertDontSee('pager__page', false);           // 30 pictures fit on one page of 50
        $this->get(route('gallery', ['per' => 25]))->assertOk()->assertSee('Page 1 of 2')->assertSee('Next');
        $this->get(route('gallery', ['per' => 25, 'page' => 99]))->assertOk()->assertSee('Page 2 of 2');
        $this->get(route('gallery', ['per' => 7]))->assertOk()->assertDontSee('pager__page', false);   // 7 is not a choice: 50 is used

    }

    public function test_filters_by_kind_and_by_section_and_a_shuffle_starts_again(): void
    {
        $this->story('Walk', 2);
        $other = Category::create(['name' => ['en' => 'Science'], 'slug' => 'science']);
        Article::create(['title' => ['en' => 'Moon'], 'slug' => 'moon', 'category_id' => $other->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'main_image' => 'articles/moon.webp']);

        $this->get(route('gallery', ['kind' => 'article']))->assertOk()->assertSee('1 pictures')->assertSee(route('article.show', 'moon'), false);
        $this->get(route('gallery', ['section' => 'history']))->assertOk()->assertSee('2 pictures')->assertDontSee(route('article.show', 'moon'), false);
        $this->get(route('gallery', ['kind' => 'nonsense', 'section' => 'nowhere']))->assertOk()->assertSee('3 pictures');

        session(['gallery_seed' => 'old']);
        $this->get(route('gallery', ['shuffle' => 1, 'kind' => 'story']))->assertRedirect(route('gallery', ['kind' => 'story']));
        $this->assertNull(session('gallery_seed'));
    }

    public function test_a_small_copy_is_made_and_kept_and_the_picture_itself_is_used_when_it_cannot_be(): void
    {
        $this->realPicture('stories/big.png');

        $url = Thumbnail::url('stories/big.png');

        $this->assertStringContainsString('storage/thumbs/', $url);
        Storage::disk('public')->assertExists(Thumbnail::path('stories/big.png'));
        $this->assertLessThan(Storage::disk('public')->size('stories/big.png'), Storage::disk('public')->size(Thumbnail::path('stories/big.png')));
        $this->assertStringEndsWith('storage/stories/missing.webp', Thumbnail::url('stories/missing.webp'));
    }

    public function test_the_links_and_the_two_switches(): void
    {
        $this->story('Walk', 1);

        $home = $this->get(route('home'))->assertOk();
        $home->assertSee('data-gallery-link', false)->assertSee('See the whole picture gallery');
        $this->assertStringContainsString(route('gallery'), $this->get(route('home'))->getContent());
        $this->get(route('sitemap'))->assertSee(route('gallery'), false);

        Setting::put('ctl_gallery_header', '0');
        $this->get(route('home'))->assertDontSee('data-gallery-link', false)->assertSee('See the whole picture gallery');
        $this->get(route('gallery'))->assertOk();

        Setting::put('ctl_gallery', '0');
        $this->get(route('gallery'))->assertNotFound();
        $this->get(route('home'))->assertDontSee('See the whole picture gallery');
        $this->get(route('sitemap'))->assertDontSee(route('gallery'), false);
    }

    public function test_the_web_name_gallery_cannot_be_taken_by_a_page_or_a_category(): void
    {
        $this->assertContains('gallery', Category::RESERVED_SLUGS);
        $this->assertContains('gallery', \App\Models\Page::RESERVED);
    }

    public function test_an_empty_gallery_says_so(): void
    {
        $this->get(route('gallery'))->assertOk()->assertSee('There are no pictures here yet.');
    }
}
