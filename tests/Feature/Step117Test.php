<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Notify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 117: the top right of the header is an account drop-down for a logged-in person, and a Log in button for a visitor. */
class Step117Test extends TestCase
{
    use RefreshDatabase;

    private function member(array $extra = []): User
    {
        $user = User::factory()->create(array_merge(['name' => 'Ayesha Khan', 'email' => 'ayesha@example.test', 'role' => 'member', 'status' => 'active'], $extra));
        $this->actingAs($user);

        return $user;
    }

    public function test_a_visitor_sees_log_in_and_no_account_menu(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('login'), false)->assertSee('Log in')->assertDontSee('data-account-menu', false)->assertDontSee('Log out');
    }

    public function test_a_member_sees_their_name_with_a_letter_and_a_menu_with_the_account_pages_and_log_out(): void
    {
        $this->member();

        $page = $this->get(route('home'))->assertOk();

        $page->assertSee('data-account-menu', false)->assertSee('Ayesha Khan')->assertSee('ayesha@example.test')
            ->assertSee('account-menu__pic--blank', false)
            ->assertSeeHtml('href="'.route('account').'"')->assertSeeHtml('href="'.route('account.profile').'"')->assertSeeHtml('href="'.route('account.security').'"')
            ->assertSeeHtml('href="'.route('account.scores').'"')->assertSeeHtml('href="'.route('account.following').'"')->assertSeeHtml('href="'.route('submit').'"')
            ->assertSeeHtml('action="'.route('logout').'"')->assertSee('Log out')
            ->assertDontSee(url('/admin').'"', false);
        $this->assertStringNotContainsString('Log in</a>', $this->get(route('home'))->getContent(), 'the Log in button is gone once logged in');
    }

    public function test_a_picture_is_shown_instead_of_the_letter_when_the_member_has_one(): void
    {
        $this->member(['photo' => 'profiles/member-abc123.webp']);

        $this->get(route('home'))->assertOk()->assertSee('account-menu__pic', false)->assertSee('storage/profiles/member-abc123.webp', false)->assertDontSee('account-menu__pic--blank', false);
    }

    public function test_the_admin_link_is_in_the_menu_only_for_staff(): void
    {
        $this->member(['role' => 'moderator']);

        $this->get(route('home'))->assertOk()->assertSeeHtml('href="'.url('/admin').'"')->assertSee('Admin area');
    }

    public function test_an_unread_notice_shows_a_dot_on_the_menu_and_the_number_inside_it(): void
    {
        $me = $this->member();
        Notify::to($me, 'new_work', 'New: Something', '/x');

        $this->get(route('home'))->assertOk()->assertSee('account-menu__dot', false)->assertSee('bell__count', false);
    }

    public function test_the_phone_menu_lists_the_same_account_pages(): void
    {
        $this->member();

        $this->get(route('home'))->assertOk()->assertSee('m-drawer__who', false)->assertSeeHtml('href="'.route('account.scores').'"');
    }
}
