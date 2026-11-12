<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Support\Html;
use App\Support\SiteMenu;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 4: the public site: home, category page, article page, search, menu, language switch, safe text. */
class Step04Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function cat(string $name, ?Category $parent = null, array $extra = []): Category
    {
        return Category::create(array_merge(['name' => ['en' => $name], 'slug' => str($name)->slug()->toString(), 'parent_id' => $parent?->id, 'sort_order' => 1], $extra));
    }

    private function article(string $title, Category $cat, array $extra = []): Article
    {
        return Article::create(array_merge([
            'title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'category_id' => $cat->id,
            'status' => 'published', 'publish_date' => now()->subDay(),
        ], $extra));
    }

    public function test_the_home_page_lists_categories_and_the_latest_articles(): void
    {
        $shows = $this->cat('Shows');
        $this->article('Great article', $shows, ['short_detail' => ['en' => 'A short line.']]);

        $this->get('/')->assertOk()->assertSee('Shows')->assertSee('Great article')->assertSee('A short line.');
    }

    public function test_the_main_menu_follows_the_category_tree(): void
    {
        $shows = $this->cat('Shows');
        $this->cat('Popular Shows', $shows);
        $this->cat('Secret', null, ['is_visible' => false]);

        $menu = SiteMenu::header();
        $this->assertSame(['Shows'], array_column($menu, 'label'));
        $this->assertSame('Popular Shows', $menu[0]['children'][0]['label']);
        $this->get('/')->assertSee('Popular Shows')->assertDontSee('Secret');
    }

    public function test_the_category_page_shows_sub_categories_articles_and_its_code(): void
    {
        $shows = $this->cat('Shows', null, ['description' => ['en' => 'All the shows.']]);
        $pop = $this->cat('Popular Shows', $shows);
        $this->article('In shows', $shows);
        $this->article('Also in shows', $pop)->extraCategories()->attach($shows->id);

        $this->get('/category/shows')->assertOk()
            ->assertSee('Shows')->assertSee($shows->code)->assertSee('All the shows.')
            ->assertSee('Popular Shows')->assertSee('category-menu__btn', false)
            ->assertSee('In shows')->assertSee('Also in shows');
    }

    public function test_a_hidden_category_or_one_inside_a_hidden_one_is_not_found(): void
    {
        $hidden = $this->cat('Hidden', null, ['is_visible' => false]);
        $inside = $this->cat('Inside hidden', $hidden);

        $this->get('/category/hidden')->assertNotFound();
        $this->get('/category/inside-hidden')->assertNotFound();
    }

    public function test_the_article_page_shows_everything_that_was_filled_and_nothing_else(): void
    {
        $shows = $this->cat('Shows');
        $other = $this->cat('Blogs');
        $a = $this->article('Full article', $shows, [
            'short_detail' => ['en' => 'The short words.'], 'long_detail' => ['en' => '<p>The long <strong>words</strong>.</p>'],
            'reviewer_name' => 'Dr Check', 'source_line' => 'Book One, page 5', 'search_words' => ['alpha', 'beta words'],
        ]);
        $a->extraCategories()->attach($other->id);
        $a->videos()->create(['url' => 'https://youtu.be/dQw4w9WgXcQ']);
        $a->images()->create(['image' => 'articles/x.webp', 'alt' => ['en' => 'A picture']]);

        $this->get('/full-article')->assertOk()
            ->assertSee('Full article')->assertSee($a->code)->assertSee('The short words.')
            ->assertSee('The long <strong>words</strong>.', false)
            ->assertSee('Reviewed by')->assertSee('Dr Check')
            ->assertSee('Book One, page 5')
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Also in')->assertSee('Blogs')
            ->assertSee('beta words');

        $plain = $this->article('Plain article', $shows);
        $this->get('/plain-article')->assertOk()->assertDontSee('Reviewed by')->assertDontSee('topic__source', false)->assertDontSee('article__videos', false);
    }

    public function test_drafts_and_future_articles_are_hidden_from_visitors_but_staff_can_preview(): void
    {
        $shows = $this->cat('Shows');
        $this->article('Draft one', $shows, ['status' => 'draft']);
        $this->article('Future one', $shows, ['publish_date' => now()->addDay()]);

        $this->get('/draft-one')->assertNotFound();
        $this->get('/future-one')->assertNotFound();
        $this->get('/')->assertDontSee('Draft one')->assertDontSee('Future one');

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active']));
        $this->get('/draft-one')->assertOk()->assertSee('This is a draft')->assertSee('noindex', false);
    }

    public function test_written_text_is_made_safe(): void
    {
        $dirty = '<p onclick="x()">Hi <script>alert(1)</script><a href="javascript:alert(1)">bad</a> <a href="https://example.org">good</a><img src=x onerror=y><iframe src="https://evil"></iframe></p><style>p{}</style>';
        $clean = Html::clean($dirty);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringContainsString('href="https://example.org"', $clean);
        $this->assertStringContainsString('rel="nofollow noopener"', $clean);
    }

    public function test_search_finds_by_title_words_and_search_words_and_can_be_limited_to_a_category(): void
    {
        $shows = $this->cat('Shows');
        $blogs = $this->cat('Blogs');
        $this->article('Zebra story', $shows, ['search_words' => ['stripes']]);
        $this->article('Other story', $blogs, ['long_detail' => ['en' => '<p>about zebra herds</p>']]);
        $this->article('Hidden zebra', $shows, ['status' => 'draft']);

        $this->get('/search?q=zebra')->assertOk()->assertSee('Zebra story')->assertSee('Other story')->assertDontSee('Hidden zebra');
        $this->get('/search?q=stripes')->assertSee('Zebra story')->assertDontSee('Other story');
        $this->get('/search?q=zebra&category='.$blogs->id)->assertSee('Other story')->assertDontSee('Zebra story');
        $this->get('/search?q=nothingfoundhere')->assertSee('Nothing was found');
        $this->get('/search')->assertOk()->assertSee('Type a word');
    }

    public function test_a_reference_code_opens_its_page(): void
    {
        $shows = $this->cat('Shows');
        $a = $this->article('Coded', $shows);

        $this->get('/id/'.$a->code)->assertRedirect(route('article.show', 'coded'));
        $this->get('/id/'.$shows->code)->assertRedirect(route('category.show', 'shows'));
        $this->get('/id/A-99999')->assertNotFound();
    }

    public function test_the_sitemap_lists_visible_pages_only(): void
    {
        $shows = $this->cat('Shows');
        $this->cat('Secret', null, ['is_visible' => false]);
        $this->article('Open one', $shows);
        $this->article('Closed one', $shows, ['status' => 'draft']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/category/shows', $xml);
        $this->assertStringContainsString('/open-one', $xml);
        $this->assertStringNotContainsString('secret', $xml);
        $this->assertStringNotContainsString('closed-one', $xml);
    }

    public function test_a_missing_page_shows_the_friendly_not_found_page(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found')->assertSee('Go to the home page');
    }

    public function test_the_language_button_shows_only_when_more_than_one_language_is_on(): void
    {
        $this->get('/')->assertDontSee('lang-menu', false);

        config(['infofluxes.locales' => ['en' => 'English', 'ar' => 'Arabic']]);
        $this->get('/')->assertSee('lang-menu', false)->assertSee('Arabic');
    }

    public function test_a_visitor_can_choose_a_language_and_the_text_follows(): void
    {
        config(['infofluxes.locales' => ['en' => 'English', 'ar' => 'Arabic'], 'infofluxes.rtl_locales' => ['ar']]);
        $shows = $this->cat('Shows');
        $a = $this->article('English title', $shows);
        $a->setTranslation('title', 'ar', 'عنوان عربي')->save();

        $this->get('/english-title')->assertSee('English title');
        $this->get('/language/ar')->assertRedirect();
        $this->get('/english-title')->assertSee('عنوان عربي')->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false);

        $this->get('/language/xx');
        $this->get('/english-title')->assertSee('عنوان عربي', false);
    }

    public function test_a_missing_translation_falls_back_to_english(): void
    {
        config(['infofluxes.locales' => ['en' => 'English', 'ar' => 'Arabic']]);
        $this->article('Only English', $this->cat('Shows'));

        $this->withSession(['locale' => 'ar'])->get('/only-english')->assertOk()->assertSee('Only English');
    }

    public function test_the_site_frame_words_come_from_settings(): void
    {
        $this->get('/')->assertSee('Every belief has a source');

        Setting::put('tagline', ['en' => 'My own tagline']);
        Setting::put('site_name', ['en' => 'My Site Name']);
        $this->get('/')->assertSee('My own tagline')->assertSee('My Site Name');
    }

    public function test_only_the_super_user_opens_site_settings_and_saves_them(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(SiteSettings::canAccess());

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->assertTrue(SiteSettings::canAccess());

        Livewire::test(SiteSettings::class)
            ->fillForm(['site_name' => ['en' => 'Saved Name'], 'tagline' => ['en' => 'Saved tagline'], 'social_facebook' => 'https://facebook.com/x'])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::forget();
        $this->get('/')->assertSee('Saved Name')->assertSee('Saved tagline')->assertSee('https://facebook.com/x', false);
    }
}
