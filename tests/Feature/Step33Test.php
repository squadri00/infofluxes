<?php

namespace Tests\Feature;

use App\Filament\Resources\Stories\Pages\ListStories;
use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Support\StoryMover;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 33: Visual Stories have their own admin, their own S- code and their own pages. */
class Step33Test extends TestCase
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

    private function story(string $title = 'Moon story', array $extra = []): Story
    {
        $s = Story::create(array_merge(['title' => ['en' => $title], 'slug' => Story::uniqueSlug($title), 'category_id' => $this->cat->id, 'status' => 'published', 'is_featured' => true, 'publish_date' => now()->subDay()], $extra));
        $c = $s->chapters()->create(['title' => ['en' => 'One'], 'sort_order' => 1]);
        $c->slides()->create(['heading' => ['en' => 'Slide a'], 'body' => ['en' => 'Words.'], 'sort_order' => 1]);

        return $s;
    }

    private function asSuper(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    public function test_a_story_has_an_s_code_and_its_own_address(): void
    {
        $s = $this->story();

        $this->assertSame('S-'.$s->id, $s->code);
        $this->assertSame(url('/stories/moon-story'), $s->url());
        $this->get('/stories/moon-story')->assertOk()->assertSee('Moon story');
    }

    public function test_the_stories_page_lists_published_stories_only(): void
    {
        $this->story('Moon story');
        $this->story('Hidden story', ['status' => 'draft']);

        $this->get('/stories')->assertOk()->assertSee('Moon story')->assertDontSee('Hidden story');
        $this->get('/stories/hidden-story')->assertNotFound();
    }

    public function test_a_draft_can_be_previewed_by_an_admin(): void
    {
        $this->story('Hidden story', ['status' => 'draft']);
        $this->asSuper();

        $this->get('/stories/hidden-story')->assertOk();
    }

    public function test_the_s_code_redirects_and_search_finds_it(): void
    {
        $s = $this->story();

        $this->get('/id/'.$s->code)->assertRedirect($s->url());
        $this->get('/search?q='.$s->code)->assertOk()->assertSee('Moon story');
        $this->get('/search?q=moon')->assertOk()->assertSee('Moon story');
    }

    public function test_the_category_page_the_home_page_and_the_sitemap_show_the_story(): void
    {
        $this->story();

        $this->get('/category/shows')->assertOk()->assertSee('Moon story');
        $this->get('/')->assertOk()->assertSee('Moon story');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/stories/moon-story', false);
    }

    public function test_the_home_strip_can_be_switched_off(): void
    {
        $this->story();
        Setting::put('home_stories_count', 0);

        $this->get('/')->assertOk()->assertDontSee('All stories');
    }

    public function test_the_footer_links_to_the_stories_page(): void
    {
        $this->get('/')->assertDontSee('/stories', false);

        $this->story();
        Setting::forget();

        $this->get('/')->assertSee('/stories', false);
    }

    public function test_the_admin_list_shows_and_clones_a_story(): void
    {
        $s = $this->story();
        $this->asSuper();

        Livewire::test(ListStories::class)->assertCanSeeTableRecords([$s]);
    }

    public function test_an_author_can_open_the_stories_admin_but_a_plain_user_cannot(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active']));
        $this->get('/admin/stories')->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active']));
        $this->get('/admin/stories')->assertForbidden();
    }

    private function oldStoryArticle(): Article
    {
        $a = Article::create(['title' => ['en' => 'Old picture story'], 'slug' => 'old-picture-story', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'working_name' => 'T-14']);
        $c = $a->chapters()->create(['title' => ['en' => 'First'], 'sort_order' => 1]);
        $c->slides()->create(['heading' => ['en' => 'S1'], 'sort_order' => 1]);
        $c->slides()->create(['heading' => ['en' => 'S2'], 'sort_order' => 2]);

        return $a;
    }

    public function test_the_mover_does_nothing_without_apply(): void
    {
        $this->oldStoryArticle();

        $r = StoryMover::run(false);

        $this->assertCount(1, $r['stories']);
        $this->assertSame(0, Story::count());
        $this->assertSame(1, Article::count());
    }

    public function test_the_mover_copies_the_story_trashes_the_article_and_old_links_still_work(): void
    {
        $a = $this->oldStoryArticle();

        $r = StoryMover::run(true);

        $this->assertSame('moved', $r['stories'][0]['status']);
        $story = Story::first();
        $this->assertSame(1, $story->chapters()->count());
        $this->assertSame(2, $story->slides()->count());
        $this->assertSame('T-14', $story->custom_data['old_id']);
        $this->assertSame(0, Article::count());
        $this->assertSame(1, Article::onlyTrashed()->count());

        $this->get('/old-picture-story')->assertRedirect($story->url());
        $this->get('/id/'.$a->code)->assertRedirect($story->url());
    }

    public function test_the_mover_is_safe_to_run_twice(): void
    {
        $this->oldStoryArticle();
        StoryMover::run(true);

        $r = StoryMover::run(true);

        $this->assertSame([], $r['stories']);
        $this->assertSame(1, Story::count());
    }

    public function test_the_old_book_address_goes_to_the_new_one(): void
    {
        $this->oldStoryArticle();
        StoryMover::run(true);

        $this->get('/old-picture-story/book')->assertRedirect(route('story.book', 'old-picture-story'));
    }
}
