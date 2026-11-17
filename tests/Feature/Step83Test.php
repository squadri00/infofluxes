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

    public function test_every_page_shows_a_peeled_corner_and_the_spiral_is_gone(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        // the peeled corner: the corner that is gone, and the folded flap with its shadow; mirrored on a left page
        foreach ([
            '.fb-page .fb-page__in::after { background: var(--bg, #14171c); clip-path: polygon(100% 0, 0 100%, 100% 100%); }',
            'clip-path: polygon(100% 0, 0 100%, 0 0); filter: drop-shadow(',
            '.fb-page.--left .fb-page__in::before, .fb-page.--left .fb-page__in::after { left: 0; transform: scaleX(-1); }',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
        $this->assertStringNotContainsString('.viewer--book .story__stage::before', $css, 'the spiral binding is gone');
    }

    public function test_the_fullscreen_words_exist(): void
    {
        $this->assertSame('Fullscreen', __('site.story_fullscreen'));
        $this->assertSame('Exit fullscreen', __('site.story_fullscreen_exit'));
    }
}
