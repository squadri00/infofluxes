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

        $this->assertStringContainsString('(2 * i + 1)', $js);
        $this->assertStringContainsString('(2 * i + 2)', $js);
        $this->assertStringContainsString('totalEl.textContent = String(total * 2)', $js);
    }

    public function test_the_words_of_a_slide_without_a_picture_are_sized_to_fill_the_two_pages(): void
    {
        $js = (string) file_get_contents(public_path('js/story.js'));
        $css = (string) file_get_contents(public_path('css/site.css'));

        $this->assertStringContainsString('function fitText()', $js);
        $this->assertStringContainsString("setProperty('--book-fs'", $js);
        $this->assertStringContainsString('window.innerWidth < 720', $js, 'not on a phone');
        $this->assertStringContainsString('font-size: var(--book-fs, 1.15rem)', $css);
    }

    public function test_the_style_sheet_has_the_spiral_and_fills_both_pages_of_a_slide_without_a_picture(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        foreach ([
            '.viewer--book .story__stage::before', 'data:image/svg+xml', 'repeat-y center top',
            '.story__bar-right', '.story__bar .story__counter',
            'column-count: 2', 'column-gap: 100px',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
    }
}
