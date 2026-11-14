<?php

namespace Tests\Feature;

use App\Models\AiPrompt;
use App\Models\Category;
use App\Support\AiImport as Importer;
use App\Support\Prompts;
use App\Support\ReadingLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 38: written for ordinary people: the audience rule in the master prompt and the reading check. */
class Step38Test extends TestCase
{
    use RefreshDatabase;

    private function answer(string $long): string
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);

        return "TYPE: article\nCATEGORY: {$cat->code}\nTITLE: Plain words\nSHORT: Short.\nLONG:\n".$long."\nSOURCE: A source\nEND";
    }

    public function test_the_master_prompt_starts_with_the_audience_rule(): void
    {
        $master = AiPrompt::where('slug', 'master')->value('body');

        $this->assertStringContainsString('AUDIENCE AND WRITING STYLE (MANDATORY', $master);
        $this->assertLessThan(strpos($master, 'Never invent'), strpos($master, 'AUDIENCE AND WRITING STYLE'), 'the audience rule comes first');
        foreach ([
            'not an academic journal', 'Simple writing, not simplified knowledge', 'Never use a comparison for Allah (SWT)', '"Muslims believe"',
            'what it means and why it matters', 'put the full details in the SOURCES list', 'I understand this now',
        ] as $needle) {
            $this->assertStringContainsString($needle, $master, $needle);
        }
        $this->assertStringNotContainsString('Simple English', $master, 'the old style section is merged, not repeated');
    }

    public function test_the_prompt_asks_for_a_plain_answer_in_short(): void
    {
        $p = Prompts::research(['topic' => 'x', 'specialist' => 'islam-beliefs']);

        $this->assertStringContainsString('the plain answer in one or two simple sentences', $p);
        $this->assertStringContainsString('AUDIENCE AND WRITING STYLE', $p);
    }

    public function test_easy_text_passes_the_reading_check(): void
    {
        $text = str_repeat("Tawhid means that Allah (SWT) is one. He has no partners. He made everything we see. Muslims believe that only He should be worshipped.\n\n", 4);

        $r = ReadingLevel::measure($text);

        $this->assertFalse($r['hard']);
        $this->assertLessThan(10, $r['average']);
        $this->assertSame([], $r['too_long']);
    }

    public function test_hard_text_is_flagged_with_its_long_sentences(): void
    {
        $long = 'The theological affirmation of divine unity represents the exclusive attribution of lordship, divinity and essential attributes to the one creator of the heavens and the earth, as discussed extensively by the classical scholars of the early centuries in many different books and traditions.';
        $text = str_repeat($long.' ', 3);

        $r = ReadingLevel::measure($text);

        $this->assertTrue($r['hard']);
        $this->assertGreaterThan(18, $r['average']);
        $this->assertCount(3, $r['too_long']);
    }

    public function test_headings_and_list_marks_are_not_counted_as_sentences(): void
    {
        $r = ReadingLevel::measure("## A heading that is not a sentence at all really\nOne short sentence here.\n- A list line.\n");

        $this->assertSame(2, $r['sentences']);
    }

    public function test_a_very_short_text_is_never_flagged(): void
    {
        $this->assertFalse(ReadingLevel::measure('This sentence has a great many words in it but it is the only one there is here so far.')['hard']);
    }

    public function test_check_warns_and_adds_a_point_for_hard_writing(): void
    {
        $hard = str_repeat('The theological affirmation of divine unity represents the exclusive attribution of lordship, divinity and essential attributes to the one creator of the heavens and the earth, as discussed extensively by the classical scholars of the early centuries in many different books and traditions. ', 3);

        $item = Importer::parse($this->answer("## One\n".$hard))[0];

        $this->assertSame([], $item['errors'], 'hard writing is a warning, not a block');
        $this->assertNotEmpty(array_filter($item['warnings'], fn ($w) => str_contains($w, 'Hard to read')));
        $this->assertNotEmpty(array_filter($item['data']['please_check'], fn ($p) => str_contains($p, 'Make this sentence shorter')));
        $this->assertTrue($item['data']['reading']['hard']);
    }

    public function test_check_is_quiet_for_plain_writing(): void
    {
        $plain = str_repeat('Allah (SWT) is one. He made everything. Muslims believe this is the base of Islam. It gives their faith a clear centre. ', 6);

        $item = Importer::parse($this->answer("## One\n".$plain))[0];

        $this->assertEmpty(array_filter($item['warnings'], fn ($w) => str_contains($w, 'Hard to read')));
    }

    public function test_the_limits_come_from_the_settings(): void
    {
        config(['infofluxes.reading.avg_max' => 5, 'infofluxes.reading.sentence_max' => 8]);
        $text = str_repeat('This sentence has exactly seven words in it. ', 12);

        $this->assertTrue(ReadingLevel::measure($text)['hard']);
    }
}
