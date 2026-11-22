<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Support\CategoryHero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 122: every category has the banner hero; a category without a picture uses the one above it; inner categories have Previous / Next arrows. */
class Step122Test extends TestCase
{
    use RefreshDatabase;

    private Category $master;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        $this->master = Category::create(['name' => ['en' => 'Master'], 'slug' => 'master', 'image' => 'categories/master.webp', 'sort_order' => 1]);
    }

    protected function tearDown(): void
    {
        Setting::forget();
        parent::tearDown();
    }

    private function child(string $slug, int $order, array $extra = []): Category
    {
        return Category::create(['name' => ['en' => ucfirst($slug)], 'slug' => $slug, 'parent_id' => $this->master->id, 'sort_order' => $order] + $extra);
    }

    public function test_an_inner_category_has_the_banner_with_the_picture_above_it(): void
    {
        $this->child('alpha', 1);

        $this->get('/category/alpha')->assertOk()->assertSee('category-banner', false)->assertSee('categories/master.webp', false);
        $this->assertSame('categories/master.webp', CategoryHero::picture(Category::where('slug', 'alpha')->first())['path']);
    }

    public function test_its_own_picture_wins_and_the_split_can_still_be_chosen(): void
    {
        $this->child('alpha', 1, ['image' => 'categories/alpha.webp']);
        $this->child('beta', 2, ['custom_data' => ['hero_style' => 'split']]);

        $this->get('/category/alpha')->assertOk()->assertSee('categories/alpha.webp', false);
        $this->get('/category/beta')->assertOk()->assertDontSee('category-banner__img', false)->assertSee('category-hero', false);
    }

    public function test_the_arrows_lead_to_the_neighbours_in_order(): void
    {
        $this->child('alpha', 1);
        $this->child('beta', 2);
        $this->child('gamma', 3);

        $this->get('/category/beta')->assertOk()->assertSee('/category/alpha', false)->assertSee('/category/gamma', false)->assertSee('Previous: Alpha')->assertSee('Next: Gamma');
        // The first has no Previous, the last has no Next.
        $this->get('/category/alpha')->assertOk()->assertDontSee('category-banner__arrow--prev', false)->assertSee('category-banner__arrow--next', false);
        $this->get('/category/gamma')->assertOk()->assertSee('category-banner__arrow--prev', false)->assertDontSee('category-banner__arrow--next', false);
    }

    public function test_hidden_siblings_are_skipped_and_only_one_child_means_no_arrows(): void
    {
        $this->child('alpha', 1);
        $this->child('beta', 2, ['is_visible' => false]);
        $this->child('gamma', 3);

        $this->get('/category/alpha')->assertOk()->assertSee('/category/gamma', false)->assertDontSee('/category/beta"', false);

        Category::where('slug', 'gamma')->delete();
        $this->get('/category/alpha')->assertOk()->assertDontSee('category-banner__nav', false);
    }

    public function test_a_master_category_has_no_arrows(): void
    {
        Category::create(['name' => ['en' => 'Second'], 'slug' => 'second', 'sort_order' => 2]);

        $this->get('/category/master')->assertOk()->assertSee('category-banner', false)->assertDontSee('category-banner__nav', false);
    }

    public function test_the_arrows_can_be_turned_off_for_the_site_or_for_one_category(): void
    {
        $this->child('alpha', 1);
        $this->child('beta', 2);

        Setting::put('ctl_category_nav', '0');
        Setting::forget();
        $this->get('/category/alpha')->assertOk()->assertDontSee('category-banner__nav', false);

        Setting::put('ctl_category_nav', '1');
        Setting::forget();
        $this->get('/category/alpha')->assertOk()->assertSee('category-banner__nav', false);

        Category::where('slug', 'alpha')->update(['custom_data' => ['hide_nav' => true]]);
        $this->get('/category/alpha')->assertOk()->assertDontSee('category-banner__nav', false);
    }
}
