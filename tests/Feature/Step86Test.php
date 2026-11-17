<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryMaker;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Support\StoryMaker\Prompt;
use App\Support\StoryMaker\Reader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 86: the story must flow (prompt rules, flow checks) and a draft can be told again in a story voice. */
class Step86Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        $this->cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
    }

    private function draft(): Story
    {
        $story = Story::create(['title' => ['en' => 'The Walk'], 'slug' => 'the-walk', 'category_id' => $this->cat->id, 'status' => 'draft', 'short_detail' => ['en' => 'A man walks.']]);
        $ch = $story->chapters()->create(['title' => ['en' => 'The opening'], 'sort_order' => 1]);
        $ch->slides()->create(['sort_order' => 1, 'heading' => ['en' => 'The hill'], 'body' => ['en' => "A man walked.\nHe saw a hill."], 'custom_data' => ['picture' => 'A hill at dawn']]);
        $ch->slides()->create(['sort_order' => 2, 'heading' => ['en' => 'Home'], 'body' => ['en' => 'He went home.']]);

        return $story;
    }

    public function test_the_prompt_asks_for_a_flowing_story(): void
    {
        $p = Prompt::english(['topic' => 'The walk']);
        $this->assertStringContainsString('THE FLOW OF THE STORY', $p);
        $this->assertStringContainsString('one flowing paragraph', $p);
        $this->assertStringNotContainsString('{{', $p);
    }

    public function test_the_rewrite_prompt_carries_the_text_and_the_rules(): void
    {
        $p = Prompt::rewrite($this->draft(), ['length' => 'short']);
        $this->assertStringContainsString('A man walked.', $p);
        $this->assertStringContainsString('A hill at dawn', $p);
        $this->assertStringContainsString('ADD NOTHING NEW', $p);
        $this->assertStringContainsString('exactly 2 slides in 1 chapters', $p);
        $this->assertStringNotContainsString('{{', $p);
    }

    public function test_flow_warnings_fire_on_a_list_and_not_on_a_flowing_slide(): void
    {
        $list = "The Quran tells us a thing.\nThe Quran tells us another.\nThe Quran tells us a third.\nThe Quran tells us it ends.";
        $flowing = 'One evening the man walked slowly up the quiet hill near his home, and when he reached the top he sat down. Then he looked at the sky and was glad.';
        $text = fn ($b) => Reader::flowProblems(2, $b, $b);

        $this->assertNotEmpty(array_filter($text($list), fn ($w) => str_contains($w, 'reads like a list')));
        $this->assertNotEmpty(array_filter(Reader::flowProblems(2, str_replace("\n", ' ', $list).' The Quran tells us more.', str_replace("\n", ' ', $list).' The Quran tells us more.'), fn ($w) => str_contains($w, 'same way')));
        $this->assertNotEmpty(array_filter($text("He sat.\nHe ate.\nHe slept.\nHe woke.\nHe left."), fn ($w) => str_contains($w, 'very short')));
        $this->assertSame([], $text($flowing));
    }

    public function test_rewrite_mode_is_for_drafts_only_and_is_remembered(): void
    {
        $story = $this->draft();
        $run = \App\Models\StoryRun::create(['kind' => 'story', 'topic' => 'The walk', 'category_id' => $this->cat->id, 'story_id' => $story->id, 'status' => 'saved', 'options' => []]);

        $page = Livewire::test(StoryMaker::class)->call('startRewrite', $run->id)
            ->assertSet('rewrite', true)->assertSet('slides', 2)->assertSee('Rewrite in story voice')->assertSee('A man walked.');
        $this->assertTrue($run->fresh()->options['rewrite']);

        $page->call('stopRewrite')->assertSet('rewrite', false)->assertDontSee('Back to a normal prompt');

        $story->update(['status' => 'published']);
        Livewire::test(StoryMaker::class)->call('startRewrite', $run->id)->assertSet('rewrite', false);
    }
}
