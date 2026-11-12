<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 9: the "Also in these categories" box finds categories by name or by code without asking the database. */
class Step09Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
    }

    private function cat(string $name, ?Category $parent = null): Category
    {
        return Category::create(['name' => ['en' => $name], 'slug' => Category::uniqueSlug($name), 'parent_id' => $parent?->id]);
    }

    public function test_the_search_finds_by_name_by_code_and_by_several_words(): void
    {
        $shows = $this->cat('Shows');
        $pop = $this->cat('Popular Shows', $shows);
        $blogs = $this->cat('Blogs');

        $this->assertSame([$shows->id, $pop->id], array_keys(ArticleResource::searchCategories('shows')));
        $this->assertSame([$blogs->id], array_keys(ArticleResource::searchCategories($blogs->code)), 'by code');
        $this->assertSame([$blogs->id], array_keys(ArticleResource::searchCategories(strtolower($blogs->code))));
        $this->assertSame([$pop->id], array_keys(ArticleResource::searchCategories('popular  shows')), 'several words, in any spacing');
        $this->assertSame([], ArticleResource::searchCategories('nothing like this'));
        $this->assertCount(3, ArticleResource::searchCategories(''), 'an empty search lists the first ones');
    }

    public function test_a_code_with_a_dash_does_not_break_the_search_and_the_list_is_cut_at_fifty(): void
    {
        foreach (range(1, 60) as $i) {
            $this->cat("Category number $i");
        }

        $this->assertCount(50, ArticleResource::searchCategories('category'));
        $this->assertNotEmpty(ArticleResource::searchCategories('C-2'));
        $this->assertSame([], ArticleResource::searchCategories('%_\\\''), 'strange letters are only letters');
    }

    public function test_the_real_box_on_the_edit_page_searches_and_shows_the_chosen_ones(): void
    {
        $main = $this->cat('Main place');
        $other = $this->cat('Other place');
        $article = Article::create(['title' => ['en' => 'An article'], 'slug' => 'an-article', 'category_id' => $main->id]);
        $article->extraCategories()->attach($other->id);

        $page = Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()]);
        $select = collect($page->instance()->form->getFlatComponents())->first(fn ($c) => method_exists($c, 'getName') && $c->getName() === 'extraCategories');
        $this->assertNotNull($select, 'the box exists on the edit page');

        // The search the box runs when someone types "C-2" (it used to stop with a database error).
        $this->assertIsArray($select->getSearchResults('C-2'));
        $this->assertSame([$other->id => 'Other place ('.$other->code.')'], $select->getSearchResults('other'));
        $this->assertSame([$other->id => 'Other place ('.$other->code.')'], $select->getOptionLabels());
    }

    public function test_choosing_other_categories_is_saved(): void
    {
        $main = $this->cat('Main place');
        $a = $this->cat('Place A');
        $b = $this->cat('Place B');
        $article = Article::create(['title' => ['en' => 'An article'], 'slug' => 'an-article', 'category_id' => $main->id, 'status' => 'draft']);

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->fillForm(['extraCategories' => [$a->id, $b->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $article->fresh()->extraCategories->pluck('id')->all());

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->fillForm(['extraCategories' => [$b->id]])->call('save');
        $this->assertSame([$b->id], $article->fresh()->extraCategories->pluck('id')->all(), 'a tag can be removed');
    }
}
