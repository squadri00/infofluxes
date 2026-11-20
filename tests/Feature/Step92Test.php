<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 92: the category card shows the real total, and a category with sub-categories has an "All articles" tab. */
class Step92Test extends TestCase
{
    use RefreshDatabase;

    private function tree(): array
    {
        $top = Category::create(['name' => ['en' => 'Top'], 'slug' => 'top']);
        $a = Category::create(['name' => ['en' => 'Alpha'], 'slug' => 'alpha', 'parent_id' => $top->id]);
        $b = Category::create(['name' => ['en' => 'Beta'], 'slug' => 'beta', 'parent_id' => $top->id]);
        foreach ([[$a, 'Zebra story', 3], [$a, 'Apple story', 2], [$b, 'Mango story', 1]] as [$c, $t, $d]) {
            Article::create(['title' => ['en' => $t], 'slug' => \Illuminate\Support\Str::slug($t), 'category_id' => $c->id, 'status' => 'published', 'publish_date' => now()->subDays($d), 'short_detail' => ['en' => 'Short.'], 'long_detail' => ['en' => '<p>x</p>']]);
        }
        Article::create(['title' => ['en' => 'Hidden draft'], 'slug' => 'hidden-draft', 'category_id' => $a->id, 'status' => 'draft', 'short_detail' => ['en' => 'S'], 'long_detail' => ['en' => '<p>x</p>']]);

        return [$top, $a, $b];
    }

    public function test_the_count_includes_sub_categories_and_only_published(): void
    {
        [$top] = $this->tree();

        $this->assertSame(3, $top->listedCount());
        $this->get('/')->assertOk()->assertSee('3 articles', false)->assertSee('list=all', false);
    }

    public function test_the_category_page_has_the_two_tabs_and_the_all_list(): void
    {
        $this->tree();

        $this->get('/category/top')->assertOk()->assertSee('Browse')->assertSee('All articles');
        $r = $this->get('/category/top?list=all')->assertOk()->assertSee('Zebra story')->assertSee('Apple story')->assertSee('Mango story')->assertDontSee('Hidden draft');
        $r->assertSee('Alpha')->assertSee('Beta');
    }

    public function test_search_sort_and_sub_category_filter(): void
    {
        $this->tree();

        $this->get('/category/top?list=all&q=mango')->assertSee('Mango story')->assertDontSee('Zebra story');
        $this->get('/category/top?list=all&sub=alpha')->assertSee('Zebra story')->assertDontSee('Mango story');

        $az = $this->get('/category/top?list=all&sort=az')->getContent();
        $this->assertLessThan(strpos($az, 'Mango story'), strpos($az, 'Apple story'));
        $new = $this->get('/category/top?list=all')->getContent();
        $this->assertLessThan(strpos($new, 'Zebra story'), strpos($new, 'Mango story'), 'newest first');
    }

    public function test_a_last_level_category_has_no_tab(): void
    {
        $this->tree();

        $this->get('/category/alpha')->assertOk()->assertDontSee('All articles');
    }

    public function test_the_sub_category_buttons_at_the_top_can_be_switched_off(): void
    {
        $this->tree();

        $on = $this->get('/category/top')->assertOk()->getContent();
        $this->assertStringContainsString('category-menu__btn', $on, 'on by default');

        \App\Support\SiteControls::set('category_buttons', false);
        $off = $this->get('/category/top')->assertOk()->getContent();
        $this->assertStringNotContainsString('category-menu', $off);
        $this->assertStringContainsString('class="tile"', $off, 'the sub-categories are still shown as cards');
        $this->assertStringContainsString('Alpha', $off);
    }
}
