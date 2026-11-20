<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Models\User;
use App\Support\AiImport as Importer;
use App\Support\Prompts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 97: AI import guards: the prompt pasted as the answer, an example left in a field, chat talk in an own text. */
class Step97Test extends TestCase
{
    use RefreshDatabase;

    public function test_a_pasted_prompt_is_named_and_nothing_is_read_out_of_it(): void
    {
        foreach ([Prompts::research(['topic' => 'Iblees']), Prompts::research(['topic' => 'Iblees', 'own_text' => 'My own words.']), Prompts::make('article', 'Iblees')] as $prompt) {
            $items = Importer::parse($prompt, true);

            $this->assertCount(1, $items, 'one message, not many strange items');
            $this->assertStringContainsString('This is the prompt, not the answer of the AI', implode(' ', $items[0]['errors']));
            $this->assertSame([], $items[0]['data']);
        }
    }

    public function test_a_prompt_with_an_answer_after_it_is_also_stopped(): void
    {
        $text = Prompts::research(['topic' => 'Iblees'])."\n\nTYPE: article\nTITLE: Real title\nSHORT: Short.\nLONG: Some words.";

        $items = Importer::parse($text);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('prompt', $items[0]['errors'][0]);
    }

    public function test_an_example_of_the_prompt_left_in_a_field_is_a_problem(): void
    {
        $answer = "TYPE: article\nTITLE: (a clear title, at most 200 letters)\nSHORT: (one or two sentences, at most 600 letters)\nLONG: (the whole article)";

        $errors = implode(' | ', Importer::parse($answer)[0]['errors']);

        $this->assertStringContainsString('TITLE is still the example of the prompt', $errors);
        $this->assertStringContainsString('SHORT is still the example of the prompt', $errors);
        $this->assertStringContainsString('LONG is still the example of the prompt', $errors);

        $real = Importer::parse("TYPE: article\nTITLE: The Conquest of Sindh\nSHORT: A short summary.\nLONG: Some real words about the conquest.")[0]['errors'];
        $this->assertStringNotContainsString('still the example', implode(' | ', $real));
    }

    public function test_helpers_tell_placeholders_and_chat_talk_from_real_text(): void
    {
        $this->assertTrue(Importer::isPlaceholder('(leave this line empty)'));
        $this->assertFalse(Importer::isPlaceholder('The Conquest of Sindh (711)'));
        $this->assertFalse(Importer::isPlaceholder(''));

        $chat = "The conquest began in 711.\nIf you'd like, I can also:\n• Format this with clean headings\nLet me know what would help most!\nMy recommendation for historical accuracy: use al-Baladhuri.";
        $this->assertSame(
            ["If you'd like, I can also:", 'Let me know what would help most!', 'My recommendation for historical accuracy: use al-Baladhuri.'],
            Importer::chatLeftovers($chat),
        );
        $this->assertSame([], Importer::chatLeftovers("The conquest began in 711.\nMuhammad ibn al-Qasim led the army."));
        $this->assertSame(["If you\u{2019}d like, I can add more."], Importer::chatLeftovers("If you\u{2019}d like, I can add more."), 'a curly apostrophe is understood too');
    }

    public function test_the_own_text_box_warns_about_chat_lines_and_stays_quiet_otherwise(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(AiImport::class)
            ->set('ownText', "The conquest began in 711.\nLet me know what would help most!")
            ->assertSee('Check the end of your text')->assertSee('Let me know what would help most!')
            ->set('ownText', 'The conquest began in 711. It was led by Muhammad ibn al-Qasim.')
            ->assertDontSee('Check the end of your text');
    }

    public function test_the_check_button_shows_the_message_and_save_refuses(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $page = Livewire::test(AiImport::class)->set('paste', Prompts::research(['topic' => 'Iblees']))->call('check');

        $this->assertCount(1, $page->get('items'));
        $page->assertSee('This is the prompt, not the answer of the AI');

        $page->call('save');
        $this->assertSame(0, \App\Models\Article::query()->count(), 'nothing was saved');
    }
}
