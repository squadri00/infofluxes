<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryTranslations;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Models\StoryRun;
use App\Models\User;
use App\Support\StoryMaker\Rules;
use App\Support\StoryMaker\TranslationPrompt;
use App\Support\StoryMaker\TranslationReader;
use App\Support\StoryMaker\TranslationSaver;
use App\Support\StoryMaker\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 74: the Story maker, step 2 (translating a saved story): prompt, check language by language, save hidden, runs, rules. */
class Step74Test extends TestCase
{
    use RefreshDatabase;

    private const VERSE = 'قُلْ هُوَ اللَّهُ أَحَدٌ';

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    /** A saved English story: 2 chapters, 3 slides, a verse in the second slide. */
    private ?int $madeStory = null;

    private function story(): Story
    {
        if ($this->madeStory) {
            return Story::find($this->madeStory);   // one story for the whole test
        }
        $cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
        $story = Story::create(['title' => ['en' => 'The Walk'], 'slug' => 'the-walk', 'category_id' => $cat->id, 'status' => 'draft', 'short_detail' => ['en' => 'A man walks to a hill.'],
            'meta_title' => ['en' => 'The walk'], 'meta_description' => ['en' => 'A short story about a walk.'], 'search_words' => ['walk', 'hill']]);
        $one = $story->chapters()->create(['title' => ['en' => 'The opening'], 'sort_order' => 1]);
        $one->slides()->create(['sort_order' => 1, 'heading' => ['en' => 'The hill'], 'body' => ['en' => 'A man walked to the hill. Prophet Muhammad ﷺ liked quiet places. The day was calm.'], 'image_alt' => ['en' => 'A hill'], 'custom_data' => ['picture' => 'A hill at dawn']]);
        $one->slides()->create(['sort_order' => 2, 'heading' => ['en' => 'The words'], 'body' => ['en' => "He remembered a verse.\n".self::VERSE."\nSay, He is Allah, the One.\n(Surah Al-Ikhlas 112:1)\nHe felt peace."], 'image_alt' => ['en' => 'A quiet man']]);
        $two = $story->chapters()->create(['title' => ['en' => 'Closing and final remarks'], 'sort_order' => 2]);
        $two->slides()->create(['sort_order' => 1, 'heading' => ['en' => 'The end'], 'body' => ['en' => 'The man went home. He was glad. The night was quiet.'], 'image_alt' => ['en' => 'A house']]);
        $this->madeStory = $story->id;

        return $story->fresh();
    }

    /** What a good answer for Urdu looks like (the Arabic line and the verse number kept). */
    private function urdu(array $over = []): string
    {
        return ($over['head'] ?? "LANG: ur\n")
            ."TITLE: چہل قدمی\nSHORT: ایک آدمی پہاڑی کی طرف جاتا ہے۔\n"
            ."CHAPTER: آغاز\n"
            ."SLIDE 1\nHEADING: پہاڑی\nBODY: ایک آدمی پہاڑی کی طرف گیا۔ حضرت محمد ﷺ پرسکون جگہوں کو پسند کرتے تھے۔ دن خاموش تھا۔\nALT: ایک پہاڑی\n"
            ."SLIDE 2\nHEADING: الفاظ\nBODY: اسے ایک آیت یاد آئی۔\n".($over['verse'] ?? self::VERSE)."\nکہہ دو، وہ اللہ ایک ہے۔\n(سورۃ الاخلاص 112:1)\nاسے سکون ملا۔\nALT: ایک پرسکون آدمی\n"
            ."CHAPTER: اختتام اور آخری باتیں\n"
            ."SLIDE 3\nHEADING: اختتام\nBODY: آدمی گھر گیا۔ وہ خوش تھا۔ رات خاموش تھی۔\nALT: ایک گھر\n"
            ."KEYWORDS: چہل قدمی، پہاڑی\nMETA TITLE: چہل قدمی\nMETA DESCRIPTION: چہل قدمی کے بارے میں ایک مختصر کہانی۔\n";
    }

