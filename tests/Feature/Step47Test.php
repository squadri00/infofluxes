<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\AiImport as Importer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 47: text that arrives through the AI import has the status "Written" (waiting for review), so it can be filtered. */
class Step47Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function text(): string
    {
        return "TYPE: article\nCATEGORY: {$this->cat->code}\nTITLE: A written one\nSHORT: Short.\nLONG:\n## One\nWords here.\nEND";
    }

    public function test_the_status_list_has_written_between_draft_and_in_review(): void
    {
        $this->assertSame(['draft', 'written', 'in_review', 'published', 'archived'], array_keys(Article::STATUSES));
    }

    public function test_a_new_article_from_the_import_is_written(): void
    {
        $made = Importer::commit(Importer::parse($this->text()), 'raw', User::factory()->create());

        $this->assertSame('written', Article::find($made['articles'][0])->status);
    }

    public function test_filling_a_blank_index_entry_makes_it_written(): void
    {
        $entry = Article::create(['title' => ['en' => 'Planned'], 'slug' => 'planned', 'category_id' => $this->cat->id, 'working_name' => 'AL-1.1.001', 'is_index_line' => true, 'status' => 'draft']);

        Importer::commit(Importer::parse($this->text(), false, $entry->id), 'raw', User::factory()->create());

        $this->assertSame('written', $entry->fresh()->status);
    }

    public function test_a_blank_entry_stays_a_draft_and_the_filter_separates_them(): void
    {
        $blank = Article::create(['title' => ['en' => 'Blank'], 'slug' => 'blank', 'category_id' => $this->cat->id, 'is_index_line' => true, 'status' => 'draft']);
        $written = Article::create(['title' => ['en' => 'Done'], 'slug' => 'done', 'category_id' => $this->cat->id, 'is_index_line' => true, 'status' => 'written', 'long_detail' => ['en' => '<p>x</p>']]);
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(ListArticles::class)->filterTable('status', 'written')->assertCanSeeTableRecords([$written])->assertCanNotSeeTableRecords([$blank]);
        Livewire::test(ListArticles::class)->filterTable('status', 'draft')->assertCanSeeTableRecords([$blank])->assertCanNotSeeTableRecords([$written]);
    }

    public function test_a_written_article_is_not_public_and_cannot_be_published_without_the_review(): void
    {
        $a = Article::create(['title' => ['en' => 'W'], 'slug' => 'w', 'category_id' => $this->cat->id, 'status' => 'written', 'ai_assisted' => true, 'long_detail' => ['en' => '<p>x</p>']]);

        $this->get('/w')->assertNotFound();
        $this->assertTrue(\App\Support\IndexEntries::isBlank($a) === false);
    }

    public function test_a_written_article_can_be_published_after_the_review(): void
    {
        $mod = User::factory()->create(['role' => 'moderator', 'status' => 'active']);
        $this->actingAs($mod);
        $a = Article::create(['title' => ['en' => 'W'], 'slug' => 'w', 'category_id' => $this->cat->id, 'status' => 'written', 'ai_assisted' => true, 'long_detail' => ['en' => '<p>x</p>']]);

        Livewire::test(\App\Filament\Resources\Articles\Pages\EditArticle::class, ['record' => $a->getRouteKey()])
            ->fillForm(['status' => 'published', 'reviewer_name' => 'Dr Check', 'custom_data' => ['review' => ['done' => array_keys(\App\Support\ReviewChecklist::standard())]]])->call('save');

        $this->assertSame('published', $a->fresh()->status);
        $this->get('/w')->assertOk();
    }
}
