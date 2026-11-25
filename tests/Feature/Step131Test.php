<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 131: every admin screen loads the script that puts a second horizontal scrollbar above the wide tables. */
class Step131Test extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_screens_load_the_top_scrollbar_script_and_the_file_is_there(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        $this->get('/admin/articles')->assertOk()->assertSee('js/admin-top-scroll.js', false);
        $js = file_get_contents(public_path('js/admin-top-scroll.js'));
        foreach (['fi-ta-content-ctn', 'if-topscroll', 'scrollLeft', 'MutationObserver'] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle);
        }
    }

    public function test_the_public_site_does_not_load_it(): void
    {
        $this->get('/')->assertOk()->assertDontSee('admin-top-scroll.js', false);
    }
}
