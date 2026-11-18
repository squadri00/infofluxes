<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Models\User;
use App\Support\Prompts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 90: an article text the person already has can be put into the shape of an article (AI import). */
class Step90Test extends TestCase
{
    use RefreshDatabase;

    public function test_the_normal_research_prompt_is_not_changed(): void
    {
        $p = Prompts::research(['topic' => 'Iblees']);

        $this->assertStringContainsString('THE ARTICLE TO WRITE', $p);
        $this->assertStringNotContainsString('THE TEXT TO PUT INTO SHAPE', $p);
        $this->assertStringNotContainsString('{{', $p);
    }

    public function test_an_own_text_gets_a_prompt_that_only_changes_its_shape(): void
    {
        $own = "Who is Iblees?\n\nIblees refused to bow to Adam (AS). Allah (SWT) said: \"Get out\" (Quran 7:13).";
        $p = Prompts::research(['topic' => 'Iblees', 'own_text' => $own]);

        $this->assertStringContainsString('THE ARTICLE TO PUT INTO SHAPE', $p);
        $this->assertStringContainsString('Iblees refused to bow to Adam (AS).', $p);
        $this->assertStringContainsString('CHANGE OF SHAPE', $p);
        $this->assertStringContainsString('Never invent a source', $p);
        $this->assertStringContainsString('TYPE: article', $p);
        $this->assertStringContainsString('SOURCES:', $p);
        $this->assertStringContainsString('PLEASE CHECK:', $p);
        $this->assertStringNotContainsString('THE ARTICLE TO WRITE', $p);
        $this->assertStringNotContainsString('{{', $p);
        $this->assertStringNotContainsString('write part 1', $p, 'a text that is put into shape is not written in parts');
    }

    public function test_a_long_own_text_is_not_asked_for_in_parts_and_keeps_its_length(): void
    {
        $own = implode(' ', array_fill(0, 3000, 'word'));
        $p = Prompts::research(['topic' => 'Long', 'own_text' => $own]);

        $this->assertStringContainsString('about 3000 words', $p);
        $this->assertStringNotContainsString('write part 1', $p);
    }

    public function test_the_ai_import_screen_uses_it_and_the_text_can_be_cleared(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        $page = Livewire::test(AiImport::class)->assertSee('I already have an article text');
        $this->assertStringContainsString('THE ARTICLE TO WRITE', $page->instance()->prompt());

        $page->set('ownText', 'My own words about Iblees.')->assertSee('put my text into shape');
        $this->assertStringContainsString('My own words about Iblees.', $page->instance()->prompt());

        $page->call('$set', 'ownText', '')->assertDontSee('put my text into shape');
    }

    public function test_a_check_result_without_a_short_line_still_shows(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $item = ['kind' => 'article', 'title' => 'A title', 'errors' => ['TITLE is missing.'], 'warnings' => [], 'data' => ['long' => 'Some words here.']];

        Livewire::test(AiImport::class)->set('items', [$item])->set('checked', true)->assertOk()->assertSee('A title')->assertSee('How it will look in Google');
    }
}
