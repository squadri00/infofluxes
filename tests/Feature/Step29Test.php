<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 29: a fact page is the same page as an article: the branch on the left, "Recently viewed" on the right. */
class Step29Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function fact(string $title, ?Category $category, array $extra = []): DidYouKnow
    {
        return DidYouKnow::create(array_merge([
            'title' => ['en' => $title], 'body' => ['en' => '<p>The answer to '.$title.'.</p>'], 'source' => 'https://example.org/'.str($title)->slug(),
            'status' => 'published', 'category_id' => $category?->id,
        ], $extra));
    }

    public function test_a_fact_page_has_the_three_column_reading_layout(): void
    {
        $cat = Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'did-you-know']);
        $this->fact('Fact one', $cat);

        $html = $this->get(route('did-you-know.show', 'fact-one'))->assertOk()->getContent();

        $this->assertStringContainsString('wrap wrap--wide reading', $html);
        $this->assertStringContainsString('reading__side reading__side--left', $html);
        $this->assertStringContainsString('reading__side reading__side--right', $html);
        $this->assertStringContainsString('class="topic reading__main fact"', $html);
    }

    public function test_the_left_side_lists_every_fact_of_the_branch_and_marks_the_one_being_read(): void
    {
        $cat = Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'did-you-know']);
        $this->fact('Fact one', $cat, ['sort_order' => 1]);
        $this->fact('Fact two', $cat, ['sort_order' => 2]);
        $this->fact('A draft', $cat, ['status' => 'draft', 'sort_order' => 3]);
        $this->fact('Elsewhere', Category::create(['name' => ['en' => 'History'], 'slug' => 'history']), ['sort_order' => 4]);

        $html = $this->get(route('did-you-know.show', 'fact-two'))->getContent();
        $side = substr($html, strpos($html, 'reading__side--left'), strpos($html, '<article') - strpos($html, 'reading__side--left'));

        $this->assertStringContainsString('Fact one', $side);
        $this->assertStringContainsString('Fact two', $side);
        $this->assertStringNotContainsString('A draft', $side);
        $this->assertStringNotContainsString('Elsewhere', $side);
        $this->assertStringContainsString('aria-current="page">Fact two', $side, 'the fact being read is marked');
        $this->assertLessThan(strpos($side, 'Fact two'), strpos($side, 'Fact one'), 'in the chosen order');
        $this->assertStringContainsString('href="'.route('category.show', ['slug' => 'did-you-know', 'view' => 'index']).'"', $side, 'the link to the full index');
    }

    public function test_the_right_side_remembers_this_fact_in_the_recently_viewed_list(): void
    {
        $cat = Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'did-you-know']);
        $this->fact('Fact one', $cat);

        $html = $this->get(route('did-you-know.show', 'fact-one'))->getContent();

        $this->assertStringContainsString('data-recent', $html);
        $this->assertStringContainsString('data-current-url="'.route('did-you-know.show', 'fact-one').'"', $html);
        $this->assertStringContainsString('data-current-title="Fact one"', $html);
        $this->assertStringContainsString('js/recent.js', $html);
    }

    public function test_a_row_with_the_fact_being_read_is_open_in_a_branch_with_sub_categories(): void
    {
        $part = Category::create(['name' => ['en' => 'Faith'], 'slug' => 'faith']);
        $one = Category::create(['name' => ['en' => 'Prayer'], 'slug' => 'prayer', 'parent_id' => $part->id]);
        $two = Category::create(['name' => ['en' => 'Fasting'], 'slug' => 'fasting', 'parent_id' => $part->id]);
        $this->fact('Why we pray', $one);
        $this->fact('Why we fast', $two);

        $html = $this->get(route('did-you-know.show', 'why-we-pray'))->getContent();

        $this->assertMatchesRegularExpression('#<details class="side-tree__cat"\s+open\s*>\s*<summary>Prayer</summary>#', $html);
        $this->assertDoesNotMatchRegularExpression('#<details class="side-tree__cat"\s+open\s*>\s*<summary>Fasting</summary>#', $html);
        $this->assertStringContainsString('Why we fast', $html, 'the other row still lists its fact');
    }

    public function test_an_article_page_shows_the_facts_of_its_branch_in_its_tree_too(): void
    {
        $cat = Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'did-you-know']);
        $this->fact('A fact in the branch', $cat);
        Article::create(['title' => ['en' => 'An article'], 'slug' => 'an-article', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);

        $html = $this->get('/an-article')->assertOk()->getContent();

        $this->assertStringContainsString('A fact in the branch', $html);
        $this->assertStringContainsString('aria-current="page">An article', $html);
    }

    public function test_a_fact_without_a_category_still_opens_as_a_plain_page(): void
    {
        $this->fact('Loose fact', null);

        $html = $this->get(route('did-you-know.show', 'loose-fact'))->assertOk()->assertSee('Loose fact')->getContent();

        $this->assertStringNotContainsString('reading__side', $html);
        $this->assertStringContainsString('href="'.route('did-you-know').'"', $html, 'the path leads to the Did You Know? list');
    }

    public function test_the_helper_that_finds_the_facts_of_a_branch_groups_them_by_category(): void
    {
        $part = Category::create(['name' => ['en' => 'Faith'], 'slug' => 'faith']);
        $kid = Category::create(['name' => ['en' => 'Prayer'], 'slug' => 'prayer', 'parent_id' => $part->id]);
        $this->fact('Top', $part);
        $this->fact('Below', $kid);
        $this->fact('Outside', Category::create(['name' => ['en' => 'History'], 'slug' => 'history']));

        $grouped = DidYouKnow::forBranch($part);

        $this->assertSame([$part->id, $kid->id], $grouped->keys()->sort()->values()->all());
        $this->assertSame(2, $grouped->flatten()->count());
    }
}
