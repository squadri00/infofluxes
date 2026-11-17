<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 83: the book is a real flipbook (StPageFlip: a curl, a dragged corner, shadows), with a bar under it and a fullscreen button. */
class Step83Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function story(): Story
    {
        $cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
        $story = Story::create(['title' => ['en' => 'The Walk'], 'slug' => 'the-walk', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'A man walks.']]);
        $ch = $story->chapters()->create(['title' => ['en' => 'The opening'], 'sort_order' => 1]);
        $ch->slides()->create(['sort_order' => 1, 'heading' => ['en' => 'The hill'], 'body' => ['en' => 'A man walked to the hill.']]);

        return $story->fresh();
    }

    public function test_the_helper_is_kept_on_this_site_with_its_licence(): void
    {
        $js = public_path('js/vendor/page-flip.browser.min.js');
        $this->assertFileExists($js);
        $this->assertGreaterThan(30000, filesize($js));
        $this->assertStringContainsString('PageFlip', (string) file_get_contents($js));
        $this->assertStringNotContainsString('fetch(', (string) file_get_contents($js), 'it does not call any other site');

        $licence = (string) file_get_contents(public_path('js/vendor/page-flip.LICENSE.txt'));
        $this->assertStringContainsString('MIT License', $licence);
        $this->assertStringContainsString('Nodlik', $licence);
    }

    public function test_the_helper_is_loaded_from_this_site_only_when_the_book_is_chosen(): void
    {
        $this->story();

        $plain = $this->get('/stories/the-walk')->assertOk()->getContent();
        $this->assertStringNotContainsString('page-flip.browser.min.js', $plain);

        Setting::put('viewer_transition', 'book');
        $book = $this->get('/stories/the-walk')->assertOk()->getContent();
        $this->assertStringContainsString(asset('js/vendor/page-flip.browser.min.js'), $book);
        $this->assertLessThan(strpos($book, 'js/story.js'), strpos($book, 'page-flip.browser.min.js'), 'the helper comes before story.js');
        $this->assertStringContainsString('"fullscreen":"Fullscreen"', $book, 'the words of the bar under the book');
    }

    public function test_the_script_builds_single_pages_and_keeps_the_viewer_in_step_with_the_book(): void
    {
        $js = (string) file_get_contents(public_path('js/story.js'));

        foreach ([
            'new St.PageFlip(', 'showPageCorners: true', 'showCover: false', 'usePortrait: false', "fb.on('flip'",
            'function fbPages()', 'pages.length % 2 === 1',
            'if (fb) { if (step > 0) { fb.flipNext(); } else { fb.flipPrev(); } return; }',
            'root.requestFullscreen', "addEventListener('fullscreenchange'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle);
        }
        $this->assertStringContainsString("pages.push(fbPage('blank'))", $js, 'a blank page keeps every picture on a left page');
    }

    public function test_the_style_sheet_has_the_pages_the_bar_and_fullscreen(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        foreach ([
            '.fb-page.--left::before', '.fb-page.--right::before', '.fb-bar__page', '.story:fullscreen',
            '.story--fb .story__track { display: none !important; }',
            '.story--fb .fb, .story--fb .story__stage, .story--fb .viewer { overflow-x: clip; }',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
    }

    public function test_the_pages_have_no_drawn_corner_fold_all_four_stars_stay_and_the_spiral_is_gone(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        // the corner of a page is the library's own (it lifts when the mouse is near; a corner can be dragged), not a drawing on the page
        $this->assertStringNotContainsString('.fb-page .fb-page__in::after', $css);
        $this->assertStringNotContainsString('.fb-page .fb-page__in::before', $css);
        $this->assertStringNotContainsString('scaleX(-1)', $css);
        $this->assertStringContainsString('background-image: var(--frame-star), var(--frame-star), var(--frame-star), var(--frame-star);', $css, 'a star in each of the four corners');
        $this->assertStringNotContainsString('.viewer--book .story__stage::before', $css, 'the spiral binding is gone');
    }
    public function test_the_words_scroll_inside_the_frame(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        $this->assertStringContainsString('.story--js .fb-page--text .fb-page__in > .slide__text { inset: 26px; }', $css, 'the scrolling area starts just inside the gold line');
        $this->assertStringContainsString('.story--js .fb-page--text.--left .fb-page__in > .slide__text { padding: 30px 30px 30px 54px; }', $css);
        $this->assertStringContainsString('.story--js .fb-page--text.--right .fb-page__in > .slide__text { padding: 30px 54px 30px 30px; }', $css);
    }

    public function test_a_click_on_a_picture_enlarges_it_and_another_click_goes_back(): void
    {
        $js = (string) file_get_contents(public_path('js/story.js'));
        $css = (string) file_get_contents(public_path('css/site.css'));

        foreach (["zoomBtn.className = 'fb-zoom'", 'function zoomOpen(', 'function zoomClose()', "zoomEl.addEventListener('click', zoomClose)", "e.key === 'Escape'", 'zoomEl ||'] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle);
        }
        $this->assertStringContainsString("zoomBtn.type = 'button'", $js, 'a button, so the page-turning helper does not turn the page');
        foreach (['.fb-zoom { position: absolute; inset: 0;', '.fb-zoom-view { position: fixed; inset: 0;', 'cursor: zoom-out'] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
        $this->assertSame('Enlarge the picture', __('site.story_zoom'));
        $this->assertSame('Click to go back', __('site.story_zoom_close'));
    }

    public function test_the_fullscreen_words_exist(): void
    {
        $this->assertSame('Fullscreen', __('site.story_fullscreen'));
        $this->assertSame('Exit fullscreen', __('site.story_fullscreen_exit'));
    }
}
