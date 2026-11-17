<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Support\SiteControls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 78: "Recent" in the header and "Continue where you left off" on the home page (the list stays in the visitor's browser). */
class Step78Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    public function test_the_home_page_has_the_edge_tab_and_the_strip(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-recent-tab', $html);
        $this->assertStringContainsString('data-recent-tab-btn', $html);
        $this->assertStringContainsString('data-recent-tab-close', $html);
        $this->assertStringContainsString('data-recent-menu', $html);
        $this->assertStringContainsString('data-recent-strip', $html);
        $this->assertStringContainsString('Continue where you left off', $html);
        $this->assertStringContainsString('Clear list', $html);
        $this->assertStringContainsString('js/recent.js', $html);
    }

    public function test_the_switch_removes_the_button_the_strip_and_the_script(): void
    {
        SiteControls::set('recent_menu', false);
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-recent-menu', $html);
        $this->assertStringNotContainsString('data-recent-tab', $html);
        $this->assertStringNotContainsString('data-recent-strip', $html);
        $this->assertStringNotContainsString('js/recent.js', $html);
    }

    public function test_the_home_page_does_not_remember_itself(): void
    {
        $this->assertStringNotContainsString('data-recent-url', $this->get('/')->getContent());
    }

    public function test_an_article_names_itself_for_the_list(): void
    {
        Article::create(['title' => ['en' => 'Why we pray'], 'slug' => 'why-we-pray', 'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => '<p>x</p>']]);
        $html = $this->get('/why-we-pray')->assertOk()->getContent();

        $this->assertStringContainsString('data-recent-url="'.url('/why-we-pray').'"', $html);
        $this->assertStringContainsString('data-recent-title="Why we pray"', $html);
        $this->assertStringContainsString('data-recent-tab', $html, 'the edge tab is on every page');
    }

    public function test_a_story_names_itself_in_its_own_language_address(): void
    {
        $cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
        $story = Story::create(['title' => ['en' => 'The Walk'], 'slug' => 'the-walk', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'A man walks.']]);

        $html = $this->get('/stories/the-walk')->assertOk()->getContent();
        $this->assertStringContainsString('data-recent-url="'.$story->url().'"', $html);
        $this->assertStringContainsString('data-recent-title="The Walk"', $html);
    }

    public function test_the_script_keeps_the_list_in_the_browser_only_and_can_clear_it(): void
    {
        $js = (string) file_get_contents(public_path('js/recent.js'));

        $this->assertStringContainsString('localStorage', $js);
        $this->assertStringContainsString('data-recent-clear', $js);
        $this->assertStringNotContainsString('fetch(', $js);
        $this->assertStringNotContainsString('XMLHttpRequest', $js);
    }

    public function test_the_hero_banner_has_a_sound_button_for_its_video_clips(): void
    {
        $js = (string) file_get_contents(public_path('js/hero.js'));
        $this->assertStringContainsString('hero__btn--sound', $js);
        $this->assertStringContainsString('data-label-sound-on', $js);

        $partial = (string) file_get_contents(resource_path('views/partials/hero.blade.php'));
        $this->assertStringContainsString('data-label-sound-on', $partial);
        $this->assertStringContainsString('data-label-sound-off', $partial);

        $this->assertSame('Turn the sound on', __('site.hero_sound_on'));
        $this->assertSame('Turn the sound off', __('site.hero_sound_off'));
    }

    public function test_the_banner_video_box_has_the_shape_of_the_clips_and_shows_them_whole(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));
        $this->assertStringContainsString('.hero:not(.hero--full):not(.hero--text-only) .hero__media { aspect-ratio: 16 / 9;', $css);
        $this->assertStringContainsString('.hero:not(.hero--full) .hero__video { object-fit: contain;', $css);
    }

    public function test_the_words_exist(): void
    {
        foreach (['recent_menu', 'recent_continue', 'recent_clear', 'recent_close', 'recent_title', 'recent_none'] as $key) {
            $this->assertNotSame('site.'.$key, __('site.'.$key), $key);
        }
    }
}
