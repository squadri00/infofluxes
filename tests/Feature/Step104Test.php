<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 104: after Check and before Save, an article can be looked at in the website's own style. */
class Step104Test extends TestCase
{
    use RefreshDatabase;

    public function test_a_checked_article_has_a_preview_and_nothing_is_saved(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        $page = Livewire::test(AiImport::class)
            ->set('paste', "TYPE: article\nTITLE: A Real Title\nSHORT: A short line.\nLONG:\n## First part\nSome words here.\nEND")
            ->call('check');

        $page->assertSee('Preview: see how this article will look on the website')
            ->assertSeeHtml('css/site.css')->assertSeeHtml('article__body')->assertSeeHtml('A Real Title')
            ->assertSeeHtml('First part')->assertSeeHtml('Some words here.');
        $this->assertSame(0, Article::count());
    }

    public function test_a_fact_or_a_problem_item_has_no_preview(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(AiImport::class)
            ->set('paste', "TYPE: article\nSHORT: no title here\nLONG:\nWords.\nEND")
            ->call('check')->assertDontSee('Preview: see how this article will look on the website');
    }
}
