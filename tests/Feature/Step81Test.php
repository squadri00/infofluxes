<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SiteControls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 81: the light / dark button is the first thing on the left of the top row, and it shows one icon at a time. */
class Step81Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    public function test_the_theme_button_comes_first_in_the_top_row(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $start = strpos($html, '<ul class="site-header__tools">');
        $this->assertNotFalse($start);
        $row = substr($html, $start);

        $this->assertLessThan(strpos($row, 'menu__item--challenge') ?: PHP_INT_MAX, strpos($row, 'data-theme-toggle'), 'before Challenge yourself');
        $this->assertLessThan(strpos($row, 'lang-menu') ?: PHP_INT_MAX, strpos($row, 'data-theme-toggle'), 'before the language');
        $this->assertLessThan(strpos($row, route('login')) ?: PHP_INT_MAX, strpos($row, 'data-theme-toggle'), 'before Log in');
    }

    public function test_the_theme_button_can_still_be_switched_off(): void
    {
        SiteControls::set('theme', false);
        $this->get('/')->assertOk()->assertDontSee('data-theme-toggle', false);
    }

    public function test_only_one_icon_shows_at_a_time(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        $this->assertStringContainsString('.theme-toggle__sun { display: none; }', $css);
        $this->assertStringContainsString(':root[data-theme="dark"] .theme-toggle__moon { display: none; }', $css);
    }
}
