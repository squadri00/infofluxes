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

        // an article sits only in a last-level category, so "Shows" (it has a sub-category) is not offered
        $this->assertSame([$pop->id], array_keys(ArticleResource::searchCategories('shows')));
        $this->assertSame([$blogs->id], array_keys(ArticleResource::searchCategories($blogs->code)), 'by code');
        $this->assertSame([$blogs->id], array_keys(ArticleResource::searchCategories(strtolower($blogs->code))));
        $this->assertSame([$pop->id], array_keys(ArticleResource::searchCategories('popular  shows')), 'several words, in any spacing');
        $this->assertSame([], ArticleResource::searchCategories('nothing like this'));
        $this->assertCount(2, ArticleResource::searchCategories(''), 'an empty search lists the first last-level ones');
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

    public function test_long_names_in_the_admin_lists_scroll_inside_their_cell_so_every_column_stays_in_view(): void
    {
        $long = $this->cat('A very long category name that would push every other column of the list far to the right of the screen');
        $child = $this->cat('Child of the long one', $long);
        Article::create(['title' => ['en' => 'A very long article title that goes on and on and on'], 'slug' => 'long-title', 'category_id' => $child->id]);

        $page = $this->get('/admin/categories')->assertOk();
        $page->assertSee('.if-scroll{overflow-x:auto', false);
        $page->assertSee('if-order', false);
        $page->assertSee('.fi-ta-actions{flex-wrap:nowrap', false);
        $page->assertSee('class="if-scroll if-scroll--name"', false);
        $page->assertSee('class="if-scroll if-scroll--path"', false);
        $page->assertSee('title="A very long category name that would push every other column of the list far to the right of the screen"', false);

        $articles = $this->get('/admin/articles')->assertOk();
        $articles->assertSee('class="if-scroll if-scroll--title"', false)->assertSee('A very long article title that goes on and on and on');
        $articles->assertSee('class="if-scroll if-scroll--cats"', false);
        $articles->assertSee('Child of the long one (C-'.$child->id.')', false);
    }

    public function test_a_scrolling_cell_is_safe_to_show(): void
    {
        $this->assertNull(\App\Filament\Support\Cells::scroll(null));
        $this->assertNull(\App\Filament\Support\Cells::scroll(''));
        $html = \App\Filament\Support\Cells::scroll('<script>alert(1)</script> "quoted"', 'x');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('title="&lt;script&gt;', $html);
    }

    /** The sections of the article form, with whether each one starts closed. */
    private function sections($page): array
    {
        $out = [];
        foreach ($page->instance()->form->getFlatComponents() as $c) {
            if ($c instanceof \Filament\Schemas\Components\Section) {
                $out[(string) $c->getHeading()] = $c->isCollapsed();
            }
        }

        return $out;
    }

    public function test_every_section_of_an_article_starts_closed_when_it_is_opened_to_edit(): void
    {
        $main = $this->cat('Main place');
        $article = Article::create(['title' => ['en' => 'An article'], 'slug' => 'an-article', 'category_id' => $main->id]);

        $sections = $this->sections(Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()]));

        $this->assertSame(['The article', 'Long detail', 'Pictures', 'Audio for the Listen button', 'YouTube videos', 'Publishing', 'Search engines (optional)'], array_keys($sections));
        foreach ($sections as $heading => $closed) {
            $this->assertTrue($closed, "$heading starts closed");
        }
    }

    public function test_a_new_article_keeps_the_first_parts_open_so_the_title_can_be_typed(): void
    {
        $sections = $this->sections(Livewire::test(\App\Filament\Resources\Articles\Pages\CreateArticle::class));

        $this->assertFalse($sections['The article'], 'the title and the categories are here');
        $this->assertFalse($sections['Publishing']);
        $this->assertTrue($sections['Audio for the Listen button']);
        $this->assertTrue($sections['Search engines (optional)']);
    }

    public function test_the_chapters_and_slides_of_a_story_start_closed(): void
    {
        $main = $this->cat('Main place');
        $article = \App\Models\Story::create(['title' => ['en' => 'A story'], 'slug' => 'a-story', 'category_id' => $main->id]);
        $chapter = $article->chapters()->create(['title' => ['en' => 'Chapter one'], 'sort_order' => 1]);
        $chapter->slides()->create(['heading' => ['en' => 'Slide one'], 'sort_order' => 1]);

        $page = Livewire::test(\App\Filament\Resources\Stories\Pages\EditStory::class, ['record' => $article->getRouteKey()]);
        $repeaters = collect($page->instance()->form->getFlatComponents())->filter(fn ($c) => $c instanceof \Filament\Forms\Components\Repeater && in_array($c->getName(), ['chapters', 'slides'], true));

        $this->assertGreaterThanOrEqual(2, $repeaters->count());
        foreach ($repeaters as $repeater) {
            $this->assertTrue($repeater->isCollapsed(), $repeater->getName().' items start closed');
        }
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
