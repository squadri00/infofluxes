<?php

namespace Tests\Feature;

use App\Filament\Support\Editor;
use App\Support\Html;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 65: the richer text editor and what survives of its output. */
class Step65Test extends TestCase
{
    use RefreshDatabase;

    public function test_the_article_form_has_the_old_site_buttons_and_pictures(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $html = strtolower($this->get('/admin/articles/create')->assertOk()->getContent());

        foreach (['highlight', 'underline', 'subscript', 'superscript', 'strike', 'details', 'attachfiles', 'aligncenter', 'alignjustify', 'tablemergecells', 'codeblock'] as $tool) {
            $this->assertStringContainsString($tool, $html, $tool);
        }
        $this->assertStringContainsString('textcolor', $html);
    }

    public function test_coloured_words_highlight_and_alignment_survive(): void
    {
        $in = '<p style="text-align: center">Hi <span class="color" data-color="red" style="--color: #dc2626; --dark-color: #f87171">red</span> and <mark data-color="#fef08a" style="background-color: #fef08a; color: inherit">marked</mark></p>';
        $out = Html::clean($in);

        $this->assertStringContainsString('text-align: center', $out);
        $this->assertStringContainsString('<span class="color" style="--color: #dc2626; --dark-color: #f87171">red</span>', $out);
        $this->assertStringContainsString('<mark style="background-color: #fef08a; color: inherit">marked</mark>', $out);
    }

    public function test_dangerous_styles_and_attributes_are_still_removed(): void
    {
        $out = Html::clean('<p style="position:fixed;top:0;text-align:center;background:url(javascript:x)" onclick="x()">A</p><span style="--color: red; behavior: url(x)">B</span><mark style="background-color: expression(alert(1))">C</mark><span class="color evil" style="--color:url(http://evil)">D</span>');

        $this->assertStringNotContainsString('position', $out);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('javascript', $out);
        $this->assertStringNotContainsString('expression', $out);
        $this->assertStringNotContainsString('evil', $out);
        $this->assertStringNotContainsString('behavior', $out);
        $this->assertStringContainsString('text-align: center', $out);
        $this->assertStringContainsString('--color: red', $out);
    }

    public function test_lead_small_and_folding_boxes_survive(): void
    {
        $out = Html::clean('<p class="lead">Big</p><p class="other">x</p><small>tiny</small><details><summary>More</summary><div data-type="details-content"><p>Inside</p></div></details>');

        $this->assertStringContainsString('<p class="lead">Big</p>', $out);
        $this->assertStringNotContainsString('class="other"', $out);
        $this->assertStringContainsString('<small>tiny</small>', $out);
        $this->assertStringContainsString('<details><summary>More</summary>', $out);
        $this->assertStringContainsString('<p>Inside</p>', $out);
    }

    public function test_only_pictures_from_this_sites_storage_are_kept(): void
    {
        $out = Html::clean(
            '<img src="/storage/content/a.webp" alt="A moon" width="300" height="200" onerror="x()">'
            .'<img src="http://localhost/storage/content/b.png">'
            .'<img src="https://evil.example.com/storage/content/c.png">'
            .'<img src="/storage/../.env">'
            .'<img src="javascript:alert(1)">'
            .'<img src="data:image/png;base64,AAAA">'
            .'<img alt="no source">'
        );

        $this->assertStringContainsString('src="/storage/content/a.webp"', $out);
        $this->assertStringContainsString('alt="A moon"', $out);
        $this->assertStringContainsString('loading="lazy"', $out);
        $this->assertStringContainsString('src="http://localhost/storage/content/b.png"', $out);
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('evil.example.com', $out);
        $this->assertStringNotContainsString('.env', $out);
        $this->assertStringNotContainsString('javascript', $out);
        $this->assertStringNotContainsString('data:image', $out);
        $this->assertSame(2, substr_count($out, '<img'));
    }

}
