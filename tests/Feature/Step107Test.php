<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 107: "More facts" and "More in this category" are equal cards with one link each. */
class Step107Test extends TestCase
{
    use RefreshDatabase;

    public function test_more_facts_are_cards_with_a_label_a_title_a_short_line_and_a_read_line(): void
    {
        foreach ([1, 2] as $n) {
            DidYouKnow::create(['title' => ['en' => 'Fact number '.$n], 'slug' => 'fact-'.$n, 'body' => ['en' => '<p>The answer '.$n.'.</p>'], 'source' => 'A book', 'status' => 'published', 'sort_order' => $n]);
        }

        $this->get('/did-you-know/fact-1')->assertOk()
            ->assertSee('class="related"', false)->assertSee('rcard__title', false)->assertSee('Fact number 2')->assertSee('Read the fact')->assertSee('Did You Know?');
    }

    public function test_more_in_this_category_shows_the_same_cards_for_articles(): void
    {
        $cat = Category::create(['name' => ['en' => 'Science'], 'slug' => 'science']);
        foreach ([1, 2] as $n) {
            Article::create(['title' => ['en' => 'Article '.$n], 'slug' => 'article-'.$n, 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
                'short_detail' => ['en' => 'About article '.$n.'.'], 'long_detail' => ['en' => '<p>Text.</p>']]);
        }

        $this->get('/article-1')->assertOk()->assertSee('class="related"', false)->assertSee('Article 2')->assertSee('Read the article')->assertSee('Science');
    }
}
