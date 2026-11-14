<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Support\AiImport as Importer;
use App\Support\LinkChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Step 40: the sources are a drop-down part, there is an Expand all / Collapse all button, and a site that blocks checks is not "broken". */
class Step40Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function article(array $custom, string $html = '<h2>A</h2><p>x</p><h2>B</h2><p>y</p>'): Article
    {
        return Article::create(['title' => ['en' => 'T'], 'slug' => 't', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'long_detail' => ['en' => $html], 'custom_data' => $custom]);
    }

    public function test_the_sources_are_a_closed_drop_down_part(): void
    {
        $this->article(['sources' => [['type' => 'primary', 'ref' => 'A book', 'url' => 'https://example.org/b'], ['type' => 'secondary', 'ref' => 'Another', 'url' => null]]]);

        $html = $this->get('/t')->assertOk()->getContent();

        $this->assertStringContainsString('<details class="acc acc--sources article__sources">', $html);
        $this->assertStringContainsString('Sources (2)', $html);
        $this->assertStringContainsString('First-hand sources', $html);
        $this->assertStringNotContainsString('<details class="acc acc--sources article__sources" open', $html, 'it starts closed');
    }

    public function test_the_toggle_button_shows_only_on_a_drop_down_article(): void
    {
        $this->article(['accordion' => true]);
        $html = $this->get('/t')->assertOk()->getContent();
        $this->assertStringContainsString('data-acc-toggle', $html);
        $this->assertStringContainsString('Expand all', $html);
        $this->assertStringContainsString('Collapse all', $html);
        $this->assertStringContainsString('js/accordion.js', $html);

        Article::query()->forceDelete();
        $this->article([]);
        $this->get('/t')->assertOk()->assertDontSee('data-acc-toggle', false);
    }

    public function test_the_script_and_the_styles_for_the_toggle_exist(): void
    {
        $js = (string) file_get_contents(public_path('js/accordion.js'));
        $this->assertStringContainsString('details.acc', $js);
        $this->assertStringContainsString('data-acc-toggle', $js);
        $css = (string) file_get_contents(public_path('css/site.css'));
        $this->assertStringContainsString('.acc-toggle', $css);
        $this->assertStringContainsString('.acc__title', $css);
        $this->assertStringNotContainsString('.article__body .acc__title', $css, 'the part styles are not tied to the article body any more');
    }

    public function test_a_site_that_refuses_checks_is_blocked_not_broken(): void
    {
        Http::fake(['blocked.example.org/*' => Http::response('no', 403), 'gone.example.org/*' => Http::response('no', 404), 'slow.example.org/*' => Http::response('no', 429)]);

        $this->assertSame('blocked', LinkChecker::check('https://blocked.example.org/x'));
        $this->assertSame('blocked', LinkChecker::check('https://slow.example.org/x'));
        $this->assertSame('broken', LinkChecker::check('https://gone.example.org/x'));
    }

    public function test_check_asks_the_reviewer_to_open_a_blocked_link_themselves(): void
    {
        Http::fake(['blocked.example.org/*' => Http::response('no', 403), '*' => Http::response('ok', 200)]);
        $cat = $this->cat;
        $answer = "TYPE: article\nCATEGORY: {$cat->code}\nTITLE: Blocked links\nSHORT: Short.\nLONG:\n## One\nWords.\nSOURCES:\n- PRIMARY | A hadith | https://blocked.example.org/h | opened: yes\nEND";

        $item = Importer::parse($answer, true)[0];

        $this->assertSame('blocked', $item['data']['sources'][0]['link']);
        $w = implode(' | ', $item['warnings']);
        $this->assertStringContainsString('does not allow automatic checks', $w);
        $this->assertStringNotContainsString('did not open', $w);
        $this->assertNotEmpty(array_filter($item['data']['please_check'], fn ($p) => str_contains($p, 'Open this link yourself')));
    }
}
