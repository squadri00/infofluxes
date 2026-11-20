<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Filament\Pages\StoryMaker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 96: a number box that is emptied while somebody types a new number does not crash the page. */
class Step96Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
    }

    public function test_the_ai_import_words_box_can_be_emptied_and_the_prompt_still_builds(): void
    {
        $this->admin();
        $page = Livewire::test(AiImport::class)->set('topic', 'Iblees')->set('words', '')->assertOk();

        $this->assertSame(1500, $page->instance()->wordsCount(), 'while the box is empty the usual length is used');
        $this->assertStringContainsString('Iblees', $page->instance()->prompt());

        $page->set('words', '100');
        $this->assertSame(300, $page->get('words'), 'too small a number is raised');
        $page->set('words', '2500');
        $this->assertSame(2500, $page->get('words'));
        $this->assertSame(2500, $page->instance()->wordsCount());
    }

    public function test_the_story_maker_slides_box_can_be_emptied_and_the_prompt_still_builds(): void
    {
        $this->admin();
        $page = Livewire::test(StoryMaker::class)->set('topic', 'Abraha')->set('slides', '')->assertOk();

        $this->assertSame(8, $page->instance()->slideCount());
        $this->assertStringContainsString('Abraha', $page->instance()->prompt());

        $page->set('slides', '12');
        $this->assertSame(12, $page->get('slides'));
        $this->assertSame(12, $page->instance()->slideCount());
    }
}
