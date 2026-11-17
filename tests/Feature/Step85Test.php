<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryMaker;
use App\Filament\Pages\StoryTranslations;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Models\StorySlide;
use App\Models\User;
use App\Support\StoryMaker\Reader;
use App\Support\StoryMaker\Rules;
use App\Support\StoryMaker\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 85: read the whole story before the draft, a note when an update changes the slides of a draft with pictures, translations marked out of date. */
class Step85Test extends TestCase
{
    use RefreshDatabase;

    private ?Category $cat = null;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        $this->cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    /** A good answer with $slides slides in 2 chapters (the second is the closing one). */
    private function answer(int $slides = 3, string $title = 'The Walk'): string
    {
        $out = "TYPE: story\nCATEGORY: Stories\nTITLE: {$title}\nSHORT: A short story about a quiet walk.\nSTYLE: Warm\nCHAPTER: The opening\n";
        for ($i = 1; $i < $slides; $i++) {
            $out .= "SLIDE\nHEADING: Heading {$i}\nBODY: He walks to the hill. The day is calm. He feels at peace. He looks at the sky and he is glad about it.\nPICTURE: A hill at dawn\nALT: A hill\n";
        }
        $out .= "CHAPTER: Closing and final remarks\nSLIDE\nHEADING: The end\nBODY: He goes home. He is glad. The night is quiet and he sleeps well with a calm mind.\nPICTURE: A house\nALT: A house\n";

        return $out."KEYWORDS: walk\nMETA TITLE: The walk\nMETA DESCRIPTION: A short and simple story about a quiet walk to a hill at sunset, told in plain words for a beginner to read.\nPLEASE CHECK:\n- The hill\nEND";
    }

    private function saved(int $slides = 3): array
    {
        $page = Livewire::test(StoryMaker::class)->set('topic', 'The walk')->set('categoryId', $this->cat->id)->set('paste', $this->answer($slides))->call('check');

        return [$page, $page->set('paste', $this->answer($slides))->call('saveStory')];
    }

    // ---------- the helpers of the check ----------

    public function test_the_words_of_a_slide_and_the_notes_of_a_slide(): void
    {
        $this->assertSame(5, Reader::wordCount('He walks to the hill.'));
        $this->assertSame([2 => ['Slide 2 is long (90 words).'], 3 => ['Slide 3 has no ALT line.', 'Slide 3 is thin.']],
            Reader::warningsBySlide(['The story has only one chapter.', 'Slide 2 is long (90 words).', 'Slide 3 has no ALT line.', 'Slide 3 is thin.']));
    }

    // ---------- the reading panel ----------

    public function test_the_whole_story_can_be_read_before_it_is_saved_and_nothing_is_saved_by_reading(): void
    {
        $this->superuser();
        $page = Livewire::test(StoryMaker::class)->set('topic', 'The walk')->set('categoryId', $this->cat->id)->set('paste', $this->answer(4))->call('check')
            ->assertSee('Read the whole story')->assertSee('Open all chapters')->assertDontSee('The whole story, in order');

        $page->call('openReading')->assertSet('readOpen', true)
            ->assertSee('The whole story, in order')->assertSee('Heading 1')->assertSee('Heading 3')->assertSee('Slide 4')->assertSee('He goes home.')
            ->assertSee('Chapter 2: Closing and final remarks')->assertSee('words');

        $this->assertSame(0, Story::count(), 'reading saves nothing');

        $page->call('closeReading')->assertSet('readOpen', false)->assertDontSee('The whole story, in order');
    }

    public function test_a_quran_verse_is_shown_on_its_own_line_from_right_to_left(): void
    {
        $this->superuser();
        $answer = str_replace('He walks to the hill. The day is calm.', "He remembered a verse.\nقُلْ هُوَ اللَّهُ أَحَدٌ\nSay, He is Allah, the One.\n(Quran 112:1)", $this->answer(3));

        Livewire::test(StoryMaker::class)->set('paste', $answer)->call('check')->call('openReading')->assertSeeHtml('dir="rtl"')->assertSee('قُلْ هُوَ اللَّهُ أَحَدٌ');
    }

    public function test_a_new_answer_is_a_new_thing_to_read(): void
    {
        $this->superuser();
        $page = Livewire::test(StoryMaker::class)->set('paste', $this->answer(3))->call('check')->call('openReading')->assertSet('readOpened', true);

        $page->set('paste', $this->answer(4))->assertSet('readOpen', false)->assertSet('readOpened', false);
    }

    // ---------- the safety (off unless the super user switches it on) ----------

    public function test_without_the_safety_the_draft_can_be_saved_at_once(): void
    {
        $this->superuser();
        $this->saved(3);

        $this->assertSame(1, Story::count());
    }

    public function test_with_the_safety_the_draft_waits_until_the_story_was_opened(): void
    {
        $this->superuser();
        Rules::saveRequireRead(true);

        $page = Livewire::test(StoryMaker::class)->set('topic', 'The walk')->set('categoryId', $this->cat->id)->set('paste', $this->answer(3))->call('check');
        $page->call('saveStory');
        $this->assertSame(0, Story::count(), 'not read yet: nothing is saved');
        $page->assertSee('and read it first.');

        $page->call('openReading')->call('saveStory');
        $this->assertSame(1, Story::count());
    }

    public function test_the_super_user_switches_the_safety_on_and_off_on_the_screen(): void
    {
        $this->superuser();

        Livewire::test(StoryMaker::class)->assertSet('ruleRequireRead', false)->set('ruleRequireRead', true)->call('saveRules');
        $this->assertTrue(Rules::requireRead());

        Livewire::test(StoryMaker::class)->assertSet('ruleRequireRead', true)->call('resetRules');
        $this->assertFalse(Rules::requireRead());
    }

