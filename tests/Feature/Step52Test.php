<?php

namespace Tests\Feature;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Page;
use App\Models\Person;
use App\Models\Redirect;
use App\Models\Story;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 52: redirects. An old address keeps working when an address is changed, and the super user can add some by hand. */
class Step52Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        Redirect::query()->delete();
    }

    private function article(string $slug, string $status = 'published'): Article
    {
        return Article::create(['title' => ['en' => 'T '.$slug], 'slug' => $slug, 'category_id' => $this->cat->id, 'status' => $status, 'publish_date' => now()->subDay()]);
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    // ---------- the shape of an address ----------

    public function test_an_address_is_always_kept_in_the_same_shape(): void
    {
        $this->assertSame('/old-one', Redirect::normalize('old-one'));
        $this->assertSame('/old-one', Redirect::normalize('/old-one/'));
        $this->assertSame('/old-one', Redirect::normalize('  /old-one/?x=1#top  '));
        $this->assertSame('/a/b', Redirect::normalize('a/b'));
        $this->assertSame('/', Redirect::normalize(''));
        $this->assertTrue(Redirect::isExternal('https://example.org/x'));
        $this->assertFalse(Redirect::isExternal('/x'));
        $this->assertTrue(Redirect::isNever('/admin'));
        $this->assertTrue(Redirect::isNever('/Admin/articles'));
        $this->assertFalse(Redirect::isNever('/administration'));
    }

    public function test_saving_normalises_and_makes_the_look_up_key(): void
    {
        $r = Redirect::create(['from_path' => 'Old-One/', 'to' => 'new-one']);

        $this->assertSame('/Old-One', $r->from_path);
        $this->assertSame('/old-one', $r->from_key);
        $this->assertSame('/new-one', $r->to);
    }

    // ---------- made by themselves ----------

    public function test_changing_the_address_of_a_published_article_makes_a_redirect(): void
    {
        $a = $this->article('first-name');

        $a->update(['slug' => 'second-name']);

        $r = Redirect::first();
        $this->assertSame(['/first-name', '/second-name', 'auto', 301], [$r->from_path, $r->to, $r->type, $r->code]);
        $this->assertSame(Article::class, $r->subject_type);
        $this->assertSame($a->id, $r->subject_id);
    }

    public function test_a_draft_that_was_never_public_leaves_no_redirect(): void
    {
        $a = $this->article('draft-name', 'draft');

        $a->update(['slug' => 'other-name']);

        $this->assertSame(0, Redirect::count());
    }

    public function test_publishing_and_renaming_at_the_same_time_still_keeps_the_old_address(): void
    {
        $a = $this->article('was-draft', 'draft');

        $a->update(['slug' => 'now-public', 'status' => 'published']);

        $this->assertSame(['/was-draft'], Redirect::pluck('from_path')->all(), 'the address that was shared as a draft preview may be known');
    }

    public function test_a_second_change_moves_the_first_old_address_on_so_there_is_no_chain(): void
    {
        $a = $this->article('one');
        $a->update(['slug' => 'two']);
        $a->update(['slug' => 'three']);

        $this->assertEqualsCanonicalizing([['/one', '/three'], ['/two', '/three']], Redirect::get()->map(fn ($r) => [$r->from_path, $r->to])->all());
    }

    public function test_going_back_to_an_old_address_removes_the_redirect_that_started_there(): void
    {
        $a = $this->article('one');
        $a->update(['slug' => 'two']);
        $a->update(['slug' => 'one']);

        $this->assertSame([['/two', '/one']], Redirect::get()->map(fn ($r) => [$r->from_path, $r->to])->all());
    }

    public function test_a_new_record_that_takes_an_address_removes_the_redirect_that_started_there(): void
    {
        Redirect::create(['from_path' => '/taken', 'to' => '/somewhere', 'type' => 'manual']);

        $this->article('taken');

        $this->assertSame(0, Redirect::count());
    }

    public function test_every_kind_of_record_keeps_its_old_address(): void
    {
        $story = Story::create(['title' => ['en' => 'S'], 'slug' => 's-old', 'category_id' => $this->cat->id, 'status' => 'published']);
        $story->update(['slug' => 's-new']);
        $this->cat->update(['slug' => 'shows-new']);
        $page = Page::create(['title' => ['en' => 'P'], 'slug' => 'p-old']);
        $page->update(['slug' => 'p-new']);
        $person = Person::create(['name' => 'Dr X', 'slug' => 'x-old']);
        $person->update(['slug' => 'x-new']);
        $fact = DidYouKnow::create(['title' => ['en' => 'F'], 'body' => ['en' => 'Words'], 'slug' => 'f-old', 'status' => 'published']);
        $fact->update(['slug' => 'f-new']);

        $pairs = Redirect::orderBy('id')->get()->map(fn ($r) => [$r->from_path, $r->to])->all();

        $this->assertSame([
            ['/stories/s-old', '/stories/s-new'], ['/category/shows', '/category/shows-new'], ['/p-old', '/p-new'],
            ['/people/x-old', '/people/x-new'], ['/did-you-know/f-old', '/did-you-know/f-new'],
        ], $pairs);
    }

    public function test_the_same_slug_saved_again_makes_nothing(): void
    {
        $a = $this->article('same');

        $a->update(['status' => 'archived']);
        $a->touch();

        $this->assertSame(0, Redirect::count());
    }

    // ---------- on the site ----------

    public function test_the_old_address_leads_to_the_new_one_with_a_301(): void
    {
        $a = $this->article('old-name', 'published');
        $a->update(['slug' => 'new-name']);

        $this->get('/old-name')->assertStatus(301)->assertRedirect(url('/new-name'));
        $this->get('/new-name')->assertOk();
    }

    public function test_the_query_string_is_kept(): void
    {
        $this->cat->update(['slug' => 'shows-2']);

        $this->get('/category/shows?view=index')->assertStatus(301)->assertRedirect(url('/category/shows-2?view=index'));
    }

    public function test_an_address_that_matches_no_route_is_redirected_too(): void
    {
        Redirect::create(['from_path' => '/old/path.html', 'to' => '/new-place', 'type' => 'manual']);
        $this->article('new-place');

        $this->get('/old/path.html')->assertStatus(301)->assertRedirect(url('/new-place'));
        $this->get('/OLD/PATH.html')->assertStatus(301);   // capital letters make no difference
    }

    public function test_a_page_that_exists_is_never_replaced_by_a_redirect(): void
    {
        $this->article('alive');
        Redirect::query()->insert(['from_path' => '/alive', 'from_key' => '/alive', 'to' => '/elsewhere', 'type' => 'manual', 'code' => 301, 'is_active' => true, 'hits' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/alive')->assertOk();
    }

    public function test_an_unknown_address_is_still_not_found(): void
    {
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_the_hits_are_counted(): void
    {
        $a = $this->article('old-name');
        $a->update(['slug' => 'new-name']);

        $this->get('/old-name');
        $this->get('/old-name');

        $r = Redirect::first();
        $this->assertSame(2, $r->hits);
        $this->assertNotNull($r->last_hit_at);
    }

    public function test_a_redirect_that_is_switched_off_is_not_used(): void
    {
        Redirect::create(['from_path' => '/off', 'to' => '/somewhere', 'type' => 'manual', 'is_active' => false]);

        $this->get('/off')->assertNotFound();
    }

    public function test_a_redirect_can_lead_to_another_website_and_can_be_temporary(): void
    {
        Redirect::create(['from_path' => '/away', 'to' => 'https://example.org/page', 'type' => 'manual', 'code' => 302]);

        $this->get('/away?x=1')->assertStatus(302)->assertRedirect('https://example.org/page?x=1');
    }

    public function test_a_chain_is_followed_but_a_circle_is_not(): void
    {
        Redirect::create(['from_path' => '/a', 'to' => '/b', 'type' => 'manual']);
        Redirect::create(['from_path' => '/b', 'to' => '/c', 'type' => 'manual']);
        $this->article('c');
        Redirect::create(['from_path' => '/x', 'to' => '/y', 'type' => 'manual']);
        Redirect::create(['from_path' => '/y', 'to' => '/x', 'type' => 'manual']);

        $this->get('/a')->assertStatus(301)->assertRedirect(url('/c'));
        $this->get('/x')->assertNotFound();
    }

    public function test_only_get_and_head_are_redirected_and_the_admin_never(): void
    {
        Redirect::create(['from_path' => '/old', 'to' => '/new', 'type' => 'manual']);
        Redirect::query()->insert(['from_path' => '/admin/secret', 'from_key' => '/admin/secret', 'to' => '/elsewhere', 'type' => 'manual', 'code' => 301, 'is_active' => true, 'hits' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->post('/old')->assertStatus(405);
        $this->get('/admin/secret')->assertNotFound();   // the admin is never redirected, even by a row put in the table directly
        $this->assertSame(301, $this->call('HEAD', '/old')->getStatusCode());
    }

    // ---------- the screen ----------

    public function test_only_the_super_user_sees_the_screen(): void
    {
        foreach (['moderator', 'author', 'member'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active', 'admin_screens' => ['articles', 'import']]));
            $this->get('/admin/redirects')->assertForbidden();
        }
        $this->superuser();
        $this->get('/admin/redirects')->assertOk()->assertSee('New redirect');
    }

    public function test_a_redirect_can_be_made_by_hand_and_is_cleaned_up(): void
    {
        $this->superuser();

        Livewire::test(ManageRedirects::class)
            ->callAction('create', ['from_path' => 'Old-Page/', 'to' => 'new-page', 'code' => 301, 'is_active' => true])
            ->assertHasNoActionErrors();

        $r = Redirect::first();
        $this->assertSame(['/Old-Page', '/new-page', 'manual'], [$r->from_path, $r->to, $r->type]);
    }

    public function test_the_screen_refuses_the_bad_ones(): void
    {
        $this->superuser();
        Redirect::create(['from_path' => '/exists', 'to' => '/x', 'type' => 'manual']);
        Redirect::create(['from_path' => '/next', 'to' => '/start', 'type' => 'manual']);

        $try = fn (array $data) => Livewire::test(ManageRedirects::class)->callAction('create', $data + ['code' => 301, 'is_active' => true]);

        $try(['from_path' => '/', 'to' => '/x'])->assertHasActionErrors(['from_path']);
        $try(['from_path' => 'admin/articles', 'to' => '/x'])->assertHasActionErrors(['from_path']);
        $try(['from_path' => 'exists', 'to' => '/y'])->assertHasActionErrors(['from_path']);
        $try(['from_path' => 'same', 'to' => '/same/'])->assertHasActionErrors(['to']);
        $try(['from_path' => 'start', 'to' => '/next'])->assertHasActionErrors(['to']);   // /start -> /next -> /start: a circle
        $try(['from_path' => 'bad', 'to' => 'javascript:alert(1)'])->assertHasActionErrors(['to']);
        $this->assertSame(2, Redirect::count());
    }

    public function test_one_can_be_switched_off_and_deleted_from_the_screen(): void
    {
        $this->superuser();
        $r = Redirect::create(['from_path' => '/old', 'to' => '/new', 'type' => 'manual']);

        Livewire::test(ManageRedirects::class)->callAction(TestAction::make('delete')->table($r));

        $this->assertSame(0, Redirect::count());
    }

    public function test_the_changes_to_redirects_are_written_to_the_history(): void
    {
        $this->superuser();
        Activity::query()->delete();

        Redirect::create(['from_path' => '/old', 'to' => '/new', 'type' => 'manual']);

        $line = Activity::first();
        $this->assertSame(Redirect::class, $line->subject_type);
        $this->assertSame('/old → /new', $line->subject_label);
    }
}