    private function french(): string
    {
        return "LANG: fr\nTITLE: La marche\nSHORT: Un homme marche vers une colline.\n"
            ."CHAPTER: Le début\n"
            ."SLIDE 1\nHEADING: La colline\nBODY: Un homme marcha vers la colline. Le Prophète Muhammad ﷺ aimait les lieux calmes. Le jour était paisible.\nALT: Une colline\n"
            ."SLIDE 2\nHEADING: Les mots\nBODY: Il se souvint d'un verset.\n".self::VERSE."\nDis : Il est Allah, Unique.\n(Sourate Al-Ikhlas 112:1)\nIl ressentit la paix.\nALT: Un homme calme\n"
            ."CHAPTER: Conclusion et dernières remarques\n"
            ."SLIDE 3\nHEADING: La fin\nBODY: L'homme rentra chez lui. Il était heureux. La nuit était calme.\nALT: Une maison\n"
            ."KEYWORDS: marche, colline\nMETA TITLE: La marche\nMETA DESCRIPTION: Une courte histoire sur une marche.\n";
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    // ---------- rules and word lists ----------

    public function test_the_five_languages_have_drafts_of_their_word_lists_and_quran_translations(): void
    {
        $l = Rules::languages();

        $this->assertSame(['ur', 'ar', 'fr', 'es', 'hi'], array_keys($l));
        $this->assertTrue($l['ur']['rtl']);
        $this->assertTrue($l['ar']['rtl']);
        $this->assertFalse($l['fr']['rtl']);
        $this->assertSame('', $l['ar']['quran'], 'Arabic has no translation line');
        $this->assertNotSame('', $l['ur']['quran']);
        $this->assertStringContainsString('ﷺ', $l['ur']['words']);
        $this->assertStringContainsString('(SWT)', $l['fr']['words']);
    }

    public function test_the_word_lists_and_the_rules_can_be_changed_and_reset(): void
    {
        Rules::saveLanguage('fr', 'Hamidullah (changed)', "Allah (SWT) → Allah (SWT) (changed)");
        Rules::saveTranslation("My translation rules");
        $this->assertSame('Hamidullah (changed)', Rules::languages()['fr']['quran']);
        $this->assertStringContainsString('(changed)', Rules::languages()['fr']['words']);
        $this->assertSame('My translation rules', Rules::translation());
        $this->assertSame('Muhammad Hamidullah', Rules::languageDefaults()['fr']['quran'], 'the draft stays');

        Rules::resetLanguage('fr');
        Rules::resetTranslation();
        $this->assertSame('Muhammad Hamidullah', Rules::languages()['fr']['quran']);
        $this->assertFalse(Rules::translationChanged());

        Rules::saveLanguage('fr', 'Muhammad Hamidullah', Rules::languageDefaults()['fr']['words']);
        $this->assertNull(Setting::get(Rules::LANGUAGES_KEY), 'a language at its draft is not stored');
    }

    // ---------- the prompt ----------

    public function test_the_prompt_has_the_rules_the_english_story_the_counts_and_a_block_for_each_language(): void
    {
        $text = TranslationPrompt::build($this->story(), ['ur', 'ar']);

        foreach (['RULES FOR TRANSLATING AN INFOFLUXES STORY', 'TITLE: The Walk', 'CHAPTER: The opening', 'SLIDE 2', 'BODY: He remembered a verse.', self::VERSE, 'It has 2 chapter(s) and 3 slide(s)', 'LANGUAGE: Urdu (ur)', 'written from right to left', 'Fateh Muhammad Jalandhry', 'LANGUAGE: Arabic (ar)', 'No translation line for a Quran verse', 'LANG: ur', 'LANG: ar', 'END'] as $needle) {
            $this->assertStringContainsString($needle, $text, $needle);
        }
        $this->assertStringNotContainsString('LANG: fr', $text, 'only the languages asked for');
        $this->assertStringNotContainsString('PICTURE', $text, 'the picture lines are not given');
        $this->assertStringNotContainsString('A hill at dawn', $text);
    }

    public function test_an_unknown_language_is_left_out_and_no_language_has_a_hint(): void
    {
        $this->assertStringNotContainsString('LANG: xx', TranslationPrompt::build($this->story(), ['xx', 'fr']));
        $this->assertStringContainsString('(choose at least one language)', TranslationPrompt::build($this->story(), []));
    }

    // ---------- the check ----------

    public function test_good_answers_for_two_languages_have_no_problems(): void
    {
        $r = TranslationReader::read("```\n".$this->urdu().$this->french()."END\n```", $this->story(), ['ur', 'fr']);

        $this->assertSame([], $r['languages']['ur']['errors']);
        $this->assertSame([], $r['languages']['fr']['errors']);
        $this->assertSame([], $r['languages']['ur']['warnings']);
        $this->assertSame('چہل قدمی', $r['languages']['ur']['data']['title']);
        $this->assertSame(2, count($r['languages']['ur']['data']['chapters']));
        $this->assertSame(['چہل قدمی', 'پہاڑی'], $r['languages']['ur']['data']['keywords']);
        $this->assertSame([], $r['missing']);
    }

    public function test_a_language_may_be_named_by_its_name_and_a_language_not_asked_for_is_left_out(): void
    {
        $r = TranslationReader::read(str_replace('LANG: fr', 'LANGUAGE: French', $this->french()).$this->urdu(), $this->story(), ['fr']);

        $this->assertArrayHasKey('fr', $r['languages']);
        $this->assertArrayNotHasKey('ur', $r['languages']);
        $this->assertStringContainsString('Urdu was not asked for', implode(' ', $r['notes']));
    }

    public function test_a_missing_language_is_noted(): void
    {
        $r = TranslationReader::read($this->urdu(), $this->story(), ['ur', 'fr', 'hi']);
        $this->assertSame(['fr', 'hi'], $r['missing']);
        $this->assertStringContainsString('French is not in the answer', implode(' ', $r['notes']));
    }

    public function test_the_structure_must_be_the_same_as_the_english(): void
    {
        $fewer = preg_replace('/SLIDE 3.*?ALT: ایک گھر\n/su', '', $this->urdu());
        $r = TranslationReader::read($fewer, $this->story(), ['ur']);
        $this->assertStringContainsString('slide(s)', implode(' ', $r['languages']['ur']['errors']));
        $this->assertNotEmpty($r['languages']['ur']['errors']);

        $oneChapter = str_replace("CHAPTER: اختتام اور آخری باتیں\n", '', $this->urdu());
        $this->assertStringContainsString('chapter(s)', implode(' ', TranslationReader::read($oneChapter, $this->story(), ['ur'])['languages']['ur']['errors']));
    }

    public function test_a_changed_or_missing_arabic_quotation_is_a_problem_and_the_translation_is_not_saved(): void
    {
        $changed = $this->urdu(['verse' => 'قُلْ هُوَ اللَّهُ أَحَدٌ صَمَدٌ']);
        $r = TranslationReader::read($changed, $this->story(), ['ur']);
        $this->assertStringContainsString('was changed or left out', implode(' ', $r['languages']['ur']['errors']));

        $dropped = TranslationReader::read(str_replace(self::VERSE."\n", '', $this->urdu()), $this->story(), ['ur']);
        $this->assertStringContainsString('A quotation is never changed', implode(' ', $dropped['languages']['ur']['errors']));
    }

    public function test_a_different_verse_number_or_a_lost_salutation_symbol_is_noticed(): void
    {
        $r = TranslationReader::read(str_replace('112:1', '112:9', $this->urdu()), $this->story(), ['ur']);
        $this->assertStringContainsString('verse reference', implode(' ', $r['languages']['ur']['warnings']));

        $r2 = TranslationReader::read(str_replace('حضرت محمد ﷺ', 'حضرت محمد', $this->urdu()), $this->story(), ['ur']);
        $this->assertStringContainsString('ﷺ', implode(' ', $r2['languages']['ur']['warnings']));
    }

    public function test_untranslated_text_and_a_wrong_length_are_noticed(): void
    {
        $english = "LANG: ur\nTITLE: The Walk\nSHORT: A man walks to a hill.\nCHAPTER: The opening\nSLIDE 1\nHEADING: The hill\nBODY: A man walked to the hill. He sat down and looked at the town below him.\nALT: A hill\n"
            ."SLIDE 2\nHEADING: The words\nBODY: He remembered a verse.\n".self::VERSE."\n(Surah Al-Ikhlas 112:1)\nHe felt peace.\nALT: A man\nCHAPTER: End\nSLIDE 3\nHEADING: End\nBODY: The man went home.\nALT: A house\n";
        $r = TranslationReader::read($english, $this->story(), ['ur']);
        $this->assertStringContainsString('still in English', implode(' ', $r['languages']['ur']['warnings']));

        $same = TranslationReader::read(str_replace(['LANG: fr'], ['LANG: fr'], preg_replace('/^LANG: fr/m', 'LANG: fr', $this->frenchLikeEnglish())), $this->story(), ['fr']);
        $this->assertStringContainsString('not translated', implode(' ', $same['languages']['fr']['warnings']));
    }

    /** French block where slide 1 is the English text itself. */
    private function frenchLikeEnglish(): string
    {
        return str_replace('Un homme marcha vers la colline. Le Prophète Muhammad ﷺ aimait les lieux calmes. Le jour était paisible.', 'A man walked to the hill. Prophet Muhammad ﷺ liked quiet places. The day was calm.', $this->french());
    }

    public function test_web_addresses_and_missing_parts_are_noticed(): void
    {
        $r = TranslationReader::read(str_replace('ایک پہاڑی\n', "ایک پہاڑی https://example.org\n", $this->urdu()), $this->story(), ['ur']);
        $this->assertSame([], $r['languages']['ur']['errors']);

        $noTitle = TranslationReader::read(str_replace("TITLE: چہل قدمی\n", '', $this->urdu()), $this->story(), ['ur']);
        $this->assertStringContainsString('TITLE is missing', implode(' ', $noTitle['languages']['ur']['errors']));

        $noBody = TranslationReader::read(preg_replace('/BODY: آدمی گھر گیا۔ وہ خوش تھا۔ رات خاموش تھی۔\n/u', '', $this->urdu()), $this->story(), ['ur']);
        $this->assertStringContainsString('has no BODY', implode(' ', $noBody['languages']['ur']['errors']));
    }

    // ---------- saving ----------

    public function test_a_language_is_saved_into_its_own_boxes_english_is_untouched_and_it_is_hidden(): void
    {
        $story = $this->story();
        $r = TranslationReader::read($this->urdu(), $story, ['ur']);
        TranslationSaver::save($story, 'ur', $r['languages']['ur']['data'], 7);

        $story->refresh()->load('chapters.slides');
        $this->assertSame('چہل قدمی', $story->getTranslation('title', 'ur'));
        $this->assertSame('The Walk', $story->getTranslation('title', 'en'));
        $this->assertSame('ایک آدمی پہاڑی کی طرف جاتا ہے۔', $story->getTranslation('short_detail', 'ur'));
        $this->assertSame('چہل قدمی', $story->getTranslation('meta_title', 'ur'));
        $this->assertSame('آغاز', $story->chapters[0]->getTranslation('title', 'ur'));
        $this->assertSame('The opening', $story->chapters[0]->getTranslation('title', 'en'));
        $slide = $story->chapters[0]->slides[1];
        $this->assertSame('الفاظ', $slide->getTranslation('heading', 'ur'));
        $this->assertStringContainsString(self::VERSE, $slide->getTranslation('body', 'ur'));
        $this->assertStringContainsString(self::VERSE, $slide->getTranslation('body', 'en'));
        $this->assertSame('ایک پرسکون آدمی', $slide->getTranslation('image_alt', 'ur'));

        $this->assertSame('hidden', Translations::status($story, 'ur'));
        $this->assertSame(7, Translations::all($story)['ur']['run']);
        $this->assertSame([], Translations::shown($story));
    }

    public function test_switching_a_language_on_and_saving_it_again_keeps_it_on(): void
    {
        $story = $this->story();
        $data = TranslationReader::read($this->urdu(), $story, ['ur'])['languages']['ur']['data'];
        TranslationSaver::save($story, 'ur', $data);

        Translations::set($story->fresh(), 'ur', 'on');
        $this->assertSame(['ur'], Translations::shown($story->fresh()));

        TranslationSaver::save($story->fresh(), 'ur', $data);
        $this->assertSame('on', Translations::status($story->fresh(), 'ur'), 'a language that is shown stays shown');

        Translations::set($story->fresh(), 'ur', 'hidden');
        $this->assertSame([], Translations::shown($story->fresh()));
    }

    public function test_english_and_unknown_languages_cannot_be_saved_here(): void
    {
        $story = $this->story();
        $data = TranslationReader::read($this->urdu(), $story, ['ur'])['languages']['ur']['data'];
        $this->expectException(\InvalidArgumentException::class);
        TranslationSaver::save($story, 'en', $data);
    }

    // ---------- the screen ----------

    public function test_who_may_open_the_screen(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(StoryTranslations::canAccess());

        $this->superuser();
        $this->assertTrue(StoryTranslations::canAccess());
        $this->get('/admin/story-translations')->assertOk()->assertSee('Story translations')->assertSee('Saved translation runs');
    }

    public function test_the_whole_flow_choose_prompt_paste_check_save_and_the_run_is_kept(): void
    {
        $this->superuser();
        $story = $this->story();
        $page = Livewire::test(StoryTranslations::class)->call('chooseStory', $story->id);

        $page->assertSet('storyId', $story->id)->assertSet('codes', ['ur', 'ar', 'fr', 'es', 'hi']);
        $page->set('codes', ['ur', 'fr'])->assertSee('LANG: ur')->assertDontSee('LANG: hi');

        $page->set('paste', $this->urdu().$this->french())->call('check')->assertSet('checked', true);
        $run = StoryRun::first();
        $this->assertSame('translation', $run->kind);
        $this->assertSame($story->id, $run->story_id);
        $this->assertSame(['ur', 'fr'], $run->options['languages']);
        $this->assertSame('checked', $run->status);
        $this->assertStringContainsString('LANG: ur', $run->prompt);

        $page->call('saveLanguages')->assertNotified();
        $story->refresh();
        $this->assertSame('La marche', $story->getTranslation('title', 'fr'));
        $this->assertSame('چہل قدمی', $story->getTranslation('title', 'ur'));
        $this->assertSame(['ur', 'fr'], array_keys(Translations::all($story)));
        $this->assertSame('saved', $run->fresh()->status);
        $this->assertSame(['ur', 'fr'], $page->get('done')['saved']);
    }

    public function test_a_language_with_a_problem_is_left_out_and_the_good_one_is_saved(): void
    {
        $this->superuser();
        $story = $this->story();
        $bad = $this->urdu(['verse' => 'نص مختلف']);   // the verse was changed

        $page = Livewire::test(StoryTranslations::class)->call('chooseStory', $story->id)->set('codes', ['ur', 'fr'])->set('paste', $bad.$this->french())->call('saveLanguages');

        $story->refresh();
        $this->assertSame(['fr'], array_keys(Translations::all($story)));
        $this->assertNull($story->getTranslation('title', 'ur', false) ?: null);
        $this->assertArrayHasKey('ur', $page->get('done')['skipped']);
    }

    public function test_nothing_is_saved_when_every_language_has_a_problem(): void
    {
        $this->superuser();
        $story = $this->story();
        Livewire::test(StoryTranslations::class)->call('chooseStory', $story->id)->set('codes', ['ur'])->set('paste', $this->urdu(['verse' => 'x']))->call('saveLanguages');
        $this->assertSame([], Translations::all($story->fresh()));
    }

    public function test_a_language_can_be_shown_and_hidden_from_the_screen_and_the_languages_not_done_are_asked_for_first(): void
    {
        $this->superuser();
        $story = $this->story();
        TranslationSaver::save($story, 'fr', TranslationReader::read($this->french(), $story, ['fr'])['languages']['fr']['data']);

        $page = Livewire::test(StoryTranslations::class)->call('chooseStory', $story->id);
        $page->assertSet('codes', ['ur', 'ar', 'es', 'hi'])->assertSee('Saved, hidden');
        $page->call('setStatus', 'fr', 'on')->assertSee('Shown on the site');
        $this->assertSame(['fr'], Translations::shown($story->fresh()));
        $page->call('setStatus', 'fr', 'hidden');
        $this->assertSame([], Translations::shown($story->fresh()));
    }

    public function test_a_run_can_be_saved_before_the_answer_opened_again_and_deleted(): void
    {
        $this->superuser();
        $story = $this->story();
        $page = Livewire::test(StoryTranslations::class)->call('chooseStory', $story->id)->set('codes', ['hi', 'es'])->call('saveRun');
        $run = StoryRun::first();
        $this->assertSame('prompt', $run->status);
        $this->assertSame(['es', 'hi'], $run->options['languages']);

        $fresh = Livewire::test(StoryTranslations::class)->call('openRun', $run->id);
        $fresh->assertSet('storyId', $story->id)->assertSet('codes', ['es', 'hi'])->assertSet('runId', $run->id);
        $fresh->call('deleteRun', $run->id)->assertSet('runId', null);
        $this->assertSame(0, StoryRun::count());
    }

    public function test_only_stories_with_slides_can_be_chosen_and_the_story_can_be_found_by_code_or_title(): void
    {
        $this->superuser();
        $story = $this->story();
        $cat = Category::first();
        $empty = Story::create(['title' => ['en' => 'Empty one'], 'slug' => 'empty-one', 'category_id' => $cat->id, 'status' => 'draft']);

        $page = Livewire::test(StoryTranslations::class)->call('chooseStory', $empty->id)->assertSet('storyId', null);
        $ids = $page->set('storyQuery', 'S-'.$story->id)->instance()->storyResults()->pluck('id')->all();
        $this->assertSame([$story->id], $ids);
        $this->assertSame([$story->id], $page->set('storyQuery', 'The Walk')->instance()->storyResults()->pluck('id')->all());
    }

    public function test_the_rules_can_be_changed_by_the_super_user_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active', 'admin_screens' => ['storymaker']]));
        Livewire::test(StoryTranslations::class)->set('ruleText', 'Hacked')->call('saveRules')->assertForbidden();
        $this->assertFalse(Rules::translationChanged());

        $this->superuser();
        $page = Livewire::test(StoryTranslations::class)->set('ruleText', 'My rules')->set('langEdits.fr.quran', 'Another translation')->call('saveRules');
        $this->assertSame('My rules', Rules::translation());
        $this->assertSame('Another translation', Rules::languages()['fr']['quran']);
        $page->call('resetRules');
        $this->assertFalse(Rules::translationChanged());
        $this->assertSame('Muhammad Hamidullah', Rules::languages()['fr']['quran']);
    }