    // ---------- updating a draft that has pictures ----------

    public function test_a_note_says_so_when_an_update_changes_the_slides_of_a_draft_with_pictures(): void
    {
        $this->superuser();
        [$page] = $this->saved(3);
        $story = Story::first();

        // no picture yet: nothing to say
        $page->set('paste', $this->answer(4))->call('check');
        $this->assertNull($page->instance()->updateNote());

        StorySlide::query()->first()->update(['image' => 'stories/a.webp']);
        $note = $page->instance()->updateNote();
        $this->assertNotNull($note);
        $this->assertStringContainsString($story->code, $note);
        $this->assertStringContainsString('3 slides', $note);
        $this->assertStringContainsString('4 slides', $note);
        $page->call('check')->assertSee('The pictures you added stay in the same places');

        // the same shape: nothing to say
        $page->set('paste', $this->answer(3))->call('check');
        $this->assertNull($page->instance()->updateNote());
    }

    public function test_no_note_for_a_story_that_is_not_a_draft(): void
    {
        $this->superuser();
        [$page] = $this->saved(3);
        StorySlide::query()->first()->update(['image' => 'stories/a.webp']);
        Story::first()->update(['status' => 'published']);

        $page->set('paste', $this->answer(5))->call('check');
        $this->assertNull($page->instance()->updateNote());
    }

    // ---------- translations out of date ----------

    private function storyWithUrdu(): Story
    {
        $story = Story::create(['title' => ['en' => 'The Walk'], 'slug' => 'the-walk', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'A man walks.']]);
        $ch = $story->chapters()->create(['title' => ['en' => 'The opening'], 'sort_order' => 1]);
        $ch->slides()->create(['sort_order' => 1, 'heading' => ['en' => 'The hill'], 'body' => ['en' => 'A man walked to the hill.']]);
        $story->setTranslation('title', 'ur', 'چہل قدمی')->save();
        Translations::saved($story->fresh(), 'ur', null);
        Translations::set($story->fresh(), 'ur', 'on');

        return $story->fresh();
    }

    public function test_a_change_of_the_english_text_hides_the_languages_and_marks_them_out_of_date(): void
    {
        $story = $this->storyWithUrdu();
        $this->assertSame(['ur'], Translations::shown($story));

        StorySlide::first()->setTranslation('body', 'en', 'A man walked to the quiet hill.')->save();

        $row = Translations::all($story->fresh())['ur'];
        $this->assertTrue($row['outdated']);
        $this->assertSame('hidden', $row['status']);
        $this->assertSame([], Translations::shown($story->fresh()));
        $this->get('/ur/stories/the-walk')->assertRedirect($story->url());    // a visitor sees the English story
    }

    public function test_a_change_in_another_language_is_not_a_change_of_the_english_text(): void
    {
        $story = $this->storyWithUrdu();

        StorySlide::first()->setTranslation('body', 'ur', 'ایک آدمی پہاڑی پر گیا۔')->save();
        $story->setTranslation('title', 'ur', 'نئی چہل قدمی')->save();

        $this->assertFalse(Translations::all($story->fresh())['ur']['outdated']);
        $this->assertSame(['ur'], Translations::shown($story->fresh()));
    }

    public function test_a_new_or_a_removed_slide_also_marks_the_languages(): void
    {
        $story = $this->storyWithUrdu();
        $story->chapters->first()->slides()->create(['sort_order' => 2, 'heading' => ['en' => 'More'], 'body' => ['en' => 'More words.']]);
        $this->assertTrue(Translations::all($story->fresh())['ur']['outdated'], 'a new slide');

        Translations::saved($story->fresh(), 'ur', null);
        $this->assertFalse(Translations::all($story->fresh())['ur']['outdated'], 'made again: it is up to date');

        StorySlide::where('sort_order', 2)->first()->delete();
        $this->assertTrue(Translations::all($story->fresh())['ur']['outdated'], 'a removed slide');
    }

    public function test_an_out_of_date_language_cannot_be_shown_until_it_is_made_again(): void
    {
        $story = $this->storyWithUrdu();
        StorySlide::first()->setTranslation('body', 'en', 'Different words now.')->save();

        Translations::set($story->fresh(), 'ur', 'on');
        $this->assertSame([], Translations::shown($story->fresh()), 'refused');

        Translations::saved($story->fresh(), 'ur', null);          // made again, or read and saved again
        Translations::set($story->fresh(), 'ur', 'on');
        $this->assertSame(['ur'], Translations::shown($story->fresh()));
    }

    public function test_the_translations_screen_says_out_of_date_and_does_not_offer_to_show_it(): void
    {
        $this->superuser();
        $story = $this->storyWithUrdu();
        StorySlide::first()->setTranslation('body', 'en', 'Different words now.')->save();

        Livewire::test(StoryTranslations::class)->call('chooseStory', $story->id)
            ->assertSee('Out of date')->assertSee('It is hidden.')->assertDontSee('Show on the site')
            ->call('setStatus', 'ur', 'on');

        $this->assertSame([], Translations::shown($story->fresh()));
    }

    public function test_saving_a_changed_draft_from_the_story_maker_marks_its_languages(): void
    {
        $this->superuser();
        [$page] = $this->saved(3);
        $story = Story::first();
        $story->setTranslation('title', 'ur', 'چہل قدمی')->save();
        Translations::saved($story->fresh(), 'ur', null);

        $page->set('paste', $this->answer(3, 'The Walk Again'))->call('check')->call('saveStory');

        $this->assertTrue(Translations::all($story->fresh())['ur']['outdated']);
    }
}
