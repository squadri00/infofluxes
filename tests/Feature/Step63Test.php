<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\AiImport as Importer;
use App\Support\Prompts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 63: META TITLE and META DESCRIPTION in the AI import. */
class Step63Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function answer(array $over = []): string
    {
        $f = array_merge([
            'TYPE' => 'article', 'CATEGORY' => $this->cat->code, 'ALSO IN' => '', 'TITLE' => 'Why the moon matters', 'SHORT' => 'Short words about the moon.',
            'LONG' => "## One\nWords here.\n\n## Two\nMore words.", 'KEYWORDS' => 'moon, calendar',
            'META TITLE' => 'Why the moon matters in the calendar', 'META DESCRIPTION' => 'How the new moon sets the months, and why people looked to the sky to know when a month began. A plain, sourced explanation.',
            'VIDEOS' => '', 'SOURCE' => 'One source',
        ], $over);
        $out = '';
        foreach ($f as $k => $v) {
            $out .= $k.':'.(str_contains((string) $v, "\n") ? "\n" : ' ').$v."\n";
        }

        return $out.'END';
    }

    /** Only the notes about the META lines (an answer without a SOURCES list has its own note). */
    private function metaWarnings(array $item): array
    {
        return array_values(array_filter($item['warnings'], fn ($w) => str_contains($w, 'META')));
    }

    public function test_the_meta_lines_are_read_and_do_not_disturb_the_title(): void
    {
        $item = Importer::parse($this->answer())[0];

        $this->assertSame([], $item['errors']);
        $this->assertSame('Why the moon matters', $item['data']['title']);
        $this->assertSame('Why the moon matters in the calendar', $item['data']['meta_title']);
        $this->assertStringStartsWith('How the new moon sets the months', $item['data']['meta_description']);
        $this->assertSame([], $this->metaWarnings($item));
    }

    public function test_without_the_meta_lines_nothing_breaks(): void
    {
        $item = Importer::parse($this->answer(['META TITLE' => '', 'META DESCRIPTION' => '']))[0];
        $this->assertSame([], $item['errors']);
        $this->assertNull($item['data']['meta_title']);
        $this->assertNull($item['data']['meta_description']);
    }

    public function test_too_long_or_too_short_lines_are_warned_about_and_absurd_ones_are_refused(): void
    {
        $item = Importer::parse($this->answer(['META TITLE' => str_repeat('a', 75), 'META DESCRIPTION' => str_repeat('b', 200)]))[0];
        $this->assertSame([], $item['errors']);
        $this->assertCount(2, $this->metaWarnings($item));

        $short = Importer::parse($this->answer(['META DESCRIPTION' => 'Too short.']))[0];
        $this->assertStringContainsString('very short', implode(' ', $short['warnings']));

        $bad = Importer::parse($this->answer(['META TITLE' => str_repeat('a', 130), 'META DESCRIPTION' => str_repeat('b', 400)]))[0];
        $this->assertCount(2, $bad['errors']);
    }

    public function test_tags_in_the_meta_lines_are_removed(): void
    {
        $item = Importer::parse($this->answer(['META TITLE' => 'The <b>moon</b> <script>x</script> matters']))[0];
        $this->assertSame('The moon x matters', $item['data']['meta_title']);
    }

    public function test_saving_puts_them_in_the_page_title_and_meta_description_boxes_and_the_page_uses_them(): void
    {
        $items = Importer::parse($this->answer());
        Importer::commit($items, 'raw', User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        $a = Article::where('slug', 'why-the-moon-matters')->firstOrFail();
        $this->assertSame('Why the moon matters in the calendar', $a->page_title);
        $this->assertStringStartsWith('How the new moon sets', $a->meta_description);

        $a->update(['status' => 'published', 'publish_date' => now()->subDay()]);
        $html = $this->get('/why-the-moon-matters')->getContent();
        $this->assertStringContainsString('<title>Why the moon matters in the calendar</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="How the new moon sets', $html);
    }

    public function test_an_article_saved_without_them_keeps_the_fallbacks(): void
    {
        Importer::commit(Importer::parse($this->answer(['META TITLE' => '', 'META DESCRIPTION' => ''])), 'raw', null);
        $a = Article::where('slug', 'why-the-moon-matters')->firstOrFail();
        $a->update(['status' => 'published', 'publish_date' => now()->subDay()]);

        $html = $this->get('/why-the-moon-matters')->getContent();
        $this->assertStringContainsString('<title>Why the moon matters | InfoFluxes</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Short words about the moon.">', $html);
    }

    public function test_every_prompt_asks_for_the_two_lines(): void
    {
        foreach (['article', 'fact'] as $kind) {
            $text = Prompts::make($kind, 'x');
            $this->assertStringContainsString('META TITLE:', $text, $kind);
            $this->assertStringContainsString('META DESCRIPTION:', $text, $kind);
        }
        $this->assertStringContainsString('META TITLE:', Prompts::research(['topic' => 'x', 'category' => $this->cat->code]));
    }
}
