<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Stories\Pages\ListStories;
use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use App\Support\MasterFeatured;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 108: a second star: articles and stories featured at the top of their master (top-level) category. */
class Step108Test extends TestCase
{
    use RefreshDatabase;

    private Category $master;
    private Category $leaf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->master = Category::create(['name' => ['en' => 'About Islam'], 'slug' => 'about-islam']);
        $mid = Category::create(['name' => ['en' => 'Allah'], 'slug' => 'allah', 'parent_id' => $this->master->id]);
        $this->leaf = Category::create(['name' => ['en' => 'Names'], 'slug' => 'names', 'parent_id' => $mid->id]);
    }

    private function article(string $title, array $extra = []): Article
    {
        return Article::create(array_merge(['title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'category_id' => $this->leaf->id, 'status' => 'published',
            'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'Short.'], 'long_detail' => ['en' => '<p>Text.</p>']], $extra));
    }

    public function test_the_master_category_page_shows_its_featured_items_from_any_level_below(): void
    {
        $this->article('Featured one', ['is_featured_master' => true, 'featured_master_order' => 2]);
        $this->article('Plain one');
        $story = Story::create(['title' => ['en' => 'Featured story'], 'slug' => 'featured-story', 'category_id' => $this->leaf->id, 'status' => 'published',
            'publish_date' => now()->subDay(), 'is_featured_master' => true, 'featured_master_order' => 1]);

        $this->get(route('category.show', 'about-islam'))->assertOk()->assertSee('category-featured', false)->assertSee('Featured one')->assertSee('Featured story')->assertDontSee('Plain one');
        $this->assertSame(['Featured story', 'Featured one'], MasterFeatured::for($this->master)->map(fn ($i) => $i->mainTitle())->all());
    }

    public function test_at_most_three_show_and_unpublished_ones_never_show(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $i => $t) {
            $this->article('Item '.$t, ['is_featured_master' => true, 'featured_master_order' => $i + 1]);
        }
        $this->article('Hidden draft', ['is_featured_master' => true, 'featured_master_order' => 0, 'status' => 'draft']);

        $titles = MasterFeatured::for($this->master)->map(fn ($i) => $i->mainTitle())->all();

        $this->assertSame(['Item A', 'Item B', 'Item C'], $titles);
    }

    public function test_only_top_level_categories_have_the_featured_row(): void
    {
        $this->article('Featured one', ['is_featured_master' => true]);

        $this->get(route('category.show', 'allah'))->assertOk()->assertDontSee('category-featured', false);
        $this->assertTrue(MasterFeatured::for($this->leaf)->isEmpty());
    }

    public function test_the_lists_have_the_master_star_next_to_the_home_star(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $article = $this->article('Starred');
        $story = Story::create(['title' => ['en' => 'Starred story'], 'slug' => 'starred-story', 'category_id' => $this->leaf->id, 'status' => 'published']);

        Livewire::test(ListArticles::class)->assertTableColumnExists('is_featured_master')->assertTableColumnExists('is_featured');
        Livewire::test(ListStories::class)->assertTableColumnExists('is_featured_master')->assertTableColumnExists('is_featured');
        $this->assertFalse((bool) $article->fresh()->is_featured_master);
        $this->assertFalse((bool) $story->fresh()->is_featured_master);
    }
}
