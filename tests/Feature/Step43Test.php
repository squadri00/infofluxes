<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Support\AiImport as Importer;
use App\Support\Html;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 43: headings that follow a sentence directly, and verse blocks that an editor stripped of their classes. */
class Step43Test extends TestCase
{
    use RefreshDatabase;

    public function test_a_heading_straight_after_a_sentence_is_found(): void
    {
        $text = "What Is It?\nThe first part has words that are longer than its heading.\n\nHere is a sentence that ends the part.\nWhat the Quran Says\nThe next part also has longer words than the heading.\nHow Scholars Explain It\nThe last part has words, too, and they are longer.";

        [$out, $n] = Importer::guessHeadings($text);

        $this->assertSame(3, $n);
        $this->assertStringContainsString("## What the Quran Says\n", $out);
        $this->assertStringContainsString("## How Scholars Explain It\n", $out);
    }

    public function test_a_line_after_a_heading_is_not_a_second_heading_and_a_list_is_not_a_heading(): void
    {
        [$out, $n] = Importer::guessHeadings("A Heading\nAnother short line\nThe words of the part are long enough to follow a heading.\n- list line\nA closing sentence.");

        $this->assertSame(1, $n, 'only the first line is a heading');
        $this->assertStringNotContainsString('## Another short line', $out);
    }

    public function test_a_plain_quote_with_the_shape_of_a_verse_gets_its_classes_back(): void
    {
        $stripped = '<p>Words before.</p><blockquote><p>وَلَقَدْ بَعَثْنَا فِى كُلِّ أُمَّةٍۢ رَّسُولًا</p><p>And We certainly sent into every nation a messenger.</p>Surah An-Nahl 16:36 · Saheeh International</blockquote><p>Words after.</p>';

        $html = Html::clean($stripped);

        $this->assertStringContainsString('<blockquote class="quote quote--quran">', $html);
        $this->assertStringContainsString('<p class="quote__ar" dir="rtl" lang="ar">وَلَقَدْ', $html);
        $this->assertStringContainsString('<p class="quote__text">And We certainly sent into every nation a messenger.</p>', $html);
        $this->assertStringContainsString('<cite class="quote__ref">Surah An-Nahl 16:36 · Saheeh International</cite>', $html);
        $this->assertStringContainsString('<p>Words before.</p>', $html);
    }

    public function test_a_plain_hadith_quote_becomes_a_green_block(): void
    {
        $stripped = '<blockquote><p>Narrated Mu`adh: The Prophet said, "Worship Allah alone."</p><p>Sahih al-Bukhari 7373 · Grade: Sahih</p></blockquote>';

        $html = Html::clean($stripped);

        $this->assertStringContainsString('quote quote--hadith', $html);
        $this->assertStringContainsString('<p class="quote__text">Narrated Mu`adh', $html);
        $this->assertStringNotContainsString('quote__ar', $html, 'no Arabic line was there');
        $this->assertStringContainsString('<cite class="quote__ref">Sahih al-Bukhari 7373', $html);
    }

    public function test_an_ordinary_quote_is_left_alone(): void
    {
        $html = Html::clean('<blockquote><p>An ordinary quotation from a book.</p><p>Someone Famous, 1999</p></blockquote>');

        $this->assertSame('<blockquote><p>An ordinary quotation from a book.</p><p>Someone Famous, 1999</p></blockquote>', $html);
    }

    public function test_a_block_that_still_has_its_classes_is_not_changed(): void
    {
        $good = Html::fromMarked("[QURAN]\nARABIC: قُلْ هُوَ ٱللَّهُ أَحَدٌ\nTEXT: Say, He is Allah, One.\nREF: Surah Al-Ikhlas 112:1 · Saheeh International\n[/QURAN]");

        $this->assertSame($good, Html::clean($good));
    }

    public function test_the_public_page_shows_the_style_even_after_the_editor_stripped_it(): void
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        Article::create(['title' => ['en' => 'T'], 'slug' => 't', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'long_detail' => ['en' => '<blockquote><p>إِيَّاكَ نَعْبُدُ وَإِيَّاكَ نَسْتَعِينُ</p><p>It is You we worship and You we ask for help.</p>Surah Al-Fatihah 1:5 · Saheeh International</blockquote>']]);

        $this->get('/t')->assertOk()->assertSee('quote quote--quran', false)->assertSee('quote__ar', false);
    }

    public function test_the_pasted_answer_of_the_real_trial_is_read_with_four_headings(): void
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        $long = "What Is Tawhid al-Uluhiyyah?\nTawhid al-Uluhiyyah means believing that Allah (SWT) alone deserves worship.\n\nThe Quran makes this clear.\n\n[QURAN]\nARABIC: x\nTEXT: A verse.\nREF: Surah An-Nahl 16:36 · Saheeh International\n[/QURAN]\n\nHere, Taghut means anything falsely worshipped as a power against guidance.\nWhat the Quran Says About Worship\nThe Quran connects the oneness of Allah with the purpose of sending prophets.\n\nThis verse expresses a direct relationship with Allah (SWT).\nHow Sunni Scholars Explain This Concept\nSunni scholars commonly explain Tawhid through three categories:\n- One\n- Two\nThis is a framework of scholars.\n\nAsh'ari scholars differ in methods.\nWhy Tawhid al-Uluhiyyah Matters\nThis concept makes worship the centre of a relationship.";

        $item = Importer::parse("TYPE: article\nCATEGORY: {$cat->code}\nTITLE: Real trial\nSHORT: S\nLONG:\n{$long}\nEND")[0];

        $this->assertSame(['What Is Tawhid al-Uluhiyyah?', 'What the Quran Says About Worship', 'How Sunni Scholars Explain This Concept', 'Why Tawhid al-Uluhiyyah Matters'], array_column($item['data']['outline'], 'title'));
        $this->assertSame([], $item['errors']);
    }
}
