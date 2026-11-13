<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 26: the main menu never drops to a second row: what does not fit moves into a "More" drop-down. */
class Step26Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    public function test_the_menu_tells_the_script_the_word_for_more(): void
    {
        Category::create(['name' => ['en' => 'Faith'], 'slug' => 'faith']);

        $this->get('/')->assertOk()->assertSee('data-more-label="More"', false)->assertSee('data-site-nav', false);
    }

    public function test_the_script_moves_the_items_that_do_not_fit_into_a_more_menu_and_keeps_every_link(): void
    {
        $js = (string) file_get_contents(public_path('js/header.js'));

        $this->assertStringContainsString('menu__more', $js);
        $this->assertStringContainsString('overflows()', $js);
        $this->assertStringContainsString("classList.remove('nav-below')", $js, 'the old second row is never used');
        $this->assertStringNotContainsString("classList.add('nav-below')", $js);
        $this->assertStringContainsString("querySelectorAll('.menu__sub a')", $js, 'the sub-items of a moved item are kept as links');
        $this->assertStringContainsString('wireDrop(moreItem', $js, 'the More menu opens like the others');
    }

    public function test_the_style_keeps_the_menu_on_one_row_when_the_script_runs(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        $this->assertStringContainsString('.site-header.menu-fit .menu--main .menu__list { flex-wrap: nowrap; }', $css);
        $this->assertStringContainsString('.menu__more .menu__sub', $css);
    }

    public function test_without_the_script_the_menu_still_lists_every_item(): void
    {
        foreach (['Faith', 'History', 'Science'] as $name) {
            Category::create(['name' => ['en' => $name], 'slug' => strtolower($name)]);
        }

        $html = $this->get('/')->assertOk()->getContent();

        foreach (['Faith', 'History', 'Science'] as $name) {
            $this->assertStringContainsString('>'.$name.'<', $html);
        }
    }
}
