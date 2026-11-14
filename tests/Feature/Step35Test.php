<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Models\AiPrompt;
use App\Models\Category;
use App\Models\User;
use App\Support\AiImport as Importer;
use App\Support\Prompts;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 35: the form for a researched article builds one prompt. */
class Step35Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function cats(): array
    {
        $root = Category::create(['name' => ['en' => 'About Islam'], 'slug' => 'about-islam']);
        $q = Category::create(['name' => ['en' => 'Quran'], 'slug' => 'quran', 'parent_id' => $root->id]);
        $bible = Category::create(['name' => ['en' => 'Bible Index'], 'slug' => 'bible-index']);
        $plain = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);

        return [$q, $bible, $plain];
    }

    public function test_a_category_suggests_the_matching_specialist(): void
    {
        [$q, $bible, $plain] = $this->cats();

        $this->assertSame('islam-beliefs', AiPrompt::suggestFor($q));
        $this->assertSame('bible-comparative', AiPrompt::suggestFor($bible));
        $this->assertNull(AiPrompt::suggestFor($plain));
        $this->assertNull(AiPrompt::suggestFor(null));
    }

    public function test_the_prompt_holds_the_rules_and_every_choice_of_the_form(): void
    {
        [$q] = $this->cats();

        $p = Prompts::research(['topic' => 'The preservation of the Quran', 'category_id' => $q->id, 'specialist' => 'islam-beliefs', 'words' => 1800, 'depth' => 'detailed', 'comments' => 'Cover the Uthmanic copies.']);

        $this->assertStringContainsString('MASTER RULES', $p);
        $this->assertStringContainsString('ISLAMIC BELIEFS AND THE QURAN', $p);
        $this->assertStringContainsString('The preservation of the Quran', $p);
        $this->assertStringContainsString($q->code, $p);
        $this->assertStringContainsString('about 1800 words', $p);
        $this->assertStringContainsString('at least 10 good sources', $p);
        $this->assertStringContainsString('Cover the Uthmanic copies.', $p);
        $this->assertStringContainsString('TYPE: article', $p);
        $this->assertStringContainsString('PLEASE CHECK:', $p);
        $this->assertStringNotContainsString('{{', $p, 'no placeholder is left');
        $this->assertStringNotContainsString('write part 1', $p, 'a normal length is one answer');
    }

    public function test_a_long_article_is_asked_for_in_parts(): void
    {
        $this->assertStringContainsString('write part 1', Prompts::research(['topic' => 'x', 'words' => 4000]));
        $this->assertStringContainsString('about 8000 words', Prompts::research(['topic' => 'x', 'words' => 99999]));
        $this->assertStringContainsString('about 300 words', Prompts::research(['topic' => 'x', 'words' => 5]));
    }

    public function test_an_empty_form_still_gives_a_prompt_with_clear_gaps(): void
    {
        $p = Prompts::research([]);

        $this->assertStringContainsString('(write your subject here)', $p);
        $this->assertStringContainsString('(choose a category)', $p);
    }

    public function test_the_screen_picks_the_specialist_when_the_category_is_chosen(): void
    {
        [$q, $bible] = $this->cats();
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        $page = Livewire::test(AiImport::class)->assertSet('kind', 'research')
            ->set('categoryId', $bible->id)->assertSet('specialist', 'bible-comparative')
            ->set('categoryId', $q->id)->assertSet('specialist', 'islam-beliefs')
            ->set('topic', 'Who wrote the Gospels?')->set('words', 99999);

        $page->assertSet('words', 8000);
        $this->assertStringContainsString('Who wrote the Gospels?', $page->instance()->prompt());
        $page->assertSee('Comments for the AI');
    }

    public function test_the_other_kinds_still_work(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        $p = Livewire::test(AiImport::class)->set('kind', 'article')->set('topic', 'Fasting')->instance()->prompt();

        $this->assertStringContainsString('TYPE: article', $p);
        $this->assertStringContainsString('Fasting', $p);
    }

    public function test_an_answer_with_sources_is_not_lost_silently_and_does_not_break_the_other_fields(): void
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        $answer = "TYPE: article\nCATEGORY: {$cat->code}\nALSO IN:\nTITLE: A researched one\nSHORT: Short words.\nLONG:\n## First part\nWords here.\n### Small part\nMore words.\nKEYWORDS: a, b\nVIDEOS:\nSOURCE: One main source\nSOURCES:\n- PRIMARY | Book | https://example.org | opened: yes\nPLEASE CHECK:\n- The date\nEND";

        $item = Importer::parse($answer)[0];

        $this->assertSame([], $item['errors']);
        $this->assertSame('One main source', $item['data']['source']);
        $this->assertStringNotContainsString('PRIMARY', $item['data']['long']);
        $this->assertStringContainsString('## First part', $item['data']['long']);
        $this->assertNotEmpty(array_filter($item['warnings'], fn ($w) => str_contains($w, 'not saved yet')));
    }
}
