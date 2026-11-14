<?php

namespace Tests\Feature;

use App\Models\AiPrompt;
use App\Models\Article;
use App\Models\Category;
use App\Support\AiImport as Importer;
use App\Support\Html;
use App\Support\ReadingLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 39: verses and hadith in their own blocks (Quran blue, hadith green). */
class Step39Test extends TestCase
{
    use RefreshDatabase;

    private const AR = 'الَّذِي خَلَقَ سَبْعَ سَمَاوَاتٍ طِبَاقًا';

    private function marked(): string
    {
        return "## The heavens\nTwo verses say Allah (SWT) made seven heavens.\n\n[QURAN]\nARABIC: ".self::AR."\nTEXT: [And] who created seven heavens in layers.\nREF: Surah Al-Mulk 67:3 · Saheeh International (text as shown on Quran.com)\n[/QURAN]\n\nThe Prophet ﷺ taught this too.\n\n[HADITH]\nTEXT: Whoever believes in Allah.\nREF: Sahih al-Bukhari 6018 · Grade: Sahih · Translation: Sunnah.com\n[/HADITH]\n\nEnd words.";
    }

    public function test_the_master_prompt_teaches_the_block_shape(): void
    {
        $master = AiPrompt::where('slug', 'master')->value('body');

        foreach (['[QURAN]', '[/QURAN]', '[HADITH]', '[/HADITH]', 'ARABIC:', 'TEXT:', 'REF: Surah Al-Mulk 67:3', 'Never type Arabic from memory', 'Grade: Sahih'] as $needle) {
            $this->assertStringContainsString($needle, $master, $needle);
        }
    }

    public function test_blocks_become_safe_html_with_their_own_colour_class(): void
    {
        $html = Html::fromMarked($this->marked());

        $this->assertStringContainsString('<blockquote class="quote quote--quran"><p class="quote__ar" dir="rtl" lang="ar">'.self::AR.'</p>', $html);
        $this->assertStringContainsString('<p class="quote__text">[And] who created seven heavens in layers.</p>', $html);
        $this->assertStringContainsString('<cite class="quote__ref">Surah Al-Mulk 67:3', $html);
        $this->assertStringContainsString('<blockquote class="quote quote--hadith"><p class="quote__text">Whoever believes in Allah.</p>', $html);
        $this->assertStringNotContainsString('[QURAN]', $html);
        $this->assertStringContainsString('<p>Two verses say Allah (SWT) made seven heavens.</p>', $html);
    }

    public function test_the_block_survives_the_safe_filter_but_other_classes_and_attributes_do_not(): void
    {
        $clean = Html::clean(Html::fromMarked($this->marked()));
        $this->assertStringContainsString('class="quote quote--quran"', $clean);
        $this->assertStringContainsString('dir="rtl"', $clean);
        $this->assertStringContainsString('class="quote quote--hadith"', $clean);

        $evil = Html::clean('<blockquote class="quote evil-class" onclick="x()" style="color:red"><p class="quote__ar nope" dir="ltr" lang="fr">a</p></blockquote>');
        $this->assertStringContainsString('class="quote"', $evil);
        $this->assertStringNotContainsString('evil-class', $evil);
        $this->assertStringNotContainsString('onclick', $evil);
        $this->assertStringNotContainsString('style=', $evil);
        $this->assertStringNotContainsString('dir="ltr"', $evil);
        $this->assertStringNotContainsString('lang="fr"', $evil);
    }

    public function test_html_inside_a_block_is_shown_as_text(): void
    {
        $html = Html::fromMarked("[QURAN]\nTEXT: <script>x</script>\nREF: Surah 1:1\n[/QURAN]");

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_an_empty_block_is_dropped_and_an_unclosed_one_is_kept(): void
    {
        $this->assertSame('', Html::fromMarked("[QURAN]\nREF: Surah 1:1\n[/QURAN]"));
        $this->assertStringContainsString('quote--hadith', Html::fromMarked("[HADITH]\nTEXT: Words.\nREF: Bukhari 1"));
    }

    public function test_a_long_text_line_in_a_block_is_joined(): void
    {
        $b = Html::quoteBlocks("[QURAN]\nARABIC: abc\nTEXT: First part\nsecond part\nREF: Surah 2:255\n[/QURAN]");

        $this->assertSame('First part second part', $b[0]['text']);
    }

    public function test_the_reading_check_ignores_the_blocks(): void
    {
        $quote = str_repeat('And whoever does righteous deeds, whether male or female, while being a believer, those will enter Paradise and will not be wronged even as much as the speck on a date seed in any way at all. ', 3);
        $text = str_repeat("Allah (SWT) is one. He made everything. Muslims believe this. It is the base of Islam. It is simple.\n", 12)."[QURAN]\nTEXT: ".$quote."\nREF: Surah An-Nisa 4:124\n[/QURAN]\n";

        $this->assertFalse(ReadingLevel::measure($text)['hard']);
    }

    private function answer(string $long): string
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);

        return "TYPE: article\nCATEGORY: {$cat->code}\nTITLE: With verses\nSHORT: Short.\nLONG:\n".$long."\nSOURCE: A source\nEND";
    }

    public function test_a_complete_answer_has_no_block_warnings(): void
    {
        $item = Importer::parse($this->answer($this->marked()))[0];

        $this->assertSame([], $item['errors']);
        $this->assertCount(2, $item['data']['quotes']);
        $this->assertEmpty(array_filter($item['warnings'], fn ($w) => str_contains($w, 'block')), implode(' | ', $item['warnings']));
    }

    public function test_incomplete_blocks_are_flagged(): void
    {
        $long = "## Part\nWords.\n\n[QURAN]\nTEXT: A verse.\nREF: Surah Al-Mulk\n[/QURAN]\n\n[HADITH]\nTEXT: A saying.\nREF: Some book\n[/HADITH]\n\n[QURAN]\nARABIC: x\nTEXT: Another.\n[/QURAN]";

        $w = implode("\n", Importer::parse($this->answer($long))[0]['warnings']);

        $this->assertStringContainsString('no surah and verse number', $w);
        $this->assertStringContainsString('no Arabic text', $w);
        $this->assertStringContainsString('hadith reference has no number', $w);
        $this->assertStringContainsString('hadith reference has no grade', $w);
        $this->assertStringContainsString('block has no reference', $w);
    }

    public function test_a_wrong_verse_number_inside_a_block_is_still_caught(): void
    {
        $long = "## Part\nWords.\n\n[QURAN]\nARABIC: x\nTEXT: A verse.\nREF: Surah Al-Ikhlas 112:9\n[/QURAN]";

        $item = Importer::parse($this->answer($long))[0];

        $this->assertStringContainsString('112:9', implode(' ', $item['warnings']));
    }

    public function test_the_blocks_show_on_the_public_page_and_listen_skips_the_arabic(): void
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        Article::create(['title' => ['en' => 'T'], 'slug' => 't', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'long_detail' => ['en' => Html::fromMarked($this->marked())]]);

        $html = $this->get('/t')->assertOk()->getContent();

        $this->assertStringContainsString('class="quote quote--quran"', $html);
        $this->assertStringContainsString('class="quote quote--hadith"', $html);
        $this->assertStringContainsString('.quote__ar', (string) file_get_contents(public_path('js/listen.js')));
        $this->assertStringContainsString('.quote--hadith', (string) file_get_contents(public_path('css/site.css')));
    }
}
