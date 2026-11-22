<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Follow;
use App\Models\Person;
use App\Models\User;
use App\Support\Following;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 112: a member follows categories (with everything below), contributors and words. */
class Step112Test extends TestCase
{
    use RefreshDatabase;

    private Category $master;
    private Category $leaf;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::forget();   // a switch saved by an earlier test must not leak into this one
        $this->master = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);
        $this->leaf = Category::create(['name' => ['en' => 'Conquests'], 'slug' => 'conquests', 'parent_id' => $this->master->id]);
    }

    protected function tearDown(): void
    {
        \App\Models\Setting::forget();
        parent::tearDown();
    }

    private function member(): User
    {
        $user = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $this->actingAs($user);

        return $user;
    }

    public function test_following_a_category_a_person_and_a_word_and_stopping(): void
    {
        $me = $this->member();
        $person = Person::create(['name' => 'Shamim Saifullah']);

        $this->post(route('follow', ['category', $this->master->id]))->assertRedirect();
        $this->post(route('follow', ['person', $person->id]))->assertRedirect();
        $this->post(route('follow.keyword'), ['keyword' => '  Ibn   Battuta '])->assertRedirect();

        $this->assertTrue(Following::is($me, 'category', $this->master->id));
        $this->assertTrue(Following::is($me, 'person', $person->id));
        $this->assertTrue(Following::is($me, 'keyword', 'ibn battuta'), 'a word is kept in small letters with single spaces');

        $this->post(route('follow', ['category', $this->master->id]));
        $this->assertFalse(Following::is($me, 'category', $this->master->id), 'the same button again stops following');
        $this->post(route('follow.keyword'), ['keyword' => 'Ibn Battuta']);
        $this->assertTrue(Following::is($me, 'keyword', 'ibn battuta'), 'adding a word again does not take it away');
        $this->post(route('follow.keyword'), ['keyword' => 'ibn battuta', 'toggle' => 1]);
        $this->assertFalse(Following::is($me, 'keyword', 'ibn battuta'));
    }

    public function test_a_made_up_target_a_hidden_category_and_too_many_are_refused(): void
    {
        $me = $this->member();
        $hidden = Category::create(['name' => ['en' => 'Hidden'], 'slug' => 'hidden', 'is_visible' => false]);

        $this->post(route('follow', ['category', 99999]))->assertRedirect();
        $this->post(route('follow', ['category', $hidden->id]))->assertRedirect();
        $this->assertSame(0, Follow::count());

        for ($i = 0; $i < Follow::MAX; $i++) {
            Follow::create(['user_id' => $me->id, 'kind' => 'keyword', 'keyword' => 'word '.$i]);
        }
        $this->post(route('follow', ['category', $this->master->id]));
        $this->assertFalse(Following::is($me, 'category', $this->master->id), 'a member can follow only '.Follow::MAX.' things');
    }

    public function test_a_guest_cannot_follow_and_the_way_back_cannot_leave_the_site(): void
    {
        $this->post(route('follow', ['category', $this->master->id]))->assertRedirect(route('login'));

        $this->member();
        $this->post(route('follow', ['category', $this->master->id]), ['back' => 'https://evil.example/x'])->assertRedirect()->assertRedirectContains('localhost');
        $this->post(route('follow', ['category', $this->leaf->id]), ['back' => '//evil.example/x'])->assertRedirectContains('localhost');
        $this->post(route('follow', ['category', $this->leaf->id]), ['back' => '/history'])->assertRedirect('/history');
    }

    public function test_the_follow_button_is_a_login_link_for_a_guest_and_a_button_for_a_member(): void
    {
        $this->get(route('category.show', 'history'))->assertOk()->assertSee('data-follow="category"', false)->assertSee('Log in to follow');

        $this->member();
        $this->get(route('category.show', 'history'))->assertOk()->assertSee('+ Follow')->assertSee(route('follow', ['category', $this->master->id]), false);
        $this->post(route('follow', ['category', $this->master->id]));
        $this->get(route('category.show', 'history'))->assertSee('✓ Following');
    }

    public function test_the_following_page_lists_what_is_followed_and_the_email_choice_is_saved(): void
    {
        $me = $this->member();
        $this->post(route('follow', ['category', $this->master->id]));
        $this->post(route('follow.keyword'), ['keyword' => 'hijra']);

        $this->get(route('account.following'))->assertOk()->assertSee('History')->assertSee('hijra')->assertSee('Stop following')->assertSee('Send me one email a week');

        $this->post(route('account.digest'), ['digest' => '0']);
        $this->assertFalse($me->fresh()->custom_data['digest']);
        $this->post(route('account.digest'), ['digest' => '1']);
        $this->assertTrue($me->fresh()->custom_data['digest']);
    }

    public function test_the_follow_switch_in_site_controls_turns_it_all_off(): void
    {
        $this->member();
        \App\Models\Setting::put('ctl_follow', '0');

        $this->get(route('category.show', 'history'))->assertDontSee('data-follow="category"', false);
        $this->post(route('follow', ['category', $this->master->id]))->assertForbidden();
        $this->get(route('account.following'))->assertNotFound();
    }
}
