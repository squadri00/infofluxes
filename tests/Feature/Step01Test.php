<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 1: the setup. People have a role and a status, and only active staff may enter the admin area. */
class Step01Test extends TestCase
{
    use RefreshDatabase;

    private function person(string $role, string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    public function test_the_admin_login_page_opens(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_the_super_user_enters_the_admin_area(): void
    {
        $this->actingAs($this->person('superuser'))->get('/admin')->assertOk();
    }

    public function test_moderator_and_author_enter_the_admin_area(): void
    {
        $this->actingAs($this->person('moderator'))->get('/admin')->assertOk();
        $this->actingAs($this->person('author'))->get('/admin')->assertOk();
    }

    public function test_a_member_cannot_enter_the_admin_area(): void
    {
        $this->actingAs($this->person('member'))->get('/admin')->assertForbidden();
    }

    public function test_a_switched_off_or_waiting_person_cannot_enter(): void
    {
        $this->actingAs($this->person('superuser', 'switched_off'))->get('/admin')->assertForbidden();
        $this->actingAs($this->person('author', 'waiting'))->get('/admin')->assertForbidden();
    }

    public function test_english_is_the_main_language_and_more_can_be_added_in_one_place(): void
    {
        $this->assertSame('en', config('infofluxes.default_locale'));
        $this->assertSame(['en'], array_keys(config('infofluxes.locales')));
    }
}
