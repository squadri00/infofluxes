<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Stories\Pages\ListStories;
use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 46: the "In category" filter of the Articles and Visual Stories lists includes everything below the chosen category. */
class Step46Test extends TestCase
{
    use RefreshDatabase;

    private Category $top;

    private Category $mid;

    private Category $leafA;

    private Category $leafB;

    private Category $other;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->top = Category::create(['name' => ['en' => 'About Islam'], 'slug' => 'about-islam']);
        $this->mid = Category::create(['name' => ['en' => 'Allah Index'], 'slug' => 'allah-index', 'parent_id' => $this->top->id]);
        $this->leafA = Category::create(['name' => ['en' => 'Creation'], 'slug' => 'creation', 'parent_id' => $this->mid->id]);
        $this->leafB = Category::create(['name' => ['en' => 'Worship'], 'slug' => 'worship', 'parent_id' => $this->mid->id]);
        $this->other = Category::create(['name' => ['en' => 'Elsewhere'], 'slug' => 'elsewhere']);
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
    }

    private function article(string $slug, Category $c): Article
    {
        return Article::create(['title' => ['en' => 'T '.$slug], 'slug' => $slug, 'category_id' => $c->id, 'status' => 'published']);
    }

    public function test_a_heading_shows_the_articles_of_all_its_sub_categories(): void
    {
        $a = $this->article('a', $this->leafA);
        $b = $this->article('b', $this->leafB);
        $x = $this->article('x', $this->other);

        Livewire::test(ListArticles::class)->filterTable('category', $this->top->id)->assertCanSeeTableRecords([$a, $b])->assertCanNotSeeTableRecords([$x]);
        Livewire::test(ListArticles::class)->filterTable('category', $this->mid->id)->assertCanSeeTableRecords([$a, $b])->assertCanNotSeeTableRecords([$x]);
    }

    public function test_a_last_level_category_shows_only_its_own(): void
    {
        $a = $this->article('a', $this->leafA);
        $b = $this->article('b', $this->leafB);

        Livewire::test(ListArticles::class)->filterTable('category', $this->leafA->id)->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    }

    public function test_an_article_that_is_also_in_a_category_below_shows_up_too(): void
    {
        $x = $this->article('x', $this->other);
        $x->extraCategories()->attach($this->leafB->id);

        Livewire::test(ListArticles::class)->filterTable('category', $this->top->id)->assertCanSeeTableRecords([$x]);
        Livewire::test(ListArticles::class)->filterTable('category', $this->leafA->id)->assertCanNotSeeTableRecords([$x]);
    }

    public function test_all_shows_everything(): void
    {
        $a = $this->article('a', $this->leafA);
        $x = $this->article('x', $this->other);

        Livewire::test(ListArticles::class)->assertCanSeeTableRecords([$a, $x]);
    }

    public function test_the_stories_list_works_the_same_way(): void
    {
        $s = Story::create(['title' => ['en' => 'S'], 'slug' => 's', 'category_id' => $this->leafA->id, 'status' => 'published']);
        $o = Story::create(['title' => ['en' => 'O'], 'slug' => 'o', 'category_id' => $this->other->id, 'status' => 'published']);

        Livewire::test(ListStories::class)->filterTable('category', $this->top->id)->assertCanSeeTableRecords([$s])->assertCanNotSeeTableRecords([$o]);
    }
}
