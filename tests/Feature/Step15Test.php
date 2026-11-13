<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 15: the logo is in the middle of the top bar, and the main row holds the menu only. */
class Step15Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    /** The three parts of the header: the top bar, the main row and the search row. */
    private function parts(): array
    {
        $html = $this->get('/')->assertOk()->getContent();
        $top = strpos($html, 'site-header__top');
        $main = strpos($html, 'class="site-header__main"');
        $search = strpos($html, 'id="header-search"');

        return [
            'top' => substr($html, $top, $main - $top),
            'main' => substr($html, $main, $search - $main),
            'search' => substr($html, $search, 400),
        ];
    }

    public function test_the_logo_is_between_the_tagline_and_the_tools_in_the_top_bar(): void
    {
        $top = $this->parts()['top'];

        $tagline = strpos($top, 'site-header__tagline');
        $logo = strpos($top, 'site-header__name--top');
        $tools = strpos($top, 'site-header__tools');

        $this->assertNotFalse($logo);
        $this->assertLessThan($logo, $tagline, 'the tagline is on the left');
        $this->assertLessThan($tools, $logo, 'the logo is in the middle, before the tools on the right');
        $this->assertStringContainsString('href="'.route('home').'"', substr($top, $logo, 200));
    }

    public function test_the_main_row_has_the_menu_and_no_logo(): void
    {
        \App\Models\Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        $main = $this->parts()['main'];

        $this->assertStringContainsString('data-site-nav', $main);
        $this->assertStringNotContainsString('site-header__name', $main, 'no logo here, in the normal header or in the slim one');
        $this->assertStringContainsString('data-search-toggle', $main, 'the search button is still there for the slim header');
    }

    public function test_the_slim_header_while_scrolling_has_no_logo(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        $this->assertStringContainsString('.site-header.is-compact .site-header__top { display: none; }', $css, 'the top bar, where the logo is, is hidden while scrolling');
        $this->assertStringNotContainsString('name--compact', $css, 'no small copy of the logo any more');
        $this->assertStringContainsString('.site-header.is-compact .site-header__main-inner { flex-wrap: nowrap;', $css, 'the menu and the search button stay on one row');
        $this->assertStringContainsString('grid-template-columns: 1fr auto 1fr', $css, 'tagline | logo | tools');
    }

    public function test_the_logo_picture_or_the_site_name_is_shown_in_the_middle(): void
    {
        $top = $this->parts()['top'];
        $this->assertMatchesRegularExpression('#site-header__name--top"[^>]*>\s*InfoFluxes\s*</a>#', $top, 'no logo yet: the name');

        Setting::put('logo', 'site/logo.webp');
        $top = $this->parts()['top'];
        $this->assertMatchesRegularExpression('#site-header__name--top"[^>]*>\s*<img class="site-header__logo" src="[^"]*site/logo\.webp" alt="InfoFluxes">#', $top);

        $this->assertStringNotContainsString('site/logo.webp', $this->parts()['main'], 'the picture is in the top bar only');
    }

    public function test_the_search_row_and_the_tools_are_still_there(): void
    {
        $parts = $this->parts();

        $this->assertStringContainsString('data-theme-toggle', $parts['top']);
        $this->assertStringContainsString('Log in', $parts['top']);
        $this->assertStringContainsString('data-search-form', $this->get('/')->getContent());
    }
}
