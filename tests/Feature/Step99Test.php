<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 99: a draft can be previewed on the website from the AI import, the Articles list and the edit page. */
class Step99Test extends TestCase
{
    use RefreshDatabase;

    private function draft(): Article
    {
        $cat = Category::create(['name' => ['en' => 'Topic'], 'slug' => 'topic']);

        return Article::create(['title' => ['en' => 'A draft to preview'], 'slug' => 'a-draft-to-preview', 'category_id' => $cat->id, 'status' => 'draft',
            'short_detail' => ['en' => 'Short.'], 'long_detail' => ['en' => '<p>Some words here.</p>']]);
    }

    private function staff(string $role = 'superuser'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    public function test_staff_see_the_draft_on_the_website_with_a_draft_notice_and_visitors_do_not(): void
    {
        $this->draft();

        $this->get('/a-draft-to-preview')->assertNotFound();

        $this->actingAs($this->staff());
        $this->get('/a-draft-to-preview')->assertOk()->assertSee('A draft to preview')->assertSee('noindex', false);
    }

    public function test_after_saving_the_ai_import_lists_each_draft_with_a_preview_link(): void
    {
        $draft = $this->draft();
        $this->actingAs($this->staff());

        $page = Livewire::test(AiImport::class);
        $page->set('done', ['articles' => [$draft->id], 'categories' => [], 'skipped' => 0]);

        $page->assertSee('Preview on the website')->assertSee($draft->code)->assertSee('A draft to preview')
            ->assertSeeHtml('href="'.route('article.show', 'a-draft-to-preview').'"')->assertSeeHtml('target="_blank"');
    }

    public function test_the_articles_list_and_the_edit_page_have_a_preview_button(): void
    {
        $draft = $this->draft();
        $this->actingAs($this->staff());

        Livewire::test(ListArticles::class)->assertTableActionExists('preview')
            ->assertTableActionHasUrl('preview', route('article.show', 'a-draft-to-preview'), $draft);

        Livewire::test(EditArticle::class, ['record' => $draft->id])->assertActionExists('preview')
            ->assertActionHasUrl('preview', route('article.show', 'a-draft-to-preview'));
    }

    public function test_an_author_can_preview_their_own_draft_too(): void
    {
        $draft = $this->draft();
        $author = $this->staff('author');
        $draft->update(['author_id' => $author->id]);
        $this->actingAs($author);

        Livewire::test(ListArticles::class)->assertTableActionExists('preview', record: $draft);
        $this->get('/a-draft-to-preview')->assertOk();
    }
}
