<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 124: "Browse all topics" (/categories): every main category with its sub-categories, a footer link, a home page button, a switch. */
class Step124Test extends TestCase
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

    private function tree(): array
    {
        $a = Category::create(['name' => ['en' => 'Alpha master'], 'slug' => 'alpha-master', 'sort_order' => 1, 'description' => ['en' => 'About alpha.']]);
        $b = Category::create(['name' => ['en' => 'Beta master'], 'slug' => 'beta-master', 'sort_order' => 2]);
        $a1 = Category::create(['name' => ['en' => 'Alpha child one'], 'slug' => 'alpha-child-one', 'parent_id' => $a->id, 'sort_order' => 1]);
        $a2 = Category::create(['name' => ['en' => 'Alpha child two'], 'slug' => 'alpha-child-two', 'parent_id' => $a->id, 'sort_order' => 2, 'is_visible' => false]);
        $deep = Category::create(['name' => ['en' => 'Alpha deep one'], 'slug' => 'alpha-deep-one', 'parent_id' => $a1->id]);

        return compact('a', 'b', 'a1', 'a2', 'deep');
    }

    public function test_the_page_lists_the_main_categories_and_their_sub_categories_in_order(): void
    {
        $this->tree();

        $html = $this->get('/categories')->assertOk()->assertSee('Browse all topics')->assertSee('Alpha master')->assertSee('Beta master')->assertSee('Alpha child one')->getContent();
        $this->assertLessThan(strpos($html, 'Beta master'), strpos($html, 'Alpha master'), 'the order of the menu');
        // Only two levels are listed; a hidden category stays hidden.
        $this->assertStringNotContainsString('Alpha deep one', $html);
        $this->assertStringNotContainsString('Alpha child two', $html);
    }

    public function test_the_counts_include_everything_below_and_only_published_articles(): void
    {
        $t = $this->tree();
        Article::create(['title' => ['en' => 'Published one'], 'slug' => 'published-one', 'category_id' => $t['deep']->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'S.']]);
        Article::create(['title' => ['en' => 'A draft'], 'slug' => 'a-draft', 'category_id' => $t['deep']->id, 'status' => 'draft']);

        $this->get('/categories')->assertOk()->assertSee('1 articles');
    }

    public function test_the_footer_and_the_home_page_link_to_it_and_the_sitemap_lists_it(): void
    {
        $this->tree();

        $this->get('/')->assertOk()->assertSee(route('categories'), false);
        $this->get('/categories')->assertOk()->assertSee('site-footer__all', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('categories'), false);
    }

    public function test_the_switch_turns_the_page_and_its_links_off(): void
    {
        $this->tree();
        Setting::put('ctl_categories_page', '0');
        Setting::forget();

        $this->get('/categories')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee(route('categories'), false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(route('categories'), false);
    }

    public function test_a_category_page_has_an_all_categories_button_before_browse(): void
    {
        $this->tree();

        $html = $this->get('/category/alpha-master')->assertOk()->assertSee('All categories')->getContent();
        $this->assertLessThan(strpos($html, '>Browse'), strpos($html, 'All categories'), 'the button comes before Browse');

        Setting::put('ctl_categories_page', '0');
        Setting::forget();
        $this->get('/category/alpha-master')->assertOk()->assertDontSee('all-tabs__tab--all', false);
    }

    public function test_the_address_cannot_be_used_by_a_category_or_a_page(): void
    {
        $this->assertContains('categories', Category::RESERVED_SLUGS);
        $this->assertContains('categories', \App\Models\Page::RESERVED);
    }
}
