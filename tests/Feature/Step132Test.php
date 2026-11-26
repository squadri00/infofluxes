<?php

namespace Tests\Feature;

use App\Filament\Pages\VerseHelper;
use App\Models\Category;
use App\Models\QuranTranslation;
use App\Models\QuranTranslationVerse;
use App\Models\QuranVerse;
use App\Models\Setting;
use App\Models\User;
use App\Support\AiImport;
use App\Support\Html;
use App\Support\Quran;
use App\Support\QuranBlocks;
use App\Support\QuranRefs;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 132: the Verse helper: the verified Quran text, the lookup page, the fill-in and check of the verse blocks in the AI import. */
class Step132Test extends TestCase
{
    use RefreshDatabase;

    private const AR_3035 = "أَمْ خُلِقُوا۟ مِنْ غَيْرِ شَىْءٍ أَمْ هُمُ ٱلْخَٰلِقُونَ";

    private const AR_3036 = "أَمْ خَلَقُوا۟ ٱلسَّمَٰوَٰتِ وَٱلْأَرْضَ ۚ بَل لَّا يُوقِنُونَ";

    private const AR_3030 = "فَأَقِمْ وَجْهَكَ لِلدِّينِ حَنِيفًا";

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Quran::forget();
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        Quran::forget();
        Setting::forget();
        parent::tearDown();
    }

    /** A few verses and a translation, enough for the tests. */
    private function text(bool $translation = true): void
    {
        foreach ([[52, 35, self::AR_3035], [52, 36, self::AR_3036], [30, 30, self::AR_3030]] as [$s, $a, $ar]) {
            QuranVerse::create(['surah' => $s, 'ayah' => $a, 'arabic' => $ar]);
        }
        if ($translation) {
            $t = QuranTranslation::create(['code' => 'sahih', 'name' => 'Saheeh International', 'is_default' => true]);
            foreach ([[52, 35, 'Or were they created by nothing, or were they the creators [of themselves]?'], [52, 36, 'Or did they create the heavens and the earth? Rather, they are not certain.'], [30, 30, 'So direct your face toward the religion, inclining to truth.']] as [$s, $a, $en]) {
                QuranTranslationVerse::create(['translation_id' => $t->id, 'surah' => $s, 'ayah' => $a, 'text' => $en]);
            }
        }
        Quran::forget();
    }

    private function user(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    // ---------- finding a verse ----------

    public function test_references_are_read_in_the_usual_ways_and_a_wrong_one_is_named(): void
    {
        $refs = Quran::parse("30:30\nSurah Ar-Rum 30:30, (2:255) and At-Tur 52:35–36, 52:35-36\n115:1; 2:300");

        $this->assertSame(['30:30', '2:255', '52:35-36', '115:1', '2:300'], array_column($refs, 'ref'));
        $this->assertSame([true, true, true, false, false], array_column($refs, 'valid'));
        $this->assertStringContainsString('only 286 verses', $refs[4]['problem']);
        $this->assertSame('Ar-Rum', Quran::surahName(30));
        $this->assertSame('An-Nas', Quran::surahName(114));
    }

    public function test_the_arabic_and_the_english_of_a_range_are_joined_and_a_missing_verse_gives_nothing(): void
    {
        $this->text();
        $t = Quran::translation();

        $this->assertSame(self::AR_3035.' '.self::AR_3036, Quran::arabic(52, 35, 36));
        $this->assertSame('Or were they created by nothing, or were they the creators [of themselves]? Or did they create the heavens and the earth? Rather, they are not certain.', Quran::english(52, 35, 36, $t));
        $this->assertNull(Quran::arabic(52, 35, 37), 'verse 37 is not imported');
        $this->assertNull(Quran::english(2, 1, 1, $t));
        $this->assertSame('Surah At-Tur 52:35-36 · Saheeh International · Arabic text: Tanzil.net', Quran::ref(52, 35, 36, $t));
    }

    public function test_the_block_has_the_arabic_the_english_and_the_reference(): void
    {
        $this->text();

        $block = Quran::block(['surah' => 30, 'from' => 30, 'to' => 30], Quran::translation());

        $this->assertSame("[QURAN]\nARABIC: ".self::AR_3030."\nTEXT: So direct your face toward the religion, inclining to truth.\nREF: Surah Ar-Rum 30:30 · Saheeh International · Arabic text: Tanzil.net\n[/QURAN]", $block);
        $this->assertNull(Quran::block(['surah' => 2, 'from' => 255, 'to' => 255], null));
    }

    public function test_arabic_is_compared_by_its_letters_and_english_by_its_words(): void
    {
        $this->assertSame('exact', Quran::compareArabic(self::AR_3030, self::AR_3030));
        $this->assertSame('marks', Quran::compareArabic('فاقم وجهك للدين حنيفا', self::AR_3030), 'the same letters without vowel marks');
        $this->assertSame('different', Quran::compareArabic('فَأَقِمْ وَجْهَكَ لِلَّهِ', self::AR_3030));

        $this->assertGreaterThanOrEqual(0.985, Quran::similarity('Or were they created by nothing, or were they the creators of themselves', 'Or were they created by nothing, or were they the creators [of themselves]?'));
        $this->assertLessThan(0.985, Quran::similarity('Or were they created by nothing, or were they themselves the creators?', 'Or were they created by nothing, or were they the creators [of themselves]?'));
    }

    // ---------- the check of the verse blocks ----------

    public function test_a_block_without_arabic_gets_the_verified_arabic_and_the_credit(): void
    {
        $this->text();
        $long = "A sentence.\n\n[QURAN]\nTEXT: So direct your face toward the religion, inclining to truth.\nREF: Surah Ar-Rum 30:30\n[/QURAN]\n\nAnother sentence.";

        $r = QuranBlocks::check($long);

        $this->assertStringContainsString('ARABIC: '.self::AR_3030, $r['long']);
        $this->assertStringContainsString('REF: Surah Ar-Rum 30:30 · Saheeh International · Arabic text: Tanzil.net', $r['long']);
        $this->assertStringContainsString("A sentence.\n\n[QURAN]", $r['long']);
        $this->assertStringContainsString("[/QURAN]\n\nAnother sentence.", $r['long']);
        $this->assertStringContainsString('filled in from the verified Quran text', implode(' ', $r['auto']));
        $this->assertSame([], $r['warnings']);
    }

    public function test_a_block_without_english_gets_the_translation(): void
    {
        $this->text();

        $r = QuranBlocks::check("[QURAN]\nARABIC: ".self::AR_3030."\nREF: Surah Ar-Rum 30:30\n[/QURAN]");

        $this->assertStringContainsString('TEXT: So direct your face toward the religion, inclining to truth.', $r['long']);
        $this->assertStringContainsString('filled in from Saheeh International', implode(' ', $r['auto']));
    }

    public function test_arabic_that_only_has_other_vowel_marks_is_replaced_but_different_arabic_is_only_a_warning(): void
    {
        $this->text();
        $marks = QuranBlocks::check("[QURAN]\nARABIC: فاقم وجهك للدين حنيفا\nTEXT: So direct your face toward the religion, inclining to truth.\nREF: Surah Ar-Rum 30:30\n[/QURAN]");
        $this->assertStringContainsString('ARABIC: '.self::AR_3030, $marks['long']);
        $this->assertStringContainsString('other vowel marks', implode(' ', $marks['auto']));

        $other = QuranBlocks::check("[QURAN]\nARABIC: فَأَقِمْ وَجْهَكَ لِلَّهِ\nTEXT: So direct your face toward the religion, inclining to truth.\nREF: Surah Ar-Rum 30:30\n[/QURAN]");
        $this->assertStringContainsString('ARABIC: فَأَقِمْ وَجْهَكَ لِلَّهِ', $other['long'], 'different Arabic is not changed');
        $this->assertStringContainsString('not the same as the verified Quran text', implode(' ', $other['warnings']));
    }

    public function test_english_that_is_the_translation_is_named_and_other_english_is_only_a_warning(): void
    {
        $this->text();
        $same = QuranBlocks::check("[QURAN]\nARABIC: ".self::AR_3030."\nTEXT: So direct your face toward the religion, inclining to truth.\nREF: Surah Ar-Rum 30:30\n[/QURAN]");
        $this->assertStringContainsString('REF: Surah Ar-Rum 30:30 · Saheeh International', $same['long']);
        $this->assertSame([], $same['warnings']);

        $own = QuranBlocks::check("[QURAN]\nARABIC: ".self::AR_3035."\nTEXT: Or were they created by nothing, or were they themselves the creators?\nREF: Surah At-Tur 52:35\n[/QURAN]");
        $this->assertStringContainsString('TEXT: Or were they created by nothing, or were they themselves the creators?', $own['long'], 'the contributor\'s English is not changed');
        $this->assertStringNotContainsString('Saheeh International', explode('REF:', $own['long'])[1]);
        $this->assertStringContainsString('is not the Saheeh International wording', implode(' ', $own['warnings']));
    }

    public function test_a_verse_named_in_the_text_without_a_block_is_listed(): void
    {
        $this->text();

        $r = QuranBlocks::check("Surah Ar-Rum (30:30) refers to the natural disposition.\n\n[QURAN]\nARABIC: ".self::AR_3035."\nTEXT: x\nREF: Surah At-Tur 52:35\n[/QURAN]\n\nSee also Quran 2:255.");

        $this->assertStringContainsString('30:30 is named in the text without its verse', implode(' ', $r['auto']));
        $this->assertStringContainsString('2:255 is named in the text without its verse', implode(' ', $r['auto']));
        $this->assertStringNotContainsString('52:35 is named', implode(' ', $r['auto']), 'a verse that has a block is not listed');
    }

    public function test_nothing_happens_when_the_quran_text_is_not_imported(): void
    {
        $long = "[QURAN]\nTEXT: x\nREF: Surah Ar-Rum 30:30\n[/QURAN]";

        $this->assertSame(['long' => $long, 'auto' => [], 'warnings' => []], QuranBlocks::check($long));
    }

    // ---------- the AI import and the page ----------

    public function test_the_ai_import_fills_the_verse_and_the_saved_article_shows_it_with_the_credit_link(): void
    {
        $this->text();
        Category::create(['name' => ['en' => 'History place'], 'slug' => 'history-place']);
        $answer = "TYPE: article\nCATEGORY: History place\nTITLE: A Verse Test\nSHORT: A short line.\nLONG:\n## The verse\nA sentence.\n\n[QURAN]\nTEXT: So direct your face toward the religion, inclining to truth.\nREF: Surah Ar-Rum 30:30\n[/QURAN]\n\nKEYWORDS: verse\nSOURCE: Quran 30:30\nEND\n";

        $items = AiImport::parse($answer);
        $this->assertSame([], $items[0]['errors'], json_encode($items[0]['errors']));
        $this->assertStringContainsString('ARABIC: '.self::AR_3030, $items[0]['data']['long']);
        $this->assertStringNotContainsString('A verse block has no Arabic text', implode(' ', $items[0]['warnings']));
        $this->assertStringContainsString('filled in from the verified Quran text', implode(' ', $items[0]['data']['please_check']));

        $html = Html::fromMarked($items[0]['data']['long']);
        $this->assertStringContainsString('<p class="quote__ar" dir="rtl" lang="ar">'.self::AR_3030.'</p>', $html);
        $this->assertStringContainsString('<a href="https://tanzil.net" target="_blank" rel="noopener">Tanzil.net</a>', $html);
    }

    public function test_the_helper_page_gives_the_verses_and_the_block(): void
    {
        $this->text();
        $this->user();

        $page = Livewire::test(VerseHelper::class)->assertSuccessful()->set('refs', "30:30\n52:35-36\n115:1");

        $page->assertSee(self::AR_3030)->assertSee('Surah At-Tur 52:35-36')->assertSee('Rather, they are not certain.')->assertSee('There is no surah 115')->assertSee('[QURAN]');
        $result = $page->instance()->result();
        $this->assertSame(2, substr_count($result['blocks'], '[QURAN]'), 'a block for each verse that exists');
        $this->assertSame(['sahih' => 'Saheeh International'], $page->instance()->translationChoices());
    }

    public function test_the_helper_page_says_when_the_text_is_not_imported_and_only_staff_can_open_it(): void
    {
        $this->user();
        Livewire::test(VerseHelper::class)->assertSee('The Quran text is not on this site yet');

        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active']));
        $this->assertFalse(VerseHelper::canAccess());
    }

    // ---------- the import command ----------

    public function test_the_import_command_checks_the_whole_file_and_stores_the_text_exactly(): void
    {
        $dir = sys_get_temp_dir().'/quran-test-'.uniqid();
        mkdir($dir);
        $lines = ['# Tanzil test notice', '#  keep this block'];
        foreach (QuranRefs::counts() as $i => $n) {
            for ($a = 1; $a <= $n; $a++) {
                $lines[] = ($i + 1).'|'.$a.'|'.($i + 1 === 30 && $a === 30 ? self::AR_3030 : 'verse '.($i + 1).' '.$a);
            }
        }
        file_put_contents($dir.'/ar.txt', implode("\n", $lines)."\n");

        $this->artisan('quran:import', ['--arabic' => $dir.'/ar.txt'])->expectsOutputToContain('Arabic text imported: 6236 verses')->assertSuccessful();
        $this->assertSame(self::AR_3030, Quran::arabic(30, 30, 30));
        $this->assertStringContainsString('Tanzil test notice', (string) Setting::get('quran_arabic_notice'));

        file_put_contents($dir.'/en.txt', implode("\n", array_map(fn ($l) => str_starts_with($l, '#') ? $l : preg_replace('/^(\d+\|\d+\|).*/', '${1}an english verse', $l), $lines))."\n");
        $this->artisan('quran:import', ['--translation' => $dir.'/en.txt', '--code' => 'test', '--name' => 'A Test Translation', '--default' => true])->assertSuccessful();
        $this->assertSame('An english verse', ucfirst(Quran::english(2, 1, 1, Quran::translation('test'))));
        $this->assertTrue(Quran::translation()->is_default);

        // A file that does not have all the verses is refused and nothing changes.
        file_put_contents($dir.'/short.txt', "1|1|a\n1|2|b\n");
        $this->artisan('quran:import', ['--arabic' => $dir.'/short.txt'])->expectsOutputToContain('Nothing was imported')->assertFailed();
        $this->assertSame(6236, QuranVerse::count());

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }
}
