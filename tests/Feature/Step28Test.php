<?php

namespace Tests\Feature;

use App\Filament\Resources\DidYouKnow\Pages\ManageDidYouKnow;
use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 28: the Did You Know? facts are normal items of their category: cards, their own page, the index view, search. */
class Step28Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
    }

    private function fact(string $title, array $extra = []): DidYouKnow
    {
        return DidYouKnow::create(array_merge([
            'title' => ['en' => $title], 'body' => ['en' => '<p>The answer to '.$title.'.</p>'], 'source' => 'https://example.org/'.str($title)->slug(),
            'status' => 'published', 'sort_order' => 1,
        ], $extra));
    }

    private function cat(): Category
    {
        return Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'did-you-know']);
    }

    // ---------------- an address of its own ----------------

    public function test_every_fact_gets_an_address_from_its_heading_and_keeps_it(): void
    {
        $a = $this->fact('Who was Qaroon?');
        $b = $this->fact('Who was Qaroon?');

        $this->assertSame('who-was-qaroon', $a->slug);
        $this->assertSame('who-was-qaroon-2', $b->slug, 'two with one heading never clash');

        $a->setTranslation('title', 'en', 'A new heading')->save();
        $this->assertSame('who-was-qaroon', $a->fresh()->slug, 'the address stays when the heading changes');
    }

    public function test_a_fact_has_a_page_with_its_answer_table_source_and_reviewer(): void
    {
        $cat = $this->cat();
        $this->fact('Women in the Quran', [
            'category_id' => $cat->id, 'reviewer_name' => 'Dr Khan', 'ai_assisted' => true,
            'body' => ['en' => '<p>Intro.</p><table><thead><tr><th>No.</th><th>Name</th></tr></thead><tbody><tr><td>1</td><td>Maryam</td></tr></tbody></table>'],
        ]);

        $html = $this->get(route('did-you-know.show', 'women-in-the-quran'))->assertOk()
            ->assertSee('Women in the Quran')->assertSee('Intro.')->assertSee('Reviewed by Dr Khan')->assertSee('Written with AI help')->getContent();

        $this->assertStringContainsString('<td>Maryam</td>', $html);
        $this->assertStringContainsString('href="https://example.org/women-in-the-quran"', $html);
        $this->assertStringContainsString('href="'.route('category.show', 'did-you-know').'"', $html, 'the path back to its category');
    }

    public function test_the_page_of_a_draft_or_of_a_fact_without_a_source_is_not_found_for_visitors(): void
    {
        $this->fact('Draft one', ['status' => 'draft']);
        $this->fact('No source', ['source' => null]);

        $this->get(route('did-you-know.show', 'draft-one'))->assertNotFound();
        $this->get(route('did-you-know.show', 'no-source'))->assertNotFound();
        $this->get(route('did-you-know.show', 'nothing-here'))->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->get(route('did-you-know.show', 'draft-one'))->assertOk()->assertSee('This is a draft');
    }

    public function test_the_page_offers_the_full_article_and_more_facts_of_the_same_category(): void
    {
        $cat = $this->cat();
        $other = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);
        $article = Article::create(['title' => ['en' => 'The whole story'], 'slug' => 'the-whole-story', 'status' => 'published', 'publish_date' => now()->subDay()]);
        $this->fact('Main fact', ['category_id' => $cat->id, 'article_id' => $article->id]);
        $this->fact('Sister fact', ['category_id' => $cat->id, 'sort_order' => 2]);
        $this->fact('Foreign fact', ['category_id' => $other->id, 'sort_order' => 3]);

        $this->get(route('did-you-know.show', 'main-fact'))->assertOk()
            ->assertSee('Read the full article')->assertSee('Sister fact')->assertDontSee('Foreign fact');
    }

    // ---------------- the category page: the same cards and views as every category ----------------

    public function test_the_facts_are_normal_cards_in_their_category_with_the_grid_list_index_buttons(): void
    {
        $cat = $this->cat();
        $this->fact('Fact one', ['category_id' => $cat->id]);
        $this->fact('Fact two', ['category_id' => $cat->id, 'sort_order' => 2, 'image' => 'facts/one.webp']);

        $html = $this->get(route('category.show', 'did-you-know'))->assertOk()->getContent();

        $this->assertStringContainsString('class="mosaic"', $html);
        $this->assertSame(2, substr_count($html, 'class="pcard '));
        $this->assertStringContainsString('href="'.route('did-you-know.show', 'fact-one').'"', $html, 'each card opens the fact on its own page');
        $this->assertStringContainsString(asset('storage/facts/one.webp'), $html, 'a picture when it has one');
        $this->assertStringContainsString('class="view-bar"', $html);
        $this->assertStringNotContainsString('class="dyk"', $html, 'not the open-and-close cards');
    }

    public function test_the_index_view_lists_the_facts_as_lines_and_counts_them(): void
    {
        $cat = $this->cat();
        $this->fact('Fact one', ['category_id' => $cat->id]);
        $this->fact('Fact two', ['category_id' => $cat->id, 'sort_order' => 2]);

        $html = $this->get(route('category.show', ['slug' => 'did-you-know', 'view' => 'index']))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('did-you-know.show', 'fact-one').'"', $html);
        $this->assertSame(2, substr_count($html, 'data-qi-item'));
        $this->assertStringContainsString('2 subjects', $html);
        $this->assertStringContainsString('D-'.DidYouKnow::first()->id, $html);
    }

    public function test_facts_and_articles_share_one_category_page(): void
    {
        $cat = $this->cat();
        $this->fact('A fact', ['category_id' => $cat->id]);
        Article::create(['title' => ['en' => 'An article'], 'slug' => 'an-article', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);

        $this->get(route('category.show', 'did-you-know'))->assertOk()->assertSee('A fact')->assertSee('An article')->assertSee('Articles');
    }

    public function test_a_hidden_draft_or_unsourced_fact_never_shows_on_the_category_page(): void
    {
        $cat = $this->cat();
        $this->fact('Good one', ['category_id' => $cat->id]);
        $this->fact('A draft', ['category_id' => $cat->id, 'status' => 'draft']);
        $this->fact('No source', ['category_id' => $cat->id, 'source' => null]);

        $this->get(route('category.show', 'did-you-know'))->assertSee('Good one')->assertDontSee('A draft')->assertDontSee('No source');
    }

    // ---------------- search and the sitemap ----------------

    public function test_search_finds_facts_in_any_capital_letters_and_limits_them_to_a_category(): void
    {
        $cat = $this->cat();
        $other = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);
        $this->fact('The lote tree', ['category_id' => $cat->id]);
        $this->fact('A lote in history', ['category_id' => $other->id, 'sort_order' => 2]);

        $this->get(route('search', ['q' => 'LOTE']))->assertOk()->assertSee('The lote tree')->assertSee('A lote in history');
        $this->get(route('search', ['q' => 'lote', 'category' => $cat->id]))->assertSee('The lote tree')->assertDontSee('A lote in history');
        $this->get(route('search', ['q' => 'nothing at all']))->assertDontSee('The lote tree');
    }

    public function test_search_looks_in_the_answer_too_and_ignores_unpublished_facts(): void
    {
        $this->fact('Plain heading', ['body' => ['en' => '<p>A word hidden in the answer: sidrat.</p>']]);
        $this->fact('Draft heading', ['status' => 'draft', 'body' => ['en' => '<p>sidrat too</p>']]);

        $this->get(route('search', ['q' => 'SIDRAT']))->assertSee('Plain heading')->assertDontSee('Draft heading');
    }

    public function test_every_visible_fact_page_is_in_the_sitemap(): void
    {
        $this->fact('Visible one');
        $this->fact('Draft one', ['status' => 'draft']);

        $this->get('/sitemap.xml')->assertOk()->assertSee('/did-you-know/visible-one', false)->assertDontSee('/did-you-know/draft-one', false);
    }

    // ---------------- the admin ----------------

    public function test_cloning_a_fact_gives_the_copy_its_own_address(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $fact = $this->fact('Original');

        Livewire::test(ManageDidYouKnow::class)->callTableAction('replicate', $fact);

        $this->assertSame(2, DidYouKnow::count());
        $this->assertSame(['original', 'original-2'], DidYouKnow::orderBy('id')->pluck('slug')->all());
    }

    public function test_the_admin_form_has_the_picture_fields(): void
    {
        $source = (string) file_get_contents(app_path('Filament/Resources/DidYouKnow/DidYouKnowResource.php'));

        $this->assertStringContainsString("FileUpload::make('image')", $source);
        $this->assertStringContainsString("TextInput::make('image_alt')", $source);
    }

    public function test_the_four_column_list_page_still_works(): void
    {
        $this->fact('Fact one');

        $this->get(route('did-you-know'))->assertOk()->assertSee('Fact one')->assertSee('class="dyk"', false);
    }
}
