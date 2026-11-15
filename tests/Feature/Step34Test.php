<?php

namespace Tests\Feature;

use App\Filament\Resources\AiPrompts\Pages\ManageAiPrompts;
use App\Models\AiPrompt;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 34: the AI prompt library (master rules and specialist prompts). */
class Step34Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_the_library_holds_the_master_rules_and_six_specialists(): void
    {
        $this->assertSame(1, AiPrompt::where('kind', 'master')->count());
        $this->assertSame(6, AiPrompt::where('kind', 'specialist')->count());
    }

    public function test_every_prompt_has_words_and_the_rules_the_owner_asked_for(): void
    {
        foreach (AiPrompt::all() as $p) {
            $this->assertGreaterThan(300, strlen($p->body), $p->slug.' has words');
        }
        $master = AiPrompt::where('slug', 'master')->value('body');
        foreach (['ﷺ', '(AS)', '(RA)', '(SWT)', 'Never change a quotation', 'actually opened', 'I could not verify this', 'Please check'] as $needle) {
            $this->assertStringContainsString($needle, $master, $needle);
        }
    }

    public function test_installing_again_adds_nothing_and_keeps_a_changed_prompt(): void
    {
        $p = AiPrompt::where('slug', 'master')->first();
        $p->update(['body' => 'My own words.']);

        $this->assertSame(0, AiPrompt::installDefaults());
        $this->assertSame('My own words.', $p->fresh()->body);
        $this->assertTrue($p->fresh()->isChanged());

        $p->resetToOriginal();
        $this->assertFalse($p->fresh()->isChanged());
    }

    public function test_combined_joins_master_and_specialist(): void
    {
        $text = AiPrompt::combined('islam-beliefs');

        $this->assertStringContainsString('MASTER RULES', $text);
        $this->assertStringContainsString('ISLAMIC BELIEFS AND THE QURAN', $text);
        $this->assertStringNotContainsString('ISLAMIC HISTORY AND CIVILISATION', $text);
        $this->assertStringContainsString('MASTER RULES', AiPrompt::combined(null));
    }

    public function test_the_admin_can_edit_and_reset_but_not_delete_the_master(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $master = AiPrompt::where('slug', 'master')->first();
        $spec = AiPrompt::where('slug', 'islam-history')->first();

        Livewire::test(ManageAiPrompts::class)
            ->assertCanSeeTableRecords(AiPrompt::all())
            ->callAction(TestAction::make('edit')->table($spec), ['title' => 'Islamic history', 'use_for' => 'x', 'body' => 'New words for history.', 'sort_order' => 3])
            ->assertHasNoActionErrors()
            ->assertActionHidden(TestAction::make('delete')->table($master))
            ->assertActionVisible(TestAction::make('reset')->table($spec))
            ->callAction(TestAction::make('reset')->table($spec));

        $this->assertFalse($spec->fresh()->isChanged());
    }

    public function test_a_new_specialist_prompt_can_be_added(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(ManageAiPrompts::class)
            ->callAction('create', ['title' => 'Sufism', 'use_for' => 'Sufi subjects', 'body' => 'Own words.', 'sort_order' => 20])
            ->assertHasNoActionErrors();

        $this->assertSame('specialist', AiPrompt::where('title', 'Sufism')->value('kind'));
    }

    public function test_only_the_super_user_may_open_and_change_the_prompts_even_with_the_import_screen_ticked(): void
    {
        foreach (['moderator', 'author'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active', 'admin_screens' => ['articles', 'import']]));
            $this->get('/admin/ai-prompts')->assertForbidden();
            $this->get('/admin/ai-import')->assertOk();   // using the import is still allowed
        }

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->get('/admin/ai-prompts')->assertOk();
    }

    public function test_a_plain_member_cannot_open_the_screen(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active']));

        $this->get('/admin/ai-prompts')->assertForbidden();
    }
}