    public function test_the_translation_tool_stays_separate_from_the_article_tools(): void
    {
        $code = file_get_contents(app_path('Filament/Pages/StoryTranslations.php'));
        foreach (glob(app_path('Support/StoryMaker/Translation*.php')) as $file) {
            $code .= file_get_contents($file);
        }
        $this->assertStringNotContainsString('AiImport', $code);
        $this->assertStringNotContainsString('AiPrompt', $code);
    }

    public function test_the_salutation_symbol_alone_does_not_make_a_line_arabic(): void
    {
        $this->assertFalse(\App\Support\StoryMaker\Arabic::isLine('Prophet Muhammad ﷺ liked quiet places.'));
        $this->assertTrue(\App\Support\StoryMaker\Arabic::isLine(self::VERSE));
        $this->assertTrue(\App\Support\StoryMaker\Arabic::isLine('قُلْ هُوَ اللَّهُ أَحَدٌ ﷺ'));
        $this->assertFalse(\App\Support\StoryMaker\Arabic::isLine('The word الله means God in Arabic.'), 'one Arabic word inside an English sentence is not a quotation line');

        // The English check of step 1 counts such a slide as told words, not as a verse.
        $answer = "TYPE: story\nTITLE: Salute\nSHORT: s\nCHAPTER: One\nSLIDE\nHEADING: H\nBODY: Prophet Muhammad ﷺ walked to the hill. He sat down. He looked at the town and he felt calm and glad.\nPICTURE: p\nALT: a\nEND";
        $r = \App\Support\StoryMaker\Reader::read($answer);
        $this->assertSame([], array_filter($r['check'], fn ($c) => str_contains($c, 'has Arabic text')));
    }

    public function test_the_story_maker_links_to_the_translation_step_after_saving(): void
    {
        $this->superuser();
        $cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
        $answer = "TYPE: story\nTITLE: A linked story\nSHORT: s\nCHAPTER: One\nSLIDE\nHEADING: H\nBODY: A man walked to the hill. He sat down. He looked at the town and he felt calm and glad.\nPICTURE: p\nALT: a\nCHAPTER: Closing and final remarks\nSLIDE\nHEADING: End\nBODY: The man went home. He was glad. The night was quiet and still.\nPICTURE: p\nALT: a\nEND";
        \Livewire\Livewire::test(\App\Filament\Pages\StoryMaker::class)->set('topic', 'x')->set('categoryId', $cat->id)->set('slides', 2)->set('paste', $answer)->call('saveStory')
            ->assertSee('Translate it (step 2)')->assertSee('admin/story-translations?story=', false);
    }
}
