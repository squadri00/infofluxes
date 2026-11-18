<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryMaker;
use App\Models\AiPrompt;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Models\StoryRun;
use App\Models\User;
use App\Support\StoryMaker\Prompt;
use App\Support\StoryMaker\Reader;
use App\Support\StoryMaker\Rules;
use App\Support\StoryMaker\Saver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 72: the Story maker, step 1 (the story in English): prompt, check, save as a draft, saved runs, rules. */
class Step72Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        $this->cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
    }

    private function slide(int $n, ?string $body = null): string
    {
        $body ??= "A man walked to the hill. He was tired but happy. The sun was low in the sky. He sat down and looked at the town below.";

        return "SLIDE\nHEADING: Part {$n}\nBODY: {$body}\nPICTURE: A quiet hill at sunset, seen from behind\nALT: A hill at sunset\n";
    }

    /** A good answer: 4 chapters, $slides slides, the closing chapter last. */
    private function answer(int $slides = 8, array $over = []): string
    {
        $head = $over['head'] ?? "TYPE: story\nCATEGORY: Stories\nTITLE: ".($over['title'] ?? 'The Walk to the Hill')."\nSHORT: A short story about a quiet walk.\nSTYLE: Warm soft illustration\n";
        $per = [3, 2, 2];
        $out = $head;
        $n = 0;
        foreach (['The opening', 'The middle', 'The turn'] as $i => $title) {
            $out .= "CHAPTER: {$title}\n";
            for ($k = 0; $k < ($per[$i] ?? 1) && $n < $slides - 1; $k++) {
                $out .= $this->slide(++$n);
            }
        }
        $out .= 'CHAPTER: '.($over['last'] ?? 'Closing and final remarks')."\n".$this->slide(++$n);
        while ($n < $slides) {
            $out .= $this->slide(++$n);
        }

        return $out.($over['tail'] ?? "KEYWORDS: hill, walk, story\nMETA TITLE: The walk to the hill\nMETA DESCRIPTION: A short and simple story about a quiet walk to a hill at sunset, told in plain words.\nPLEASE CHECK:\n- The name of the hill\nEND");
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    // ---------- the prompt ----------

    public function test_the_prompt_has_the_story_rules_the_choices_and_the_shape(): void
    {
        $text = Prompt::english(['topic' => 'The night journey', 'category_id' => $this->cat->id, 'slides' => 7, 'notes' => 'Keep it gentle', 'style' => 'Paper cut art']);

        foreach (['RULES FOR EVERY INFOFLUXES STORY IN PICTURES', 'extremely simple words', 'The night journey', 'Stories', 'exactly 7 slides', 'Keep it gentle', 'STYLE: Paper cut art', 'Closing and final remarks', 'Never show the face of any prophet', 'TYPE: story', 'PICTURE:', 'ALT:'] as $needle) {
            $this->assertStringContainsString($needle, $text, $needle);
        }
        $this->assertStringNotContainsString('SOURCES:', $text, 'a story has no sources list');
        $this->assertStringNotContainsString('{{', $text, 'every blank is filled');
    }

    public function test_the_number_of_slides_is_free_and_an_empty_subject_has_a_hint(): void
    {
        $this->assertStringContainsString('exactly 3 slides', Prompt::english(['slides' => 3]));
        $this->assertStringContainsString('exactly 25 slides', Prompt::english(['slides' => 25]));
        $this->assertStringContainsString('exactly 60 slides', Prompt::english(['slides' => 5000]), 'only a typing mistake is stopped');
        $this->assertStringContainsString('exactly 1 slides', Prompt::english(['slides' => 0]));
        $this->assertStringNotContainsString('never fewer than 5', Prompt::english(['slides' => 8]));
        $this->assertStringNotContainsString('no more than 10', Prompt::english(['slides' => 8]));
        $this->assertStringContainsString('(write your subject here)', Prompt::english([]));
    }

    public function test_the_story_prompt_is_separate_from_the_article_prompts(): void
    {
        $before = AiPrompt::count();
        Prompt::english(['topic' => 'x']);
        Rules::save('My own story rules');
        $this->assertSame($before, AiPrompt::count());
        $this->assertStringNotContainsString('MASTER RULES FOR EVERY INFOFLUXES ARTICLE', Prompt::english(['topic' => 'x']));
        $this->assertStringContainsString('My own story rules', Prompt::english(['topic' => 'x']));
    }

    // ---------- reading and checking ----------

    public function test_a_good_answer_has_no_problems_and_is_read_into_chapters_and_slides(): void
    {
        $r = Reader::read($this->answer(8));

        $this->assertSame([], $r['errors']);
        $this->assertSame('The Walk to the Hill', $r['story']['title']);
        $this->assertSame(8, $r['story']['slide_count']);
        $this->assertSame(4, count($r['story']['chapters']));
        $this->assertSame('Closing and final remarks', end($r['story']['chapters'])['title']);
        $this->assertSame('A quiet hill at sunset, seen from behind', $r['story']['chapters'][0]['slides'][0]['picture']);
        $this->assertSame(['hill', 'walk', 'story'], $r['story']['keywords']);
        $this->assertSame('Warm soft illustration', $r['story']['style']);
        $this->assertSame([], $r['warnings']);
        $this->assertContains('The name of the hill', $r['check']);
    }

    public function test_the_answer_may_have_a_code_block_bold_keys_and_numbers_after_slide_and_chapter(): void
    {
        $text = "```\n".str_replace(['TYPE:', 'TITLE:', 'CHAPTER: The opening', 'SLIDE'], ['**TYPE:**', '**TITLE:**', 'CHAPTER 1: The opening', 'SLIDE 1'], $this->answer(6))."\n```";
        $r = Reader::read($text);

        $this->assertSame([], $r['errors']);
        $this->assertSame(6, $r['story']['slide_count']);
        $this->assertSame('The opening', $r['story']['chapters'][0]['title']);
    }

    public function test_any_number_of_slides_is_allowed_but_none_is_a_problem_and_a_different_number_than_asked_is_a_note(): void
    {
        foreach ([2, 4, 11, 24] as $n) {
            $this->assertSame([], Reader::read($this->answer($n))['errors'], $n.' slides');
        }
        $this->assertSame(24, Reader::read($this->answer(24))['story']['slide_count']);

        $none = Reader::read("TYPE: story
TITLE: Nothing
SHORT: s
CHAPTER: One
END");
        $this->assertStringContainsString('has no slides', implode(' ', $none['errors']));

        $this->assertStringContainsString('You asked for 8 slides and the answer has 6', implode(' ', Reader::read($this->answer(6), null, 8)['warnings']));
        $this->assertSame([], array_filter(Reader::read($this->answer(8), null, 8)['warnings'], fn ($w) => str_contains($w, 'asked for')));
    }

    public function test_missing_parts_are_problems(): void
    {
        $this->assertStringContainsString('TYPE: story', implode(' ', Reader::read(str_replace('TYPE: story', 'TYPE: article', $this->answer()))['errors']));
        $this->assertStringContainsString('TITLE is missing', implode(' ', Reader::read(str_replace("TITLE: The Walk to the Hill\n", '', $this->answer()))['errors']));
        $noBody = preg_replace('/BODY: A man[^\n]*\n/', '', $this->answer(), 1);
        $this->assertStringContainsString('has no BODY', implode(' ', Reader::read($noBody)['errors']));
        $noHeading = preg_replace('/HEADING: Part 1\n/', '', $this->answer(), 1);
        $this->assertStringContainsString('has no HEADING', implode(' ', Reader::read($noHeading)['errors']));
    }

    public function test_the_last_chapter_should_be_the_closing_one(): void
    {
        $r = Reader::read($this->answer(8, ['last' => 'The end']));
        $this->assertSame([], $r['errors']);
        $this->assertStringContainsString('should be "Closing and final remarks"', implode(' ', $r['warnings']));
        $this->assertNotEmpty(array_filter($r['check'], fn ($c) => str_contains($c, 'closing chapter')));
    }

    public function test_the_same_title_is_a_problem_unless_it_is_the_story_of_this_run(): void
    {
        $story = Story::create(['title' => ['en' => 'The Walk to the Hill'], 'slug' => 'the-walk-to-the-hill', 'category_id' => $this->cat->id, 'status' => 'draft']);

        $this->assertStringContainsString('already exists', implode(' ', Reader::read($this->answer())['errors']));
        $this->assertSame([], Reader::read($this->answer(), $story->id)['errors']);
    }

    public function test_salutations_web_addresses_arabic_and_long_sentences_are_noticed(): void
    {
        $body = 'Muhammad went to the cave. Ibrahim and Allah are named. Read https://example.org now. '
            ."The verse says:\nقُلْ هُوَ اللَّهُ أَحَدٌ\nSay, He is Allah, the One.\n(Surah Al-Ikhlas 112:1)\n"
            .'This is a very long sentence that keeps going and going without any rest so that the reader gets tired before the end of it arrives at last.';
        $r = Reader::read($this->answer(8, ['head' => "TYPE: story\nTITLE: Salutations\nSHORT: s\nSTYLE: x\n"]).'');
        $text = preg_replace('/BODY: A man walked[^\n]*\n/', 'BODY: '.$body."\n", $this->answer(8), 1);
        $r = Reader::read($text);
        $notes = implode(' ', $r['warnings']);

        $this->assertStringContainsString('without ﷺ', $notes);
        $this->assertStringContainsString('without (SWT)', $notes);
        $this->assertStringContainsString('without (AS)', $notes);
        $this->assertStringContainsString('web address', $notes);
        $this->assertStringContainsString('not simple enough', $notes);
        $this->assertNotEmpty(array_filter($r['check'], fn ($c) => str_contains($c, 'Arabic text')));
    }

    public function test_a_quran_reference_that_does_not_exist_is_noticed(): void
    {
        $text = preg_replace('/BODY: A man walked[^\n]*\n/', "BODY: The Quran says it (Surah Al-Ikhlas 112:9). He sat down. The sun was low in the sky. He was happy.\n", $this->answer(8), 1);
        $this->assertStringContainsString('cannot be right', implode(' ', Reader::read($text)['warnings']));
    }

    public function test_slides_that_are_too_short_or_too_long_and_a_sources_list_get_notes(): void
    {
        $text = preg_replace('/BODY: A man walked[^\n]*\n/', "BODY: Short.\n", $this->answer(8), 1);
        $this->assertStringContainsString('very short', implode(' ', Reader::read($text)['warnings']));

        $long = 'BODY: '.implode(' ', array_fill(0, 40, 'The man walked on. He saw the hill.'))."\n";
        $text2 = preg_replace('/BODY: A man walked[^\n]*\n/', $long, $this->answer(8), 1);
        $this->assertStringContainsString('is long', implode(' ', Reader::read($text2)['warnings']));

        $withSources = str_replace('KEYWORDS:', "SOURCES:\n- A book\nKEYWORDS:", $this->answer());
        $this->assertStringContainsString('does not need one', implode(' ', Reader::read($withSources)['warnings']));
    }

    public function test_a_picture_line_that_shows_a_face_of_a_prophet_is_noticed(): void
    {
        $text = preg_replace('/PICTURE: A quiet hill[^\n]*\n/', "PICTURE: The face of the Prophet looking at the sky\n", $this->answer(8), 1);
        $this->assertStringContainsString('face of a prophet', implode(' ', Reader::read($text)['warnings']));
    }

    // ---------- saving ----------

    public function test_a_checked_story_is_saved_as_a_draft_with_its_chapters_slides_and_picture_lines(): void
    {
        $user = $this->superuser();
        $run = StoryRun::create(['kind' => 'story', 'topic' => 'The walk']);
        $story = Saver::save(Reader::read($this->answer(8))['story'], $this->cat->id, $run, $user);

        $this->assertSame('draft', $story->status);
        $this->assertTrue($story->ai_assisted);
        $this->assertSame($user->id, $story->author_id);
        $this->assertSame('the-walk-to-the-hill', $story->slug);
        $this->assertSame($this->cat->id, $story->category_id);
        $this->assertSame('A short story about a quiet walk.', $story->getTranslation('short_detail', 'en'));
        $this->assertSame('The walk to the hill', $story->getTranslation('meta_title', 'en'));
        $this->assertSame(['hill', 'walk', 'story'], $story->search_words);
        $this->assertCount(4, $story->chapters);
        $this->assertSame(8, $story->slides()->count());
        $first = $story->chapters->first()->slides->first();
        $this->assertSame('Part 1', $first->getTranslation('heading', 'en'));
        $this->assertSame('A hill at sunset', $first->getTranslation('image_alt', 'en'));
        $this->assertSame('A quiet hill at sunset, seen from behind', $first->custom_data['picture']);
        $this->assertNull($first->image, 'no pictures yet');
        $this->assertSame($run->id, $story->custom_data['story_run']);
    }

    public function test_saving_the_same_run_again_updates_the_draft_and_keeps_the_pictures(): void
    {
        $run = StoryRun::create(['kind' => 'story']);
        $story = Saver::save(Reader::read($this->answer(8))['story'], $this->cat->id, $run, null);
        $run->update(['story_id' => $story->id]);
        $story->chapters->first()->slides->first()->update(['image' => 'stories/one.webp']);

        $again = Saver::save(Reader::read($this->answer(6, ['title' => 'The Walk to the Hill']), $story->id)['story'], $this->cat->id, $run, null);

        $this->assertSame($story->id, $again->id);
        $this->assertSame(1, Story::count());
        $this->assertSame(6, $again->slides()->count());
        $this->assertSame('stories/one.webp', $again->chapters->first()->slides->first()->image, 'the picture stays on its slide');
    }

    public function test_a_story_that_is_no_longer_a_draft_is_not_changed(): void
    {
        $run = StoryRun::create(['kind' => 'story']);
        $story = Saver::save(Reader::read($this->answer(8))['story'], $this->cat->id, $run, null);
        $run->update(['story_id' => $story->id]);
        $story->update(['status' => 'published']);

        $this->expectException(\RuntimeException::class);
        Saver::save(Reader::read($this->answer(8))['story'], $this->cat->id, $run, null);
    }

    // ---------- the screen ----------

    public function test_who_may_open_the_screen(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(StoryMaker::canAccess());

        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active', 'admin_screens' => ['stories', 'storymaker']]));
        $this->assertTrue(StoryMaker::canAccess());

        $this->superuser();
        $this->assertTrue(StoryMaker::canAccess());
        $this->get('/admin/story-maker')->assertOk()->assertSee('Story maker')->assertSee('Saved runs');
    }

    public function test_the_whole_flow_prompt_paste_check_save_and_the_run_is_kept(): void
    {
        $this->superuser();
        $page = Livewire::test(StoryMaker::class)
            ->set('topic', 'The walk')->set('categoryId', $this->cat->id)->set('slides', 8)
            ->set('paste', $this->answer(8))
            ->call('check');

        $page->assertSet('checked', true);
        $this->assertSame([], $page->get('result')['errors']);
        $run = StoryRun::first();
        $this->assertSame('checked', $run->status);
        $this->assertSame('The walk', $run->topic);
        $this->assertStringContainsString('TYPE: story', $run->prompt);
        $this->assertStringContainsString('TITLE: The Walk to the Hill', $run->answer);

        $page->call('saveStory')->assertNotified();
        $this->assertSame(1, Story::count());
        $story = Story::first();
        $this->assertSame('draft', $story->status);
        $run->refresh();
        $this->assertSame('saved', $run->status);
        $this->assertSame($story->id, $run->story_id);

        // Saved again: the same draft, not a second story.
        $page->call('saveStory');
        $this->assertSame(1, Story::count());
    }

    public function test_a_story_with_problems_is_not_saved_and_without_a_category_it_is_not_saved_either(): void
    {
        $this->superuser();
        Livewire::test(StoryMaker::class)->set('categoryId', $this->cat->id)->set('paste', str_replace("TITLE: The Walk to the Hill
", '', $this->answer(8)))->call('saveStory');
        $this->assertSame(0, Story::count());

        Livewire::test(StoryMaker::class)->set('paste', $this->answer(8))->call('saveStory');
        $this->assertSame(0, Story::count());
    }

    public function test_a_run_can_be_saved_before_any_answer_opened_again_and_deleted(): void
    {
        $this->superuser();
        $page = Livewire::test(StoryMaker::class)->set('topic', 'A later story')->set('categoryId', $this->cat->id)->set('slides', 6)->set('notes', 'Gentle')->call('saveRun');
        $run = StoryRun::first();
        $this->assertSame('prompt', $run->status);
        $this->assertSame(['slides' => 6, 'notes' => 'Gentle', 'style' => Rules::style(), 'length' => 'story', 'rewrite' => false, 'open' => true, 'own_text' => ''], $run->options);

        $fresh = Livewire::test(StoryMaker::class)->call('openRun', $run->id);
        $fresh->assertSet('topic', 'A later story')->assertSet('categoryId', $this->cat->id)->assertSet('slides', 6)->assertSet('notes', 'Gentle')->assertSet('runId', $run->id);

        $fresh->set('paste', $this->answer(8))->call('saveRun');
        $this->assertSame('pasted', $run->fresh()->status);
        $this->assertStringContainsString('TITLE: The Walk to the Hill', $run->fresh()->answer);

        $fresh->call('deleteRun', $run->id);
        $this->assertSame(0, StoryRun::count());
        $fresh->assertSet('runId', null)->assertSet('topic', '');
    }

    public function test_the_rules_can_be_changed_and_reset_by_the_super_user_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active', 'admin_screens' => ['storymaker']]));
        Livewire::test(StoryMaker::class)->set('ruleText', 'Hacked rules')->call('saveRules')->assertForbidden();
        $this->assertFalse(Rules::isChanged());

        $this->superuser();
        $page = Livewire::test(StoryMaker::class)->set('ruleText', "My rules\nOne")->set('ruleStyle', 'Charcoal drawing')->call('saveRules');
        $this->assertTrue(Rules::isChanged());
        $this->assertSame("My rules\nOne", Rules::master());
        $this->assertSame('Charcoal drawing', Rules::style());
        $this->assertStringContainsString('STYLE: Charcoal drawing', Prompt::english(['topic' => 'x']));

        $page->call('resetRules');
        $this->assertFalse(Rules::isChanged());
        $this->assertStringContainsString('EXTREMELY SIMPLE', mb_strtoupper(Rules::master()));
        $this->assertSame(Rules::DEFAULT_STYLE, Rules::style());
    }
}
