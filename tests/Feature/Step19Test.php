<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 19: the reading page has the branch of topics on the left and the article beside it. */
class Step19Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function article(string $title, Category $category, array $extra = []): Article
    {
        return Article::create(array_merge([
            'title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'category_id' => $category->id,
            'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => '<p>Text.</p>'],
        ], $extra));
    }

    private function tree(): array
    {
        $part = Category::create(['name' => ['en' => 'Faith'], 'slug' => 'faith']);
        $one = Category::create(['name' => ['en' => 'Prayer'], 'slug' => 'prayer', 'parent_id' => $part->id]);
        $two = Category::create(['name' => ['en' => 'Fasting'], 'slug' => 'fasting', 'parent_id' => $part->id]);

        return [$part, $one, $two];
    }

    public function test_the_left_side_lists_every_sub_category_with_its_topics_and_opens_the_current_one(): void
    {
        [, $one, $two] = $this->tree();
        $here = $this->article('Why we pray', $one);
        $this->article('Five daily prayers', $one);
        $this->article('Why we fast', $two);

        $html = $this->get('/why-we-pray')->assertOk()->getContent();

        $this->assertStringContainsString('side-tree', $html);
        foreach (['Faith', 'Prayer', 'Fasting', 'Five daily prayers', 'Why we fast'] as $word) {
            $this->assertStringContainsString($word, $html);
        }
        $this->assertMatchesRegularExpression('#<details class="side-tree__cat"\s+open\s*>\s*<summary>Prayer</summary>#', $html, 'the row of the article being read is open');
        $this->assertDoesNotMatchRegularExpression('#<details class="side-tree__cat"\s+open\s*>\s*<summary>Fasting</summary>#', $html, 'the other rows are closed');
        $this->assertStringContainsString('aria-current="page">Why we pray', $html, 'the current topic is marked');
    }

    public function test_hidden_and_draft_things_are_not_in_the_side_tree(): void
    {
        [, $one, $two] = $this->tree();
        $this->article('Why we pray', $one);
        $this->article('Secret draft', $two, ['status' => 'draft']);
        $two->update(['is_visible' => false]);
        $this->article('In a hidden place', $two);

        $html = $this->get('/why-we-pray')->assertOk()->getContent();

        $this->assertStringNotContainsString('Secret draft', $html);
        $this->assertStringNotContainsString('In a hidden place', $html);
    }

    public function test_the_article_has_no_box_on_the_right_but_remembers_itself_for_the_recent_tab(): void
    {
        [, $one] = $this->tree();
        $this->article('Why we pray', $one);

        $html = $this->get('/why-we-pray')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-recent-max="5"', $html, 'no box beside the article any more');
        $this->assertStringNotContainsString('reading__side--right', $html);
        $this->assertStringContainsString('reading--two', $html, 'two columns: the branch and the article');
        $this->assertStringContainsString('data-recent-title="Why we pray"', $html);
        $this->assertStringContainsString('js/recent.js', $html);
    }

    public function test_the_script_keeps_the_list_in_the_browser_only(): void
    {
        $js = (string) file_get_contents(public_path('js/recent.js'));

        $this->assertStringContainsString('localStorage', $js);
        $this->assertStringNotContainsString('fetch(', $js);
        $this->assertStringNotContainsString('XMLHttpRequest', $js);
    }

    public function test_an_article_without_a_category_still_opens(): void
    {
        $article = Article::create(['title' => ['en' => 'Loose'], 'slug' => 'loose', 'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => '<p>x</p>']]);

        $this->get('/loose')->assertOk()->assertDontSee('side-tree', false);
    }
}
