<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Setting;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 119: on the home page the featured stories and facts are small cards in the same layout as the Featured articles. */
class Step119Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    protected function tearDown(): void
    {
        Setting::forget();
        parent::tearDown();
    }

    private function story(string $title, bool $featured = true): Story
    {
        $cat = Category::firstOrCreate(['slug' => 'quranic-stories'], ['name' => ['en' => 'Quranic Stories']]);

        return Story::create(['title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'is_featured' => $featured, 'short_detail' => ['en' => 'A short line.'], 'main_image' => 'stories/'.str($title)->slug().'.webp']);
    }

    public function test_the_featured_stories_are_small_cards_in_the_mosaic_even_when_there_are_only_two(): void
    {
        $this->story('First story');
        $this->story('Second story');
        $this->story('Not featured story', false);

        $page = $this->get(route('home'))->assertOk();
        preg_match('#<section class="block">\s*<h2 class="block__title">Stories in pictures.*?</section>#s', $page->getContent(), $part);

        $this->assertStringContainsString('class="mosaic"', $part[0] ?? '');
        $this->assertSame(2, substr_count($part[0] ?? '', 'class="pcard pcard--c '), 'two small cards');
        $this->assertStringNotContainsString('pcard--P', $part[0] ?? '');
        $this->assertStringNotContainsString('class="scard"', $part[0] ?? '');
        $this->assertStringContainsString('First story', $part[0]);
        $this->assertStringContainsString('/stories/second-story', $part[0], 'a card opens its story');
        $this->assertStringContainsString('S-', $part[0], 'the reference code is on the card');
        $this->assertStringNotContainsString('Not featured story', $part[0]);
    }

    public function test_the_featured_facts_are_small_cards_too(): void
    {
        foreach (['One fact', 'Two fact'] as $i => $title) {
            DidYouKnow::create(['title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'body' => ['en' => '<p>The answer.</p>'], 'source' => 'A book', 'status' => 'published', 'is_featured' => true, 'featured_order' => $i + 1]);
        }

        $page = $this->get(route('home'))->assertOk();
        preg_match('#<section class="block" data-home-facts>.*?</section>#s', $page->getContent(), $part);

        $this->assertSame(2, substr_count($part[0] ?? '', 'class="pcard pcard--c '));
        $this->assertStringNotContainsString('pcard--P', $part[0] ?? '');
    }

    public function test_the_story_card_has_the_title_the_short_text_and_the_slide_count(): void
    {
        $story = $this->story('Counted story');
        $chapter = \App\Models\StoryChapter::create(['story_id' => $story->id, 'title' => ['en' => 'One'], 'sort_order' => 1]);
        foreach ([1, 2, 3] as $i) {
            \App\Models\StorySlide::create(['story_chapter_id' => $chapter->id, 'heading' => ['en' => "Slide $i"], 'sort_order' => $i]);
        }

        $this->get(route('home'))->assertOk()->assertSee('Counted story')->assertSee('3 slides');
    }
}
