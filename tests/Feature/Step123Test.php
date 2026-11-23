<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 123: a fact filed in a category that holds subjects (index lines) opens without an error; its page has no "current" article. */
class Step123Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    protected function tearDown(): void
    {
        Setting::forget();
        parent::tearDown();
    }

    public function test_a_fact_in_a_category_with_subjects_opens_and_lists_them_in_the_side_tree(): void
    {
        $master = Category::create(['name' => ['en' => 'Master'], 'slug' => 'master']);
        $quran = Category::create(['name' => ['en' => 'The Quran'], 'slug' => 'the-quran', 'parent_id' => $master->id]);
        $group = Category::create(['name' => ['en' => 'Group'], 'slug' => 'group', 'parent_id' => $quran->id]);
        Article::create(['title' => ['en' => 'A published subject'], 'slug' => 'a-published-subject', 'category_id' => $group->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'is_index_line' => true, 'short_detail' => ['en' => 'Short.']]);
        Article::create(['title' => ['en' => 'A planned subject'], 'slug' => 'a-planned-subject', 'category_id' => $group->id, 'status' => 'draft', 'is_index_line' => true]);
        DidYouKnow::create(['title' => ['en' => 'The Quran Explained'], 'slug' => 'the-quran-explained', 'body' => ['en' => '<p>The answer.</p>'], 'source' => 'A book', 'status' => 'published', 'category_id' => $quran->id]);

        $this->get('/did-you-know/the-quran-explained')->assertOk()->assertSee('The Quran Explained')->assertSee('A published subject')->assertSee('A planned subject');
    }
}
