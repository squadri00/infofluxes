<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryMaker;
use App\Models\Category;
use App\Models\Setting;
use App\Models\StoryRun;
use App\Models\User;
use App\Support\StoryMaker\Prompt;
use App\Support\StoryMaker\Reader;
use App\Support\StoryMaker\Rules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 84: how long each slide is (short, story, story + extra), and the prophets are called by the Muslim name with the Bible name in brackets. */
class Step84Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function answer(string $body): string
    {
        return "TYPE: story\nCATEGORY: Stories\nTITLE: The Walk\nSHORT: A short story.\nSTYLE: Warm\nCHAPTER: The opening\nSLIDE\nHEADING: The hill\nBODY: {$body}\nPICTURE: A hill at dawn\nALT: A hill\n"
            ."CHAPTER: Closing and final remarks\nSLIDE\nHEADING: The end\nBODY: He went home. He was glad.\nPICTURE: A house\nALT: A house\n"
            ."KEYWORDS: walk\nMETA TITLE: The walk\nMETA DESCRIPTION: A short and simple story about a quiet walk to a hill at sunset, told in plain words for a beginner to read.\nPLEASE CHECK:\n- The hill\nEND";
    }

    private function words(int $n): string
    {
        return trim(str_repeat('He walks. ', (int) ceil($n / 2)));
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    // ---------- the levels ----------

    public function test_there_are_three_levels_and_an_unknown_one_is_the_short_one(): void
    {
        $l = Rules::lengths();

        $this->assertSame(['short', 'story', 'extra'], array_keys($l));
        $this->assertSame([25, 45], [$l['short']['min'], $l['short']['max']], 'the way stories were always made');
        $this->assertSame([60, 110], [$l['story']['min'], $l['story']['max']]);
        $this->assertSame([90, 150], [$l['extra']['min'], $l['extra']['max']]);
        $this->assertFalse($l['story']['extra']);
        $this->assertTrue($l['extra']['extra']);
        $this->assertSame('short', Rules::length('nonsense')['code']);
        $this->assertSame('short', Rules::length(null)['code']);
    }

    public function test_the_super_user_can_change_the_numbers_and_wrong_numbers_are_ignored(): void
    {
        Rules::saveLengths(['story' => ['min' => 70, 'max' => 130], 'extra' => ['min' => 200, 'max' => 100]]);   // the second is wrong (more than the most)

        $this->assertSame([70, 130], [Rules::lengths()['story']['min'], Rules::lengths()['story']['max']]);
        $this->assertSame([90, 150], [Rules::lengths()['extra']['min'], Rules::lengths()['extra']['max']]);

        Rules::resetLengths();
        $this->assertSame([60, 110], [Rules::lengths()['story']['min'], Rules::lengths()['story']['max']]);
    }

    // ---------- the prompt ----------

    public function test_the_prompt_follows_the_level_and_leaves_no_raw_placeholder(): void
    {
        $short = Prompt::english(['topic' => 'A walk']);
        $this->assertStringContainsString('about 25 to 45 words', $short);
        $this->assertStringNotContainsString('good to know', $short);

        $story = Prompt::english(['topic' => 'A walk', 'length' => 'story']);
        $this->assertStringContainsString('about 60 to 110 words', $story);
        $this->assertStringNotContainsString('about 25 to 45 words', $story);
        $this->assertStringNotContainsString('good to know', $story);

        $extra = Prompt::english(['topic' => 'A walk', 'length' => 'extra']);
        $this->assertStringContainsString('about 90 to 150 words', $extra);
        $this->assertStringContainsString('ONE short "good to know" sentence', $extra);
        $this->assertStringContainsString('Never invent a fact', $extra);

        foreach ([$short, $story, $extra] as $text) {
            $this->assertStringNotContainsString('{{', $text);
        }
    }

    public function test_the_prompt_asks_for_the_muslim_name_with_the_bible_name_in_brackets(): void
    {
        $text = Prompt::english(['topic' => 'A walk']);

        $this->assertStringContainsString('Call every prophet by the name the Quran uses', $text);
        $this->assertStringContainsString('Isa (AS) (Jesus)', $text);
        $this->assertStringContainsString('Musa (AS) (Moses)', $text);
        $this->assertStringContainsString('Ibrahim (AS) (Abraham)', $text);
        $this->assertStringContainsString('Never write the Bible name on its own', $text);
        $this->assertStringContainsString('with (AS) and the Bible name in brackets at the first mention only', $text, 'the task repeats it, so it also holds when the rules were changed');
    }

    // ---------- the check ----------

    public function test_a_longer_slide_is_fine_for_a_longer_level_and_too_long_for_the_short_one(): void
    {
        $body = $this->words(90);

        $short = Reader::read($this->answer($body));
        $this->assertTrue(collect($short['warnings'])->contains(fn ($w) => str_contains($w, 'Slide 1 is long')), 'too long for the usual numbers');

        $story = Reader::read($this->answer($body), null, null, 'story');
        $this->assertFalse(collect($story['warnings'])->contains(fn ($w) => str_contains($w, 'Slide 1 is long')), 'fine for the story level');
        $this->assertSame([], $story['errors']);
    }

    public function test_a_thin_slide_is_told_the_words_of_its_level(): void
    {
        $r = Reader::read($this->answer($this->words(20)), null, null, 'story');
        $warning = collect($r['warnings'])->first(fn ($w) => str_contains($w, 'Slide 1 is very short'));

        $this->assertNotNull($warning);
        $this->assertStringContainsString('About 60 to 110 words', $warning);
    }

    public function test_a_bible_name_on_its_own_is_flagged_and_the_name_with_its_brackets_is_not(): void
    {
        $alone = Reader::read($this->answer('Moses went to the hill. He was glad.'));
        $this->assertTrue(collect($alone['warnings'])->contains(fn ($w) => str_contains($w, 'A Bible name is used on its own') && str_contains($w, 'Moses') && str_contains($w, 'Musa')));

        $good = Reader::read($this->answer('Musa (AS) (Moses) went to the hill. Musa (AS) was glad.'));
        $this->assertFalse(collect($good['warnings'])->contains(fn ($w) => str_contains($w, 'A Bible name is used on its own')));
        $this->assertFalse(collect($good['warnings'])->contains(fn ($w) => str_contains($w, 'The Bible name is missing')));
        $this->assertFalse(collect($good['warnings'])->contains(fn ($w) => str_contains($w, 'without (AS)')), 'the Bible name in brackets does not need its own (AS)');
    }

    public function test_the_bible_name_is_asked_for_when_the_muslim_name_has_none(): void
    {
        $r = Reader::read($this->answer('Isa (AS) went to the hill. Isa (AS) was glad.'));

        $this->assertTrue(collect($r['warnings'])->contains(fn ($w) => str_contains($w, 'The Bible name is missing') && str_contains($w, 'Isa (the Bible name is Jesus)')));
    }

    // ---------- the screen ----------

    public function test_the_screen_keeps_the_choice_in_the_prompt_the_check_and_the_saved_run(): void
    {
        $this->superuser();
        $cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);

        $page = Livewire::test(StoryMaker::class)->set('topic', 'The walk')->set('categoryId', $cat->id)->set('length', 'story');
        $this->assertStringContainsString('about 60 to 110 words', $page->instance()->prompt());

        $page->set('paste', $this->answer($this->words(90)))->call('check');
        $warnings = $page->get('result')['warnings'];
        $this->assertFalse(collect($warnings)->contains(fn ($w) => str_contains($w, 'is long')), 'the check follows the level');

        $run = StoryRun::first();
        $this->assertSame('story', $run->options['length']);

        $page->call('newRun')->assertSet('length', 'short');
        $page->call('openRun', $run->id)->assertSet('length', 'story');
    }

    public function test_the_super_user_sets_the_words_of_a_level_on_the_screen(): void
    {
        $this->superuser();

        Livewire::test(StoryMaker::class)
            ->assertSet('lengthEdits.story.min', 60)
            ->set('lengthEdits.story.min', 75)->set('lengthEdits.story.max', 125)
            ->call('saveRules');

        $this->assertSame([75, 125], [Rules::lengths()['story']['min'], Rules::lengths()['story']['max']]);

        Livewire::test(StoryMaker::class)->call('resetRules')->assertSet('lengthEdits.story.min', 60);
        $this->assertSame([60, 110], [Rules::lengths()['story']['min'], Rules::lengths()['story']['max']]);
    }

    public function test_the_choice_is_on_the_screen_with_the_words_of_each_level(): void
    {
        $this->superuser();

        Livewire::test(StoryMaker::class)
            ->assertSee('How long should each slide be?')
            ->assertSee('about 25 to 45 words')
            ->assertSee('about 60 to 110 words')
            ->assertSee('about 90 to 150 words')
            ->assertSee('Story + extra');
    }
}
