<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\ArticleReplace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 109: the AI import can replace the text of an existing article (a published one only by a moderator or the super user). */
class Step109Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = Category::create(['name' => ['en' => 'Topic'], 'slug' => 'topic']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function article(array $extra = []): Article
    {
        return Article::create(array_merge(['title' => ['en' => 'The Old Title'], 'slug' => 'the-old-title', 'category_id' => $this->cat->id, 'status' => 'published',
            'publish_date' => now()->subDays(3), 'short_detail' => ['en' => 'Old short.'], 'long_detail' => ['en' => '<p>Old words here.</p>'], 'reviewer_name' => 'Old Reviewer'], $extra));
    }

    private function answer(string $title = 'The New Title'): string
    {
        return "TYPE: article\nTITLE: $title\nSHORT: New short line.\nLONG:\n## A heading\nBrand new words.\nEND";
    }

    public function test_who_may_replace_which_article(): void
    {
        $live = $this->article();
        $draft = $this->article(['slug' => 'a-draft', 'title' => ['en' => 'A Draft'], 'status' => 'draft']);
        $author = $this->user('author');
        $draft->update(['author_id' => $author->id]);

        $this->assertTrue(ArticleReplace::canReplace($live, $this->user('moderator')));
        $this->assertTrue(ArticleReplace::canReplace($live, $this->user('superuser')));
        $this->assertFalse(ArticleReplace::canReplace($live, $author), 'an author cannot replace a published article');
        $this->assertTrue(ArticleReplace::canReplace($draft, $author), 'an author can replace their own draft');
        $this->assertFalse(ArticleReplace::canReplace($draft, $this->user('author')), 'not somebody else\'s draft');
    }

    public function test_the_check_shows_the_replace_banner_and_does_not_call_the_same_title_a_problem(): void
    {
        $this->actingAs($this->user('superuser'));
        $article = $this->article();

        $page = Livewire::test(AiImport::class)->call('selectReplace', $article->id)->set('paste', $this->answer('The Old Title'))->call('check');

        $page->assertSeeHtml('data-replace-banner')->assertSeeText('Will replace')->assertSee($article->code);
        $this->assertSame([], $page->get('items')[0]['errors']);
    }

    public function test_a_published_article_needs_the_box_ticked_and_then_keeps_its_id_address_status_and_date(): void
    {
        $this->actingAs($this->user('superuser'));
        $article = $this->article();
        $date = $article->publish_date->toDateTimeString();

        $page = Livewire::test(AiImport::class)->call('selectReplace', $article->id)->set('paste', $this->answer())->call('check');

        $page->call('save');
        $this->assertSame('The Old Title', $article->fresh()->mainTitle(), 'nothing changes before the box is ticked');

        $page->set('confirmReplace', true)->call('save');
        $fresh = $article->fresh();
        $this->assertSame('The New Title', $fresh->mainTitle());
        $this->assertStringContainsString('Brand new words.', (string) $fresh->long_detail);
        $this->assertSame('the-old-title', $fresh->slug, 'the web address stays');
        $this->assertSame('published', $fresh->status);
        $this->assertSame($date, $fresh->publish_date->toDateTimeString());
        $this->assertSame($this->cat->id, $fresh->category_id);
        $this->assertNull($fresh->reviewer_name, 'the reviewer is cleared: the text is new');
        $this->assertTrue((bool) $fresh->ai_assisted);
        $this->assertSame(1, Article::count(), 'no second article is made');
    }

    public function test_the_old_text_is_kept_in_the_history(): void
    {
        $this->actingAs($this->user('superuser'));
        $article = $this->article();

        Livewire::test(AiImport::class)->call('selectReplace', $article->id)->set('paste', $this->answer())->call('check')->set('confirmReplace', true)->call('save');

        $log = Activity::query()->where('event', 'updated')->where('subject_type', Article::class)->where('subject_id', $article->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Old words here.', json_encode($log->old_values, JSON_UNESCAPED_UNICODE));
    }

    public function test_an_author_cannot_pick_a_published_article_to_replace_and_the_search_finds_by_code(): void
    {
        $live = $this->article();
        $author = $this->user('author');
        $author->update(['admin_screens' => ['articles', 'import']]);
        $this->actingAs($author);

        $this->assertTrue(ArticleReplace::search($live->code, $author)->isEmpty());
        $page = Livewire::test(AiImport::class)->call('selectReplace', $live->id);
        $this->assertNull($page->instance()->replaceTarget());

        $mod = $this->user('moderator');
        $this->assertSame([$live->id], ArticleReplace::search($live->code, $mod)->pluck('id')->all());
        $this->assertSame([$live->id], ArticleReplace::search('old title', $mod)->pluck('id')->all());
    }

    public function test_the_articles_list_has_a_replace_with_ai_import_button(): void
    {
        $this->actingAs($this->user('superuser'));
        $article = $this->article();

        Livewire::test(ListArticles::class)->assertTableActionExists('replace_ai', record: $article)
            ->assertTableActionHasUrl('replace_ai', AiImport::getUrl(['replace' => $article->id]), $article);
    }
}
