<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\AiImport as Importer;
use App\Support\OverviewMaker;
use App\Support\Prompts;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 45: an article sits only in a last-level category (a category that has no sub-categories). */
class Step45Test extends TestCase
{
    use RefreshDatabase;

    private Category $heading;

    private Category $leaf;

    private Category $leaf2;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->heading = Category::create(['name' => ['en' => 'Allah Index'], 'slug' => 'allah-index']);
        $this->leaf = Category::create(['name' => ['en' => 'Creation'], 'slug' => 'creation', 'parent_id' => $this->heading->id]);
        $this->leaf2 = Category::create(['name' => ['en' => 'Worship'], 'slug' => 'worship', 'parent_id' => $this->heading->id]);
    }

    private function article(int $categoryId, string $slug = 'a'): Article
    {
        return Article::create(['title' => ['en' => 'T '.$slug], 'slug' => $slug, 'category_id' => $categoryId, 'status' => 'published', 'publish_date' => now()->subDay()]);
    }

    public function test_the_helpers_know_which_categories_are_last_level(): void
    {
        $this->assertFalse($this->heading->isLeaf());
        $this->assertTrue($this->leaf->isLeaf());
        $this->assertEqualsCanonicalizing([$this->leaf->id, $this->leaf2->id], Category::leafIds());
        $this->assertSame([$this->leaf->id, $this->leaf2->id], array_keys(Category::leafChoices()));
        $this->assertSame([$this->leaf->id, $this->leaf2->id], array_keys(Category::publicLeafChoices()));
        $this->assertStringContainsString('Creation', implode(' ', $this->heading->leafExamples()));
    }

    public function test_holds_articles_counts_main_and_extra_but_not_trashed(): void
    {
        $this->assertFalse($this->leaf->holdsArticles());
        $a = $this->article($this->leaf->id);
        $this->assertTrue($this->leaf->holdsArticles());
        $this->article($this->leaf2->id, 'b')->extraCategories()->attach($this->leaf->id);
        $a->delete();
        $this->assertTrue($this->leaf->holdsArticles(), 'still through the extra link');
        $this->assertFalse($this->heading->holdsArticles());
    }

    public function test_the_admin_form_offers_only_last_level_categories(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => ['en' => 'On a heading'], 'category_id' => $this->heading->id])->call('create')
            ->assertHasFormErrors(['category_id']);
        $this->assertSame(0, Article::count());

        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => ['en' => 'On a leaf'], 'category_id' => $this->leaf->id])->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame($this->leaf->id, Article::first()->category_id);
    }

    public function test_the_ai_answer_must_name_a_last_level_category_and_the_message_helps(): void
    {
        $text = fn (string $cat) => "TYPE: article\nCATEGORY: {$cat}\nTITLE: T\nSHORT: S\nLONG:\n## One\nWords here.\nEND";

        $bad = Importer::parse($text($this->heading->code))[0];
        $this->assertNotEmpty(array_filter($bad['errors'], fn ($e) => str_contains($e, 'has sub-categories')));
        $this->assertStringContainsString('Creation', implode(' ', $bad['errors']), 'it names some last-level categories');

        $good = Importer::parse($text($this->leaf->code))[0];
        $this->assertSame([], $good['errors']);
    }

    public function test_also_in_leaves_out_a_heading_with_a_note(): void
    {
        $item = Importer::parse("TYPE: article\nCATEGORY: {$this->leaf->code}\nALSO IN: {$this->heading->code}; {$this->leaf2->code}\nTITLE: T\nSHORT: S\nLONG:\n## One\nWords here.\nEND")[0];

        $this->assertSame([], $item['errors']);
        $this->assertSame([$this->leaf2->id], $item['data']['extra_ids']);
        $this->assertStringContainsString('has sub-categories', implode(' ', $item['warnings']));
    }

    public function test_the_prompt_lists_only_last_level_categories(): void
    {
        $list = Prompts::categoryList();

        $this->assertStringContainsString('Creation', $list);
        $this->assertStringContainsString('Worship', $list);
        $this->assertStringNotContainsString($this->heading->code.')', $list);
    }

    public function test_a_category_that_holds_articles_cannot_get_sub_categories(): void
    {
        $this->article($this->leaf->id);
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => ['en' => 'Below'], 'parent_id' => $this->leaf->id])->call('create')
            ->assertHasFormErrors(['parent_id']);
        $this->assertSame(0, Category::where('parent_id', $this->leaf->id)->count());

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => ['en' => 'Below an empty one'], 'parent_id' => $this->leaf2->id])->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(1, Category::where('parent_id', $this->leaf2->id)->count());
    }

    public function test_the_ai_categories_list_cannot_put_a_category_below_one_that_holds_articles(): void
    {
        $this->article($this->leaf->id);

        $item = Importer::parse("TYPE: categories\nAllah Index > Creation > Brand New\nAllah Index > Worship > Another New\nEND")[0];

        $this->assertNotEmpty(array_filter($item['errors'], fn ($e) => str_contains($e, '"Creation" already holds articles')));
        $this->assertCount(1, $item['errors'], 'the free one is fine');
    }

    public function test_the_overview_maker_does_nothing_without_apply(): void
    {
        $a = $this->article($this->heading->id);

        $r = OverviewMaker::run(false);

        $this->assertSame([$this->heading->id], array_column($r['headings'], 'id'));
        $this->assertSame($this->heading->id, $a->fresh()->category_id);
        $this->assertSame(0, Category::where('name->en', 'Overview')->count());
    }

    public function test_the_overview_maker_moves_main_and_extra_articles_into_a_new_overview_leaf(): void
    {
        $main = $this->article($this->heading->id, 'main');
        $other = $this->article($this->leaf->id, 'other');
        $other->extraCategories()->attach($this->heading->id);
        $skipped = Category::create(['name' => ['en' => 'Bible Index'], 'slug' => 'bible-index']);
        Category::create(['name' => ['en' => 'Genesis'], 'slug' => 'genesis', 'parent_id' => $skipped->id]);
        $demo = $this->article($skipped->id, 'demo');

        $r = OverviewMaker::run(true, [$skipped->id]);

        $overview = Category::where('parent_id', $this->heading->id)->where('name->en', 'Overview')->first();
        $this->assertNotNull($overview);
        $this->assertTrue($overview->isLeaf());
        $this->assertSame($overview->id, $main->fresh()->category_id);
        $this->assertSame([$overview->id], $other->fresh()->extraCategories()->pluck('categories.id')->all());
        $this->assertSame($skipped->id, $demo->fresh()->category_id, 'a skipped heading is left alone');
        $this->assertSame([$this->heading->id], array_column($r['headings'], 'id'));
        $this->assertSame([$demo->id], OverviewMaker::offenders()->pluck('id')->all());

        // running it again changes nothing more
        $again = OverviewMaker::run(true, [$skipped->id]);
        $this->assertSame([], $again['headings']);
        $this->assertSame(1, Category::where('name->en', 'Overview')->count());
    }

    public function test_the_command_prints_a_table_and_applies(): void
    {
        $a = $this->article($this->heading->id);

        $this->artisan('categories:make-overviews')->expectsOutputToContain('Nothing was changed')->assertSuccessful();
        $this->assertSame($this->heading->id, $a->fresh()->category_id);

        $this->artisan('categories:make-overviews', ['--apply' => true])->expectsOutputToContain('Done')->assertSuccessful();
        $this->assertNotSame($this->heading->id, $a->fresh()->category_id);
    }
}
