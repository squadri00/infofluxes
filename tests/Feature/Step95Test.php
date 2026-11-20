<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 95: every admin screen has a "Go to website" link in its header, for the staff. */
class Step95Test extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_header_has_a_go_to_website_link_that_opens_the_site_in_a_new_tab(): void
    {
        foreach (['superuser', 'moderator', 'author'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']));

            $html = $this->get('/admin/articles')->assertOk()->getContent();
            $this->assertStringContainsString('data-go-to-website', $html, $role);
            $this->assertStringContainsString('Go to website', $html);
            $this->assertMatchesRegularExpression('#<a href="http://localhost"[^>]*target="_blank"[^>]*data-go-to-website#', $html);
        }
    }
}
