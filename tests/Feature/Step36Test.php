<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\AiImport as Importer;
use App\Support\Html;
use App\Support\LinkChecker;
use App\Support\QuranRefs;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 36: the smarter Check: forgiving format, sections, sources, link check, Quran references. */
class Step36Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Cache::flush();
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function answer(array $over = []): string
    {
        $f = array_merge([
            'TYPE' => 'article', 'CATEGORY' => $this->cat->code, 'ALSO IN' => '', 'TITLE' => 'A researched one', 'SHORT' => 'Short words.',
            'LONG' => "## First part\nWords here about Quran 2:255.\n\n### Small part\nMore words.\n\n## Second part\n- one\n- two\n\nEnd words.",
            'KEYWORDS' => 'a, b', 'VIDEOS' => '', 'SOURCE' => 'One main source',
            'SOURCES' => "- PRIMARY | The Quran, 2:255 | https://quran.example.org/2/255 | opened: yes\n- SECONDARY | A. Writer, A Book, Press, 2001 | https://books.example.org/x | opened: no",
            'PLEASE CHECK' => "- The date of the event",
        ], $over);
        $out = '';
        foreach ($f as $k => $v) {
            $out .= $k.':'.(str_contains((string) $v, "\n") ? "\n" : ' ').$v."\n";
        }

        return $out.'END';
    }

    public function test_the_type_line_in_quotes_with_the_title_after_it_is_understood(): void
    {
        $text = "\"TYPE: article\" Ibn Battuta – The Muslim Traveller\nCATEGORY: {$this->cat->code}\nSHORT: Short.\nLONG:\n## One\nWords.\nEND";

        $item = Importer::parse($text)[0];

        $this->assertSame([], $item['errors']);
        $this->assertSame('Ibn Battuta – The Muslim Traveller', $item['data']['title']);
        $this->assertNotEmpty(array_filter($item['warnings'], fn ($w) => str_contains($w, 'title was on the TYPE line')));
    }

    public function test_bold_keys_are_understood(): void
    {
        $text = "**TYPE:** article\n**CATEGORY:** {$this->cat->code}\n**TITLE:** Bold title\n**SHORT:** Short.\n**LONG:**\nWords.\nEND";

        $item = Importer::parse($text)[0];

        $this->assertSame([], $item['errors']);
        $this->assertSame('Bold title', $item['data']['title']);
    }

    public function test_a_line_of_the_article_that_starts_with_a_quote_and_a_key_word_is_not_a_key(): void
    {
        $item = Importer::parse($this->answer(['LONG' => "## One\n\"Source: the hadith says this.\"\nMore."]))[0];

        $this->assertStringContainsString('Source: the hadith says this.', $item['data']['long']);
        $this->assertSame('One main source', $item['data']['source']);
    }

    public function test_the_parts_the_sources_and_the_check_list_are_read(): void
    {
        $item = Importer::parse($this->answer())[0];

        $this->assertSame([], $item['errors']);
        $this->assertSame([['level' => 2, 'title' => 'First part'], ['level' => 3, 'title' => 'Small part'], ['level' => 2, 'title' => 'Second part']], $item['data']['outline']);
        $this->assertCount(2, $item['data']['sources']);
        $this->assertSame('primary', $item['data']['sources'][0]['type']);
        $this->assertSame('https://quran.example.org/2/255', $item['data']['sources'][0]['url']);
        $this->assertTrue($item['data']['sources'][0]['opened']);
        $this->assertFalse($item['data']['sources'][1]['opened']);
        $this->assertContains('The date of the event', $item['data']['please_check']);
        $this->assertNotEmpty(array_filter($item['data']['please_check'], fn ($p) => str_contains($p, 'the AI did not')), 'a source the AI did not open goes to the check list');
    }

    public function test_links_are_asked_and_a_broken_one_is_flagged(): void
    {
        Http::fake(['quran.example.org/*' => Http::response('ok', 200), 'books.example.org/*' => Http::response('gone', 404)]);

        $item = Importer::parse($this->answer(), true)[0];

        $this->assertSame('ok', $item['data']['sources'][0]['link']);
        $this->assertSame('broken', $item['data']['sources'][1]['link']);
        $this->assertNotEmpty(array_filter($item['warnings'], fn ($w) => str_contains($w, 'did not open')));
        $this->assertNotEmpty(array_filter($item['data']['please_check'], fn ($p) => str_contains($p, 'A link did not open')));
    }

    public function test_without_the_link_check_no_request_is_made(): void
    {
        Http::fake();

        Importer::parse($this->answer());

        Http::assertNothingSent();
    }

    public function test_local_and_private_addresses_are_never_visited(): void
    {
        Http::fake();

        foreach (['http://localhost/x', 'http://127.0.0.1/x', 'http://192.168.1.5/x', 'http://10.0.0.1/', 'ftp://example.org/x', 'http://user:pw@example.org/', 'http://intranet/x', 'file:///etc/passwd'] as $url) {
            $this->assertSame('skipped', LinkChecker::check($url), $url);
        }
        Http::assertNothingSent();
    }

    public function test_quran_references_are_checked_against_the_verse_counts(): void
    {
        $this->assertSame(114, count(QuranRefs::counts()));
        $this->assertSame(6236, array_sum(QuranRefs::counts()));

        $refs = collect(QuranRefs::find('See Quran 2:255 and Surah Al-Baqarah 2:300, also (112:1-4) and [115:1]. Not John 3:16 and not the ratio 1:30.'))->keyBy('ref');

        $this->assertTrue($refs['2:255']['valid']);
        $this->assertFalse($refs['2:300']['valid']);
        $this->assertStringContainsString('only 286 verses', $refs['2:300']['problem']);
        $this->assertTrue($refs['112:1-4']['valid']);
        $this->assertFalse($refs['115:1']['valid']);
        $this->assertArrayNotHasKey('3:16', $refs->all());
        $this->assertArrayNotHasKey('1:30', $refs->all());
    }

    public function test_a_wrong_quran_reference_gives_a_warning_and_a_check_point(): void
    {
        $item = Importer::parse($this->answer(['LONG' => "## One\nThe Quran 2:999 says this."]))[0];

        $this->assertNotEmpty(array_filter($item['warnings'], fn ($w) => str_contains($w, '2:999')));
        $this->assertNotEmpty(array_filter($item['data']['please_check'], fn ($p) => str_contains($p, '2:999')));
    }

    public function test_marked_text_becomes_safe_html(): void
    {
        $html = Html::fromMarked("## Head <b>x</b>\nA line.\nNext line.\n\n### Small\n- one\n- <script>two</script>\n\nEnd & done.");

        $this->assertStringContainsString('<h2>Head &lt;b&gt;x&lt;/b&gt;</h2>', $html);
        $this->assertStringContainsString('<p>A line.<br>Next line.</p>', $html);
        $this->assertStringContainsString('<h3>Small</h3>', $html);
        $this->assertStringContainsString('<ul><li>one</li><li>&lt;script&gt;two&lt;/script&gt;</li></ul>', $html);
        $this->assertStringContainsString('End &amp; done.', $html);
    }

    public function test_the_accordion_needs_two_main_headings(): void
    {
        $this->assertSame('<h2>A</h2><p>x</p>', Html::accordion('<h2>A</h2><p>x</p>'));

        $out = Html::accordion('<p>Intro</p><h2>A</h2><p>x</p><h3>s</h3><h2>B</h2><p>y</p>');

        $this->assertStringStartsWith('<p>Intro</p><details class="acc" open>', $out);
        $this->assertSame(2, substr_count($out, '<details'));
        $this->assertSame(1, substr_count($out, 'open>'));
        $this->assertStringContainsString('<summary class="acc__title">B</summary>', $out);
    }

    public function test_saving_keeps_the_parts_the_sources_and_the_check_list_and_the_page_shows_them(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(AiImport::class)->set('paste', $this->answer())->call('check')->call('save');

        $a = Article::where('slug', 'a-researched-one')->firstOrFail();
        $this->assertSame('written', $a->status);
        $this->assertTrue((bool) $a->ai_assisted);
        $this->assertStringContainsString('<h2>First part</h2>', $a->getTranslation('long_detail', 'en'));
        $this->assertTrue($a->custom_data['accordion']);
        $this->assertCount(2, $a->custom_data['sources']);
        $this->assertContains('The date of the event', $a->custom_data['please_check']);

        $a->update(['status' => 'published', 'publish_date' => now()->subDay()]);
        auth()->logout();
        $html = $this->get(route('article.show', $a->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<details class="acc" open>', $html);
        $this->assertStringContainsString('First-hand sources', $html);
        $this->assertStringContainsString('https://quran.example.org/2/255', $html);
        $this->assertStringContainsString('rel="nofollow noopener"', $html);
        $this->assertStringNotContainsString('opened: no', $html, 'the AI notes are not shown to readers');
    }

    public function test_a_source_with_a_strange_address_is_not_made_a_link(): void
    {
        $a = Article::create(['title' => ['en' => 'T'], 'slug' => 't', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'custom_data' => ['sources' => [['type' => 'primary', 'ref' => 'A book', 'url' => 'javascript:alert(1)']]]]);

        $html = $this->get('/t')->assertOk()->getContent();

        $this->assertStringContainsString('A book', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
    }

    public function test_an_old_article_with_headings_is_not_turned_into_drop_downs(): void
    {
        Article::create(['title' => ['en' => 'Old'], 'slug' => 'old', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'long_detail' => ['en' => '<h2>A</h2><p>x</p><h2>B</h2><p>y</p>']]);

        $this->get('/old')->assertOk()->assertDontSee('class="acc"', false);
    }
}
