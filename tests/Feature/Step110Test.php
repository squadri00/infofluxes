<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\ArticlePictures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 110: a picture of an article is put under a tab (a numbered card) by picking the tab from a list of the real headings. */
class Step110Test extends TestCase
{
    use RefreshDatabase;

    private const TEXT = '<p>Intro.</p><h2>1. Executive Summary</h2><p>A.</p><h2>The <em>Beginning</em> of His Travels</h2><p>B.</p><h3>Not a tab</h3><h2>3. Conclusion</h2><p>C.</p>';

    public function test_the_tabs_are_the_main_headings_in_order(): void
    {
        $this->assertSame([1 => '1. Executive Summary', 2 => 'The Beginning of His Travels', 3 => '3. Conclusion'], ArticlePictures::headings(self::TEXT));
        $this->assertSame([], ArticlePictures::headings('<p>No headings here.</p>'));
    }

    public function test_the_list_names_each_tab_with_its_number_once(): void
    {
        $options = ArticlePictures::tabOptions(self::TEXT);

        $this->assertSame('1. Executive Summary', $options[1], 'a heading that already starts with its number is not numbered twice');
        $this->assertSame('2. The Beginning of His Travels', $options[2]);
        $this->assertSame('3. Conclusion', $options[3]);
    }

    public function test_a_number_whose_heading_is_gone_stays_in_the_list(): void
    {
        $options = ArticlePictures::tabOptions(self::TEXT, 7);

        $this->assertArrayHasKey(7, $options);
        $this->assertStringContainsString('no such heading', $options[7]);
    }

    public function test_a_closed_picture_row_shows_its_tab_and_what_it_shows(): void
    {
        $this->assertSame('Tab 2. The Beginning of His Travels · Ibn Battuta at sea', ArticlePictures::itemLabel(['section' => 2, 'alt' => ['en' => 'Ibn Battuta at sea']], self::TEXT));
        $this->assertSame('No tab chosen · Picture', ArticlePictures::itemLabel(['section' => null, 'alt' => ['en' => '']], self::TEXT));
    }

    public function test_the_edit_page_opens_with_the_new_picture_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $cat = Category::create(['name' => ['en' => 'Topic'], 'slug' => 'topic']);
        $article = Article::create(['title' => ['en' => 'With tabs'], 'slug' => 'with-tabs', 'category_id' => $cat->id, 'status' => 'draft', 'long_detail' => ['en' => self::TEXT]]);
        $article->images()->create(['image' => 'articles/x.webp', 'section' => 2, 'alt' => ['en' => 'Ibn Battuta at sea']]);

        $page = Livewire::test(EditArticle::class, ['record' => $article->id])->assertOk()->assertSee('Tab 2. The Beginning of His Travels')->assertDontSee('Put it in section number');
        $this->assertMatchesRegularExpression('#<option[^>]*>\s*1\. Executive Summary\s*</option>#', $page->html());
        $this->assertMatchesRegularExpression('#<option[^>]*>\s*3\. Conclusion\s*</option>#', $page->html());
    }

    public function test_the_tabs_are_also_read_from_the_editor_document_while_the_text_is_being_edited(): void
    {
        $doc = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Intro']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => '1. Executive '], ['type' => 'text', 'text' => 'Summary', 'marks' => [['type' => 'bold']]]]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Not a tab']]],
            ['type' => 'blockquote', 'content' => [['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Inside a box']]]]],
        ]];

        $this->assertSame([1 => '1. Executive Summary', 2 => 'Inside a box'], ArticlePictures::headings($doc));
        $this->assertSame([], ArticlePictures::headings(null));
    }
}
