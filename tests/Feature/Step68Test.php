<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 68: the logo beside the site name, and the icons of the browser tab. */
class Step68Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    public function test_the_source_logo_is_in_the_images_folder(): void
    {
        $this->assertFileExists(base_path('images/logo infofluxes.png'));
    }

    public function test_the_logo_and_the_name_show_side_by_side_in_every_header_and_the_footer(): void
    {
        Setting::put('logo', 'site/logo.webp');
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('class="site-header__logo" src="'.asset('storage/site/logo.webp').'"', $html);
        $this->assertStringContainsString('<span class="site-header__wordmark">InfoFluxes</span>', $html);
        $this->assertStringContainsString('class="site-footer__logo"', $html);
        $m = substr($html, strpos($html, 'class="m-header__logo"'), 400);
        $this->assertStringContainsString('storage/site/logo.webp', $m);
        $this->assertStringContainsString('<span>InfoFluxes</span>', $m);
    }

    public function test_with_the_switch_off_only_the_logo_shows_and_it_carries_the_name_for_screen_readers(): void
    {
        Setting::put('logo', 'site/logo.webp');
        Setting::put('logo_with_name', false);
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('site-header__wordmark', $html);
        $this->assertStringContainsString('alt="InfoFluxes"', $html);
    }

    public function test_without_a_logo_the_name_shows_as_before(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('<span class="site-header__wordmark">InfoFluxes</span>', $html);
        $this->assertStringNotContainsString('site-header__logo"', $html);
    }

    public function test_the_page_has_icons_for_the_tab_and_the_phone_home_screen(): void
    {
        $this->get('/')->assertSee('favicon-32.png', false)->assertSee('favicon-192.png', false)->assertSee('apple-touch-icon.png', false);
        foreach (['favicon-32.png', 'favicon-192.png', 'apple-touch-icon.png'] as $f) {
            $this->assertFileExists(public_path($f));
        }
    }
}
