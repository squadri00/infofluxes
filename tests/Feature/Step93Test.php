<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Support\CategoryFlattener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Step 93: a section can be shrunk to 3 levels; deeper articles move up and keep their old names as a Subtopic. */
class Step93Test extends TestCase
{
    use RefreshDatabase;

    private function tree(): array
    {
        $l1 = Category::create(['name' => ['en' => 'Section'], 'slug' => 'section']);
        $l2 = Category::create(['name' => ['en' => 'Index'], 'slug' => 'index-two', 'parent_id' => $l1->id]);
        $l3 = Category::create(['name' => ['en' => 'Topic'], 'slug' => 'topic-three', 'parent_id' => $l2->id]);
        $l4 = Category::create(['name' => ['en' => 'Group'], 'slug' => 'group-four', 'parent_id' => $l3->id]);
        $l5 = Category::create(['name' => ['en' => 'Subject'], 'slug' => 'subject-five', 'parent_id' => $l4->id]);
        $other = Category::create(['name' => ['en' => 'Other'], 'slug' => 'other-five', 'parent_id' => $l4->id]);
        $mk = fn ($slug, $cat) => Article::create(['title' => ['en' => $slug], 'slug' => $slug, 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'S'], 'long_detail' => ['en' => '<p>x</p>']]);
        $a = $mk('deep-one', $l5);
        $b = $mk('deep-two', $other);
        $c = $mk('already-ok', $l3);
        DB::table('article_category')->insert(['article_id' => $c->id, 'category_id' => $l5->id]);

        return compact('l1', 'l2', 'l3', 'l4', 'l5', 'other', 'a', 'b', 'c');
    }

    public function test_a_dry_run_only_counts(): void
    {
        $t = $this->tree();
        $r = CategoryFlattener::run($t['l1'], 3, false);

        $this->assertFalse($r['applied']);
        $this->assertSame(3, $r['kept']);
        $this->assertSame(3, $r['removed']);
        $this->assertSame(2, $r['articles']);
        $this->assertSame($t['l5']->id, $t['a']->fresh()->category_id);
        $this->assertNull(Category::find($t['l5']->id)?->deleted_at);
    }

    public function test_applying_moves_articles_up_keeps_the_names_and_trashes_the_old_categories(): void
    {
        $t = $this->tree();
        $r = CategoryFlattener::run($t['l1'], 3, true);

        $this->assertTrue($r['applied']);
        $this->assertSame($t['l3']->id, $t['a']->fresh()->category_id);
        $this->assertSame('Group > Subject', $t['a']->fresh()->custom_data['subtopic']);
        $this->assertSame('Group > Other', $t['b']->fresh()->custom_data['subtopic']);
        $this->assertNull($t['c']->fresh()->custom_data['subtopic'] ?? null);
        $this->assertSoftDeleted('categories', ['id' => $t['l5']->id]);
        $this->assertSoftDeleted('categories', ['id' => $t['l4']->id]);
        $this->assertNull(Category::find($t['l3']->id)->children()->first(), 'the topic is now a last-level category');
        $this->assertSame(0, DB::table('article_category')->where('category_id', $t['l5']->id)->count());
        $this->assertSame(0, DB::table('article_category')->where('article_id', $t['c']->id)->count(), 'the also-in link would repeat the main category');
    }

    public function test_the_old_address_leads_to_the_category_that_took_over_and_the_subtopic_shows(): void
    {
        $t = $this->tree();
        CategoryFlattener::run($t['l1'], 3, true);

        $this->get('/category/subject-five')->assertRedirect('/category/topic-three');
        $this->get('/category/topic-three')->assertOk()->assertSee('deep-one');
        $this->get('/deep-one')->assertOk()->assertSee('Subtopic: Group &gt; Subject', false);
    }

    public function test_the_all_articles_search_finds_a_subtopic(): void
    {
        $t = $this->tree();
        CategoryFlattener::run($t['l1'], 3, true);

        $this->get('/category/index-two?list=all&q=Subject')->assertOk()->assertSee('deep-one')->assertDontSee('deep-two');
    }

    public function test_the_index_view_groups_the_lines_under_their_subtopic(): void
    {
        $t = $this->tree();
        CategoryFlattener::run($t['l1'], 3, true);

        $html = $this->get('/category/topic-three?view=index')->assertOk()->getContent();
        // (The banner above the index may name them too; only the index itself is checked for the order.)
        $html = substr($html, (int) strpos($html, 'category-controls'));
        $this->assertStringContainsString('Group', $html);
        $this->assertStringContainsString('Subject', $html);
        $this->assertStringContainsString('deep-one', $html);
        $this->assertLessThan(strpos($html, 'deep-one'), strpos($html, 'Subject'), 'the heading comes before its line');
        $this->assertStringNotContainsString('C-6', $html, 'a group heading has no category code');
    }
}
