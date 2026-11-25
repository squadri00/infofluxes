<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use App\Models\Person;
use App\Models\Setting;
use App\Support\SiteArticles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 129: the "Read all articles" button in the purple box of the home page, and the page with its filters. */
class Step129Test extends TestCase
{
    use RefreshDatabase;

    private Category $master;

    private Category $childA;

    private Category $childB;

    private Category $empty;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        $this->master = Category::create(['name' => ['en' => 'Master'], 'slug' => 'master', 'sort_order' => 1]);
        $this->childA = Category::create(['name' => ['en' => 'Child Alpha'], 'slug' => 'child-alpha', 'parent_id' => $this->master->id, 'sort_order' => 1]);
        $this->childB = Category::create(['name' => ['en' => 'Child Beta'], 'slug' => 'child-beta', 'parent_id' => $this->master->id, 'sort_order' => 2]);
        $this->empty = Category::create(['name' => ['en' => 'Child Empty'], 'slug' => 'child-empty', 'parent_id' => $this->master->id, 'sort_order' => 3]);
    }

    protected function tearDown(): void
    {
        Setting::forget();
        parent::tearDown();
    }

    private function article(string $title, Category $cat, array $over = []): Article
    {
        return Article::create($over + ['title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'short_detail' => ['en' => 'A short line about '.$title.'.']]);
    }

    public function test_the_page_lists_the_published_articles_and_nothing_else(): void
    {
        $this->article('Zeta published', $this->childA);
        $this->article('A draft one', $this->childA, ['status' => 'draft']);

        $this->get('/articles')->assertOk()->assertSee('Read all articles')->assertSee('Zeta published')->assertDontSee('A draft one')->assertSee('1 articles');
    }

    public function test_the_search_the_category_the_contributor_and_the_sort_work_together(): void
    {
        $ann = Person::create(['name' => 'Ann Writer', 'slug' => 'ann-writer']);
        $bob = Person::create(['name' => 'Bob Writer', 'slug' => 'bob-writer']);
        $this->article('Mango orchard', $this->childA, ['contributor_id' => $ann->id, 'publish_date' => now()->subDays(3)]);
        $this->article('Banana river', $this->childA, ['contributor_id' => $bob->id, 'publish_date' => now()->subDays(2)]);
        $this->article('Cherry hill', $this->childB, ['contributor_id' => $ann->id, 'publish_date' => now()->subDay()]);

        // The search looks in the title.
        $this->get('/articles?q=mango')->assertOk()->assertSee('Mango orchard')->assertDontSee('Banana river')->assertDontSee('Cherry hill');
        // A category.
        $this->get('/articles?category='.$this->childB->id)->assertOk()->assertSee('Cherry hill')->assertDontSee('Mango orchard');
        // A master category covers everything below it.
        $this->get('/articles?category='.$this->master->id)->assertOk()->assertSee('Mango orchard')->assertSee('Cherry hill');
        // A contributor, and a contributor together with a category.
        $this->get('/articles?contributor='.$ann->id)->assertOk()->assertSee('Mango orchard')->assertSee('Cherry hill')->assertDontSee('Banana river');
        $this->get('/articles?contributor='.$ann->id.'&category='.$this->childA->id)->assertOk()->assertSee('Mango orchard')->assertDontSee('Cherry hill');

        // Sort: newest first (the usual), oldest first, A to Z.
        $new = $this->get('/articles')->getContent();
        $this->assertLessThan(strpos($new, 'Mango orchard'), strpos($new, 'Cherry hill'));
        $old = $this->get('/articles?sort=old')->getContent();
        $this->assertLessThan(strpos($old, 'Cherry hill'), strpos($old, 'Mango orchard'));
        $az = $this->get('/articles?sort=az')->getContent();
        $this->assertLessThan(strpos($az, 'Cherry hill'), strpos($az, 'Banana river'));
        $this->assertLessThan(strpos($az, 'Mango orchard'), strpos($az, 'Cherry hill'));

        // Nothing found says so, and the clear link is there while a filter is on.
        $this->get('/articles?q=zzzz')->assertOk()->assertSee('No article matches')->assertSee('Clear');
        $this->get('/articles')->assertDontSee('>Clear<', false);
    }

    public function test_only_categories_and_contributors_with_a_published_article_are_offered(): void
    {
        $ann = Person::create(['name' => 'Ann Writer', 'slug' => 'ann-writer']);
        Person::create(['name' => 'No Articles Person', 'slug' => 'none']);
        $this->article('Mango orchard', $this->childA, ['contributor_id' => $ann->id]);
        $this->article('Hidden draft', $this->empty, ['status' => 'draft']);

        $html = $this->get('/articles')->assertOk()->getContent();
        // (The main menu names every category, so only the filter bar is looked at.)
        $html = substr($html, strpos($html, '<form class="all-list__bar'), strpos($html, '</form>', strpos($html, '<form class="all-list__bar')) - strpos($html, '<form class="all-list__bar'));
        $this->assertStringContainsString('Child Alpha', $html);
        $this->assertStringNotContainsString('Child Empty', $html);
        $this->assertStringNotContainsString('Child Beta', $html);
        $this->assertStringContainsString('Ann Writer', $html);
        $this->assertStringNotContainsString('No Articles Person', $html);
        // (A sub-category is indented with a wide space.)
        $this->assertSame(['Master (1)', 'Child Alpha (1)'], array_map(fn ($c) => trim($c['label'], " \t\n\r\0\x0B\u{2003}"), SiteArticles::categories()));

        // A wrong or made-up value is ignored, not an error.
        $this->get('/articles?category=99999&contributor=99999&sort=nonsense')->assertOk()->assertSee('Mango orchard');
    }

    public function test_the_page_has_24_to_a_page(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->article('Article number '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), $this->childA, ['publish_date' => now()->subHours(40 - $i)]);
        }

        $first = $this->get('/articles')->assertOk()->getContent();
        preg_match_all('/Article number \d\d/', $first, $m);
        $this->assertCount(24, array_unique($m[0]));
        $this->get('/articles?page=2')->assertOk()->assertSee('Article number 01')->assertDontSee('Article number 30');
    }

    public function test_the_purple_box_the_footer_and_the_sitemap_lead_to_it(): void
    {
        $this->article('Mango orchard', $this->childA);
        \App\Models\HeroMessage::create(['title' => ['en' => 'Understand first'], 'is_active' => true, 'sort_order' => 1]);

        $this->get('/')->assertOk()->assertSee('hero__readall', false)->assertSee(route('articles'), false)->assertSee('Read all articles');
        $this->get('/articles')->assertOk()->assertSee('site-footer__all', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('articles'), false);
    }

    public function test_the_switch_turns_the_page_and_its_links_off(): void
    {
        $this->article('Mango orchard', $this->childA);
        \App\Models\HeroMessage::create(['title' => ['en' => 'Understand first'], 'is_active' => true, 'sort_order' => 1]);
        Setting::put('ctl_articles_page', '0');
        Setting::forget();

        $this->get('/articles')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('hero__readall', false)->assertDontSee(route('articles'), false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(route('articles'), false);
    }

    public function test_the_address_cannot_be_used_by_a_category_or_a_page(): void
    {
        $this->assertContains('articles', Category::RESERVED_SLUGS);
        $this->assertContains('articles', Page::RESERVED);
    }
}
