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

    public function test_the_main_row_has_the_menu_and_only_a_hidden_copy_of_the_logo(): void
    {
        \App\Models\Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        $main = $this->parts()['main'];

        $this->assertStringContainsString('data-site-nav', $main);
        $this->assertSame(1, substr_count($main, '<a class="site-header__name '), 'one copy of the logo, for the slim header only');
        $this->assertStringContainsString('site-header__name--compact', $main);
        $this->assertStringNotContainsString('site-header__name--top', $main);
    }

    public function test_the_two_copies_never_show_together(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        $this->assertStringContainsString('.site-header__main .site-header__name--compact { display: none; }', $css, 'hidden in the normal header');
        $this->assertStringContainsString('.site-header.is-compact .site-header__main .site-header__name--compact { display: inline-flex; }', $css, 'shown in the slim header');
        $this->assertStringContainsString('.site-header.is-compact .site-header__top { display: none; }', $css, 'the top bar (with the other copy) is hidden then');
        $this->assertStringContainsString('grid-template-columns: 1fr auto 1fr', $css, 'tagline | logo | tools');
    }

    public function test_the_logo_picture_or_the_site_name_is_shown_in_the_middle(): void
    {
        $top = $this->parts()['top'];
        $this->assertMatchesRegularExpression('#site-header__name--top"[^>]*>\s*InfoFluxes\s*</a>#', $top, 'no logo yet: the name');

        Setting::put('logo', 'site/logo.webp');
        $top = $this->parts()['top'];
        $this->assertMatchesRegularExpression('#site-header__name--top"[^>]*>\s*<img class="site-header__logo" src="[^"]*site/logo\.webp" alt="InfoFluxes">#', $top);

        $main = $this->parts()['main'];
        $this->assertStringContainsString('alt=""', $main, 'the hidden copy is not read twice');
        $this->assertStringContainsString('aria-label="InfoFluxes"', $main);
    }

    public function test_the_search_row_and_the_tools_are_still_there(): void
    {
        $parts = $this->parts();

        $this->assertStringContainsString('data-theme-toggle', $parts['top']);
        $this->assertStringContainsString('Log in', $parts['top']);
        $this->assertStringContainsString('data-search-form', $this->get('/')->getContent());
    }
}
