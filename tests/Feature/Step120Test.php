<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Setting;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 120: the featured parts of the home page are shown in a new random order on every visit (unless the switch is off). */
class Step120Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        $this->cat = Category::create(['name' => ['en' => 'Topic'], 'slug' => 'topic']);
    }

    protected function tearDown(): void
    {
        Setting::forget();
        parent::tearDown();
    }

    private function article(int $n): Article
    {
        return Article::create(['title' => ['en' => "Article number $n"], 'slug' => "article-number-$n", 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'is_featured' => true, 'featured_order' => $n, 'short_detail' => ['en' => 'Short.']]);
    }

    /** The titles of the part that starts at this heading, in the order the page shows them. @return list<string> */
    private function order(string $html, string $heading): array
    {
        preg_match('#<h2 class="block__title">'.preg_quote($heading, '#').'.*?</section>#s', $html, $part);
        preg_match_all('#<h3 class="pcard__title"><a [^>]*>([^<]+)</a>#', $part[0] ?? '', $m);

        return array_map('trim', $m[1]);
    }

    public function test_the_featured_articles_come_in_a_different_order_across_visits(): void
    {
        foreach (range(1, 6) as $n) {
            $this->article($n);
        }

        $orders = [];
        foreach (range(1, 25) as $i) {
            $orders[] = implode('|', $this->order($this->get(route('home'))->getContent(), 'Featured'));
        }

        $this->assertGreaterThan(1, count(array_unique($orders)), 'the order changes from visit to visit');
        foreach ($orders as $o) {
            $this->assertCount(6, explode('|', $o), 'every featured article is still there');
        }
    }

    public function test_when_more_are_featured_than_fit_a_different_choice_shows_each_time(): void
    {
        Setting::put('home_featured_count', '3');
        foreach (range(1, 8) as $n) {
            $this->article($n);
        }

        $seen = [];
        foreach (range(1, 25) as $i) {
            $shown = $this->order($this->get(route('home'))->getContent(), 'Featured');
            $this->assertCount(3, $shown, 'never more than the number set');
            $seen = array_merge($seen, $shown);
        }

        $this->assertGreaterThan(3, count(array_unique($seen)), 'over the visits, more than the first three show');
    }

    public function test_the_stories_and_the_facts_are_shuffled_too(): void
    {
        foreach (range(1, 5) as $n) {
            Story::create(['title' => ['en' => "Story number $n"], 'slug' => "story-number-$n", 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'is_featured' => true, 'featured_order' => $n]);
            DidYouKnow::create(['title' => ['en' => "Fact number $n"], 'slug' => "fact-number-$n", 'body' => ['en' => '<p>x</p>'], 'source' => 'Book', 'status' => 'published', 'is_featured' => true, 'featured_order' => $n]);
        }
        Setting::put('home_stories_count', '5');
        Setting::put('home_dyk_count', '5');

        $stories = [];
        $facts = [];
        foreach (range(1, 25) as $i) {
            $html = $this->get(route('home'))->getContent();
            $stories[] = implode('|', $this->order($html, 'Stories in pictures'));
            $facts[] = implode('|', $this->order($html, 'Did You Know?'));
        }

        $this->assertGreaterThan(1, count(array_unique($stories)));
        $this->assertGreaterThan(1, count(array_unique($facts)));
    }

    public function test_with_the_switch_off_the_order_numbers_are_kept_every_time(): void
    {
        Setting::put('ctl_home_shuffle', '0');
        foreach ([3, 1, 2] as $n) {
            $this->article($n);
        }

        foreach (range(1, 5) as $i) {
            $this->assertSame(['Article number 1', 'Article number 2', 'Article number 3'], $this->order($this->get(route('home'))->getContent(), 'Featured'));
        }
    }

    public function test_latest_articles_stay_newest_first_and_are_not_shuffled(): void
    {
        foreach (range(1, 4) as $n) {
            Article::create(['title' => ['en' => "Latest number $n"], 'slug' => "latest-number-$n", 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDays(10 - $n)]);
        }

        foreach (range(1, 5) as $i) {
            $this->assertSame(['Latest number 4', 'Latest number 3', 'Latest number 2', 'Latest number 1'], $this->order($this->get(route('home'))->getContent(), 'Latest articles'));
        }
    }
}
