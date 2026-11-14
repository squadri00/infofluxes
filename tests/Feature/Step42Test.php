<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\AiImport as Importer;
use App\Support\Prompts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 42: Check forgives what a chat window does to an answer (lost "##" marks, a made-up ALSO IN). */
class Step42Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = Category::create(['name' => ['en' => 'Allah Index'], 'slug' => 'allah-index']);
        Category::create(['name' => ['en' => 'Worship'], 'slug' => 'worship']);
    }

    private function answer(string $alsoIn, string $long): string
    {
        return "TYPE: article\nCATEGORY: {$this->cat->code}\nALSO IN: {$alsoIn}\nTITLE: Plain copy\nSHORT: Short words.\n\nLONG:\n{$long}\nSOURCE: A source\nEND";
    }

    public function test_an_also_in_that_does_not_fit_is_left_out_with_a_note_and_nothing_is_blocked(): void
    {
        $item = Importer::parse($this->answer('Islamic Beliefs, Tawhid, Worship in Islam, Articles of Faith', "## One\nWords here."))[0];

        $this->assertSame([], $item['errors']);
        $this->assertSame([], $item['data']['extra_ids']);
        $this->assertStringContainsString('ALSO IN', implode(' ', $item['warnings']));
        $this->assertStringContainsString('was left out', implode(' ', $item['warnings']));
    }

    public function test_names_with_commas_are_split_and_the_ones_that_fit_are_kept(): void
    {
        $worship = Category::where('slug', 'worship')->first();

        $item = Importer::parse($this->answer('Worship, Made Up Name', "## One\nWords here."))[0];

        $this->assertSame([$worship->id], $item['data']['extra_ids']);
        $this->assertStringContainsString('Made Up Name', implode(' ', $item['warnings']));
        $this->assertSame([], $item['errors']);
    }

    public function test_semicolons_and_codes_still_work(): void
    {
        $worship = Category::where('slug', 'worship')->first();

        $item = Importer::parse($this->answer($worship->code.'; Nothing Like This', "## One\nWords here."))[0];

        $this->assertSame([$worship->id], $item['data']['extra_ids']);
    }

    public function test_the_main_category_still_must_fit(): void
    {
        $item = Importer::parse("TYPE: article\nCATEGORY: No Such Place\nTITLE: T\nSHORT: S\nLONG:\n## One\nWords here.\nEND")[0];

        $this->assertNotEmpty(array_filter($item['errors'], fn ($e) => str_contains($e, 'CATEGORY')));
    }

    public function test_lost_heading_marks_are_guessed_from_short_lines(): void
    {
        $long = "What Is Tawhid al-Uluhiyyah?\nTawhid al-Uluhiyyah means worshipping Allah (SWT) alone. It is the right of Allah (SWT) to be worshipped.\n\nIn simple terms, Muslims believe that worship belongs to Allah (SWT) alone.\n\nWhat Does the Quran Say?\nThe Quran says to worship Allah (SWT) alone and join nothing with Him.\n\nConclusion\nIt is the centre of faith.";

        $item = Importer::parse($this->answer('', $long))[0];

        $this->assertSame([], $item['errors']);
        $this->assertSame(['What Is Tawhid al-Uluhiyyah?', 'What Does the Quran Say?', 'Conclusion'], array_column($item['data']['outline'], 'title'));
        $this->assertStringContainsString('## Conclusion', $item['data']['long']);
        $this->assertStringContainsString('short lines were read as headings', implode(' ', $item['warnings']));
        $this->assertNotEmpty(array_filter($item['data']['please_check'], fn ($p) => str_contains($p, 'Headings were guessed')));
    }

    public function test_sentences_lists_and_blocks_are_never_taken_for_headings(): void
    {
        [$text, $n] = Importer::guessHeadings("A short sentence that ends here.\nAnd more words follow in this line of text.\n\n- A list line\n- Another line\n\n[QURAN]\nARABIC: x\nTEXT: A verse without an end mark\nREF: Surah 1:1\n[/QURAN]\n\nWhat a real heading looks like\nThe text of the part is long enough to follow it.");

        $this->assertSame(1, $n);
        $this->assertStringContainsString("## What a real heading looks like", $text);
        $this->assertStringNotContainsString('## TEXT', $text);
        $this->assertStringNotContainsString('## A list line', $text);
        $this->assertStringNotContainsString('## A short sentence', $text);
    }

    public function test_a_text_that_already_has_headings_is_left_alone(): void
    {
        $long = "## First\nWords for the first part here.\n\nA line that could look like a heading\nBut it comes after real headings were given.";

        $item = Importer::parse($this->answer('', $long))[0];

        $this->assertSame(['First'], array_column($item['data']['outline'], 'title'));
        $this->assertEmpty(array_filter($item['warnings'], fn ($w) => str_contains($w, 'read as headings')));
    }

    public function test_a_single_guess_is_not_trusted(): void
    {
        [, $n] = Importer::guessHeadings("Only one\nThe text follows here and is longer than the line above it.");
        $this->assertSame(1, $n);

        $item = Importer::parse($this->answer('', "Only one\nThe text follows here and is longer than the line above it."))[0];
        $this->assertSame([], $item['data']['outline'], 'one guess alone is not used');
    }

    public function test_the_prompt_asks_for_a_code_block_and_an_empty_also_in(): void
    {
        $p = Prompts::research(['topic' => 'x']);

        $this->assertStringContainsString('inside ONE code block', $p);
        $this->assertStringContainsString('ALSO IN: (leave this line empty)', $p);
    }
}
