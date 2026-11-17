<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Support\ViewerStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 80: the slide viewer can turn pages like an open book (a choice in Settings > Slide viewer). */
class Step80Test extends TestCase
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
        $ch->slides()->create(['sort_order' => 2, 'heading' => ['en' => 'The end'], 'body' => ['en' => 'He went home.']]);

        return $story->fresh();
    }

    public function test_the_book_is_one_of_the_choices_for_how_a_slide_gives_way_to_the_next(): void
    {
        $options = ViewerStyle::SETTINGS['viewer_transition'][3];

        $this->assertArrayHasKey('book', $options);
        $this->assertArrayHasKey('glide', $options, 'the old choices stay');
        $this->assertSame('glide', ViewerStyle::SETTINGS['viewer_transition'][2], 'the book is a choice, not the default');
    }

    public function test_with_the_book_chosen_the_viewer_says_so_to_the_script(): void
    {
        $this->story();
        Setting::put('viewer_transition', 'book');
        $html = $this->get('/stories/the-walk')->assertOk()->getContent();

        $this->assertStringContainsString('data-transition="book"', $html);
        $this->assertStringContainsString('viewer--book', $html);
    }

    public function test_without_the_choice_the_viewer_is_not_a_book(): void
    {
        $this->story();
        $html = $this->get('/stories/the-walk')->assertOk()->getContent();

        $this->assertStringNotContainsString('viewer--book', $html);
        $this->assertStringContainsString('data-transition="glide"', $html);
    }

    public function test_a_wrong_value_falls_back_to_the_default(): void
    {
        $this->story();
        Setting::put('viewer_transition', 'spin');
        $this->get('/stories/the-walk')->assertOk()->assertSee('data-transition="glide"', false);
    }

    public function test_the_script_turns_the_page_with_copies_and_keeps_to_calm_and_phone_rules(): void
    {
        $js = (string) file_get_contents(public_path('js/story.js'));

        $this->assertStringContainsString("=== 'book'", $js);
        $this->assertStringContainsString('function flip(', $js);
        $this->assertStringContainsString('cloneNode(true)', $js, 'the leaf is made of copies, the real slides are not touched');
        $this->assertStringContainsString('prefers-reduced-motion', $js);
        $this->assertStringContainsString('window.innerWidth >= 720', $js, 'on a phone the page changes at once');
    }

    public function test_the_style_sheet_has_the_book_and_its_turning_leaf(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        foreach (['.viewer--book .story__stage', '.book-flip', '.book-leaf', '.book-face--back', 'backface-visibility: hidden', 'rotateY(180deg)'] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
    }
}
