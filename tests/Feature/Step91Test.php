<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Support\ArticlePictures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 91: the extra pictures of an article go inside its text, under the main headings. */
class Step91Test extends TestCase
{
    use RefreshDatabase;

    private function article(int $pictures, array $sections = []): Article
    {
        Setting::forget();
        $cat = Category::create(['name' => ['en' => 'Topic'], 'slug' => 'topic']);
        $a = Article::create([
            'title' => ['en' => 'An article'], 'slug' => 'an-article', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'short_detail' => ['en' => 'Short.'],
            'long_detail' => ['en' => '<p>Intro words.</p><h2>First part</h2><p>One.</p><h2>Second part</h2><p>Two.</p><h2>Third part</h2><p>Three.</p>'],
        ]);
        for ($i = 1; $i <= $pictures; $i++) {
            $a->images()->create(['image' => "articles/p{$i}.webp", 'sort_order' => $i, 'alt' => ['en' => "Alt {$i}"], 'caption' => ['en' => "Caption {$i}"], 'section' => $sections[$i] ?? null]);
        }

        return $a->fresh();
    }

    public function test_pictures_without_a_number_go_in_order_one_under_each_heading(): void
    {
        $a = $this->article(2);
        $r = ArticlePictures::place($a->long_detail, $a->images);

        $this->assertMatchesRegularExpression('#<h2>First part</h2><figure class="article__inline"><img src="[^"]*p1\.webp"#', $r['html']);
        $this->assertMatchesRegularExpression('#<h2>Second part</h2><figure class="article__inline"><img src="[^"]*p2\.webp"#', $r['html']);
        $this->assertStringNotContainsString('p3', $r['html']);
        $this->assertCount(0, $r['rest']);
    }

    public function test_a_picture_with_a_number_goes_under_that_heading_and_the_others_fill_the_free_ones(): void
    {
        $a = $this->article(3, [1 => 3]);       // the first picture is for the third heading
        $r = ArticlePictures::place($a->long_detail, $a->images);

        $this->assertMatchesRegularExpression('#<h2>Third part</h2><figure class="article__inline"><img src="[^"]*p1\.webp"#', $r['html']);
        $this->assertMatchesRegularExpression('#<h2>First part</h2><figure class="article__inline"><img src="[^"]*p2\.webp"#', $r['html']);
        $this->assertMatchesRegularExpression('#<h2>Second part</h2><figure class="article__inline"><img src="[^"]*p3\.webp"#', $r['html']);
    }

    public function test_pictures_with_no_place_stay_in_the_strip_at_the_bottom(): void
    {
        $a = $this->article(5, [4 => 9]);       // 3 headings, 5 pictures, one asks for a heading that is not there
        $r = ArticlePictures::place($a->long_detail, $a->images);

        $this->assertSame(['articles/p4.webp', 'articles/p5.webp'], $r['rest']->pluck('image')->all());

        $none = ArticlePictures::place('<p>No headings at all.</p>', $a->images);
        $this->assertCount(5, $none['rest'], 'a text without headings keeps the strip');
        $this->assertSame('<p>No headings at all.</p>', $none['html']);
    }

    public function test_the_article_page_shows_the_pictures_inside_the_text_and_no_strip_when_all_are_placed(): void
    {
        $this->article(3);

        $html = $this->get('/an-article')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'class="article__inline"'));
        $this->assertStringContainsString('Caption 1', $html);
        $this->assertStringNotContainsString('article__picture-grid', $html);
    }

    public function test_the_article_page_keeps_a_strip_for_the_extra_ones(): void
    {
        $this->article(4);

        $html = $this->get('/an-article')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'class="article__inline"'));
        $this->assertSame(1, substr_count($html, 'class="article__picture-grid"'));
    }

    public function test_the_admin_form_has_the_tab_list(): void
    {
        // The box where a number was typed became a list of the real tabs (see Step 110).
        $this->assertStringContainsString("Select::make('section')", file_get_contents(app_path('Filament/Resources/Articles/ArticleResource.php')));
    }
}
