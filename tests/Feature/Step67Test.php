<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\User;
use App\Support\SiteControls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 67: the header for phones (logo, light/dark and Menu in one bar; menu with Log in, Sign up and language; full-width search). */
class Step67Test extends TestCase
{
    use RefreshDatabase;

    private function mobile(): string
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('~<header class="m-header".*?</header>\s*<div class="m-search">.*?</div>~s', $html, $m);

        return $m[0] ?? '';
    }

    public function test_the_phone_header_has_the_bar_the_menu_and_a_search_without_categories(): void
    {
        \App\Models\Category::create(['name' => ['en' => 'About'], 'slug' => 'about']);
        $html = $this->mobile();

        $this->assertNotSame('', $html, 'the phone header is in the page');
        $this->assertStringContainsString('m-header__logo', $html);
        $this->assertStringContainsString('data-theme-toggle', $html);
        $this->assertStringContainsString('data-m-toggle', $html);
        $this->assertStringContainsString('About', $html);
        // The search is full width and has no category list.
        $search = substr($html, strpos($html, 'class="m-search"'));
        $this->assertStringContainsString('name="q"', $search);
        $this->assertStringNotContainsString('<select', $search);
        $this->assertStringNotContainsString('name="category"', $search);
    }

    public function test_log_in_sign_up_and_language_are_inside_the_menu(): void
    {
        $html = $this->mobile();
        $bar = substr($html, 0, strpos($html, 'id="m-drawer"'));
        $drawer = substr($html, strpos($html, 'id="m-drawer"'));

        $this->assertStringNotContainsString('Log in', $bar);
        $this->assertStringNotContainsString('Sign up', $bar);
        $this->assertStringContainsString('Log in', $drawer);
        $this->assertStringContainsString('Sign up', $drawer);
        $this->assertStringContainsString('Language: English', $drawer);
        $this->assertStringContainsString('flags/gb.svg', $drawer);
    }

    public function test_a_logged_in_person_sees_the_account_and_log_out_in_the_menu(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Aisha Khan', 'role' => 'member', 'status' => 'active']));
        $drawer = substr($this->mobile(), strpos($this->mobile(), 'id="m-drawer"'));

        $this->assertStringContainsString('Aisha Khan', $drawer);
        $this->assertStringContainsString('Log out', $drawer);
        $this->assertStringNotContainsString('Sign up', $drawer);
    }

    public function test_coming_soon_languages_are_listed_but_not_links_and_the_switches_still_work(): void
    {
        Language::create(['code' => 'ur', 'name' => 'Urdu', 'native_name' => 'اردو', 'flag' => 'pk', 'is_rtl' => true, 'status' => 'coming_soon']);
        $drawer = substr($this->mobile(), strpos($this->mobile(), 'id="m-drawer"'));
        $this->assertStringContainsString('اردو', $drawer);
        $this->assertStringNotContainsString('language/ur', $drawer);

        SiteControls::set('language', false);
        SiteControls::set('theme', false);
        $html = $this->mobile();
        $this->assertStringNotContainsString('m-drawer__lang', $html);
        $this->assertStringNotContainsString('data-theme-toggle', $html);
    }

    public function test_the_phone_header_script_and_styles_are_loaded_and_the_usual_header_stays(): void
    {
        $this->get('/')->assertSee('js/mobile-header.js', false)->assertSee('class="site-header"', false)->assertSee('data-site-header', false);
        $css = file_get_contents(public_path('css/site.css'));
        $this->assertStringContainsString('.m-header', $css);
        $this->assertStringContainsString('@media (max-width: 760px)', $css);
        $js = file_get_contents(public_path('js/mobile-header.js'));
        $this->assertStringContainsString('data-m-toggle', $js);
    }
}
