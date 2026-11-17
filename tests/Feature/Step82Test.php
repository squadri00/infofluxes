<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Support\StoryMaker\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 82: the top row of the viewer (Home and chapters left; "Read in" and the page number right), page numbers, the spiral, no blank page. */
class Step82Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function story(bool $ur = false): Story
    {
        $cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
        $story = Story::create(['title' => ['en' => 'The Walk'], 'slug' => 'the-walk', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'A man walks.']]);
        $ch = $story->chapters()->create(['title' => ['en' => 'The opening'], 'sort_order' => 1]);
        $ch->slides()->create(['sort_order' => 1, 'heading' => ['en' => 'The hill'], 'body' => ['en' => 'A man walked to the hill.']]);
        $ch->slides()->create(['sort_order' => 2, 'heading' => ['en' => 'The end'], 'body' => ['en' => 'He went home.']]);
        if ($ur) {
            $story->setTranslation('title', 'ur', 'چہل قدمی');
            $story->save();
            Translations::saved($story->fresh(), 'ur', null);
            Translations::set($story->fresh(), 'ur', 'on');
        }

        return $story->fresh();
    }

    public function test_the_top_row_holds_the_chapters_and_on_the_right_the_language_row_then_the_page_number(): void
    {
        $this->story(true);
        $html = $this->get('/stories/the-walk?lang=en')->assertOk()->getContent();

        $bar = strpos($html, 'class="story__bar"');
        $this->assertNotFalse($bar);
        $chapters = strpos($html, 'data-story-chapters', $bar);
        $right = strpos($html, 'class="story__bar-right"', $bar);
        $langs = strpos($html, 'class="story-langs"', $bar);
        $counter = strpos($html, 'class="story__counter"', $bar);

        $this->assertLessThan($right, $chapters, 'the chapters come first, on the left');
        $this->assertLessThan($counter, $langs, 'Read in comes before the page number');
        $this->assertGreaterThan($right, $langs, 'both are in the right part of the row');
        $this->assertSame(1, substr_count($html, 'class="story-langs"'), 'the language row is written once');
    }

    public function test_without_a_translation_there_is_no_language_row_and_the_counter_is_still_there(): void
    {
        $this->story(false);
        $html = $this->get('/stories/the-walk')->assertOk()->getContent();

        $this->assertStringNotContainsString('class="story-langs"', $html);
        $this->assertStringContainsString('data-story-total', $html);
    }

    public function test_the_book_counts_pages_two_for_every_spread(): void
    {
        $js = (string) file_get_contents(public_path('js/story.js'));

        $this->assertStringContainsString('(2 * spreadNow + 1)', $js);
        $this->assertStringContainsString('(2 * spreadNow + 2)', $js);
        $this->assertStringContainsString('String(cfg.book ? spreads.length * 2 : total)', $js);
    }

    public function test_two_text_only_slides_of_a_chapter_share_a_spread_and_next_moves_by_spreads(): void
    {
        $js = (string) file_get_contents(public_path('js/story.js'));

        $this->assertStringContainsString('function layout()', $js);
        $this->assertStringContainsString("setAttribute('data-page', 'left')", $js);
        $this->assertStringContainsString("setAttribute('data-page', 'right')", $js);
        $this->assertStringNotContainsString('chapterOf(i + 1) === chapterOf(i)', $js, 'the next text slide goes on the right page even when a new chapter starts there');
        $this->assertStringContainsString('var n = spreadOf[current] + step;', $js, 'Next and Previous move from spread to spread');
        $this->assertStringContainsString('spreads[spread].forEach', $js, 'the turning leaf copies every slide of a spread');
    }

    public function test_the_style_sheet_has_the_spiral_and_single_text_pages(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        foreach ([
            '.viewer--book .story__stage::before', 'data:image/svg+xml', 'repeat-y center top',
            '.story__bar-right', '.story__bar .story__counter',
            // a text-only slide is one page: left or right, with its own scroll bar
            '.viewer--book .slide[data-page="left"] { justify-self: start; }',
            '.viewer--book .slide[data-page="right"] { justify-self: end; }',
            'contain: size; overflow-y: auto;',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
        $this->assertStringNotContainsString('--book-fs', $css, 'the words keep the normal size');
        $this->assertStringNotContainsString('column-fill: auto', $css);
    }

    public function test_the_script_does_not_change_the_size_of_the_words(): void
    {
        $js = (string) file_get_contents(public_path('js/story.js'));

        $this->assertStringNotContainsString('fitText', $js);
        $this->assertStringNotContainsString('--book-fs', $js);
    }

    public function test_the_book_has_an_old_paper_texture_and_the_ink_stays_easy_to_read(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        $this->assertStringContainsString('feTurbulence', $css, 'the grain and the stains are drawn, not downloaded');
        $this->assertStringContainsString('--paper-img:', $css);
        $this->assertStringContainsString('.viewer--book .book-face', $css, 'the turning page has the same paper');

        // the ink on the paper: at least 7 : 1 (the level for the clearest text)
        preg_match('/--paper-color:\s*(#[0-9a-f]{6})/i', $css, $paper);
        preg_match('/\.viewer--book \.slide__text \{[^}]*color:\s*(#[0-9a-f]{6})/i', $css, $ink);
        $this->assertNotEmpty($paper);
        $this->assertNotEmpty($ink);
        $light = fn (string $hex) => array_sum(array_map(fn ($c, $k) => $k * (($c /= 255) <= .03928 ? $c / 12.92 : (($c + .055) / 1.055) ** 2.4), array_map('hexdec', str_split(ltrim($hex, '#'), 2)), [.2126, .7152, .0722]));
        $a = $light($paper[1]);
        $b = $light($ink[1]);
        $ratio = (max($a, $b) + .05) / (min($a, $b) + .05);
        $this->assertGreaterThan(7.0, $ratio, 'contrast of the ink on the paper: '.round($ratio, 1));
    }

    public function test_every_page_has_a_mushaf_style_frame_and_the_book_is_taller(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        foreach ([
            '--frame-teal: #1d5c63;', '--frame-gold: #b8923f;', '--frame-star: url("data:image/svg+xml',
            '.viewer--book .slide::before, .viewer--book .slide::after {',     // the frame of the left and of the right page
            '.viewer--book .slide[data-page="left"]::before', '.viewer--book .slide[data-page="right"]::before',
            '--book-h: clamp(540px, 64vh, 700px);', '.story__stage { min-height: var(--book-h); }',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
    }

    public function test_the_picture_fills_the_framed_page_and_everything_keeps_a_gap_from_the_frame(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        // the picture: the whole page inside the frame (stretched, any ratio), 44px from the top, bottom and left, 56px from the spine side
        $this->assertStringContainsString('.slide__media { margin: 44px 56px 44px 44px; }', $css);
        $this->assertStringContainsString('height: auto; aspect-ratio: auto; max-height: none; align-self: stretch;', $css);
        // the stars are small enough not to cover the picture (28px, and the picture starts at 44px)
        $this->assertStringContainsString('background-size: 28px 28px;', $css);
        // the words keep their distance too
        $this->assertStringContainsString('.slide[data-page="left"] > .slide__text { padding: 58px 56px 58px 80px; }', $css);
        $this->assertStringContainsString('.slide[data-page="right"] > .slide__text { padding: 58px 80px 58px 56px; }', $css);
    }
}
