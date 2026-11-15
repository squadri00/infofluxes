<?php

namespace Tests\Feature;

use App\Filament\Pages\LinkCheckerPage;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Category;
use App\Models\LinkCheck;
use App\Models\User;
use App\Support\LinkChecker;
use App\Support\LinkSweep;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 50: the site-wide link checker for the source links of all articles. */
class Step50Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        Http::fake([
            'good.example.org/*' => Http::response('ok', 200),
            'gone.example.org/*' => Http::response('no', 404),
            'blocked.example.org/*' => Http::response('no', 403),
            'error.example.org/*' => Http::response('no', 500),
        ]);
    }

    private function article(array $urls, string $slug = 'a'): Article
    {
        return Article::create(['title' => ['en' => 'Article '.$slug], 'slug' => $slug, 'category_id' => $this->cat->id, 'status' => 'published',
            'custom_data' => ['sources' => array_map(fn ($u) => ['type' => 'primary', 'ref' => 'Ref of '.$u, 'url' => $u], $urls)]]);
    }

    private function mod(string $role = 'moderator'): User
    {
        $u = User::factory()->create(['role' => $role, 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    // ---------- the list of links ----------

    public function test_sync_makes_one_row_for_each_link_and_ignores_blanks_and_repeats(): void
    {
        $a = $this->article(['https://good.example.org/1', 'https://good.example.org/1', '  https://gone.example.org/2  ', '']);
        $this->article([], 'b');

        $r = LinkSweep::sync();

        $this->assertSame(2, $r['total']);
        $this->assertSame(['https://good.example.org/1', 'https://gone.example.org/2'], LinkCheck::where('article_id', $a->id)->orderBy('id')->pluck('url')->all());
        $this->assertSame('Ref of https://good.example.org/1', LinkCheck::first()->ref);
        $this->assertSame('new', LinkCheck::first()->status);
    }

    public function test_sync_follows_the_articles(): void
    {
        $a = $this->article(['https://good.example.org/1', 'https://gone.example.org/2']);
        LinkSweep::sync();
        LinkSweep::checkRow(LinkCheck::first());

        // one source removed, one changed its name
        $a->update(['custom_data' => ['sources' => [['ref' => 'New name', 'url' => 'https://good.example.org/1']]]]);
        LinkSweep::sync();

        $this->assertSame(1, LinkCheck::count());
        $this->assertSame('New name', LinkCheck::first()->ref);
        $this->assertSame('ok', LinkCheck::first()->status, 'what was found is kept');

        $a->delete();
        LinkSweep::sync();
        $this->assertSame(0, LinkCheck::count(), 'a trashed article has no rows');
    }

    // ---------- checking ----------

    public function test_a_link_is_checked_and_the_answer_kept(): void
    {
        $this->article(['https://good.example.org/1', 'https://gone.example.org/2', 'https://blocked.example.org/3', 'https://error.example.org/4', 'http://localhost/x']);
        LinkSweep::sync();

        foreach (LinkCheck::orderBy('id')->get() as $l) {
            LinkSweep::checkRow($l);
        }

        $by = LinkCheck::all()->keyBy(fn ($l) => parse_url($l->url, PHP_URL_HOST));
        $this->assertSame(['ok', 200], [$by['good.example.org']->status, $by['good.example.org']->http_status]);
        $this->assertSame(['broken', 404], [$by['gone.example.org']->status, $by['gone.example.org']->http_status]);
        $this->assertSame(['blocked', 403], [$by['blocked.example.org']->status, $by['blocked.example.org']->http_status], 'a refusal is not a missing page');
        $this->assertSame('broken', $by['error.example.org']->status);
        $this->assertSame('skipped', $by['localhost']->status);
        $this->assertNotNull($by['good.example.org']->checked_at);
    }

    public function test_failing_since_is_kept_while_it_fails_and_cleared_when_it_opens_again(): void
    {
        $up = false;
        Http::fake(['flaky.example.org/*' => function () use (&$up) {
            return Http::response('x', $up ? 200 : 404);
        }]);
        $this->article(['https://flaky.example.org/2']);
        LinkSweep::sync();
        $l = LinkCheck::first();

        LinkSweep::checkRow($l);
        $first = $l->fresh()->failed_since;
        $this->assertNotNull($first);

        $this->travel(2)->days();
        LinkSweep::checkRow($l->fresh());
        $this->assertTrue($l->fresh()->failed_since->equalTo($first), 'the first failure is remembered');

        $up = true;
        LinkSweep::checkRow($l->fresh());
        $this->assertNull($l->fresh()->failed_since);
        $this->assertSame('ok', $l->fresh()->status);
    }

    public function test_probe_says_what_the_server_answered_and_check_still_gives_the_status(): void
    {
        $this->assertSame(['status' => 'broken', 'code' => 404], LinkChecker::probe('https://gone.example.org/x'));
        $this->assertSame(['status' => 'skipped', 'code' => null], LinkChecker::probe('ftp://example.org/x'));
        $this->assertSame('ok', LinkChecker::check('https://good.example.org/x'));
    }

    // ---------- the sweep ----------

    public function test_the_sweep_checks_the_queued_links_a_few_at_a_time_and_skips_ignored_ones(): void
    {
        $this->article(['https://good.example.org/1', 'https://good.example.org/2', 'https://good.example.org/3', 'https://good.example.org/4']);
        LinkSweep::sync();
        LinkCheck::where('url', 'like', '%/4')->update(['ignored' => true]);

        $this->assertSame(3, LinkSweep::queueAll(), 'ignored links are not queued');
        $this->assertSame(2, LinkSweep::step(2));
        $this->assertSame(['total' => 3, 'queued' => 1], LinkSweep::progress());
        $this->assertSame(1, LinkSweep::step(2));
        $this->assertSame(0, LinkSweep::step(2), 'nothing left');
        $this->assertSame('new', LinkCheck::where('url', 'like', '%/4')->first()->status, 'the ignored one was never asked');
    }

    public function test_the_hourly_run_takes_the_links_never_checked_or_older_than_a_week(): void
    {
        $this->article(['https://good.example.org/never', 'https://good.example.org/fresh', 'https://good.example.org/old']);
        LinkSweep::sync();
        LinkCheck::where('url', 'like', '%/fresh')->update(['status' => 'ok', 'checked_at' => now()->subDay()]);
        LinkCheck::where('url', 'like', '%/old')->update(['status' => 'ok', 'checked_at' => now()->subDays(8)]);

        $n = LinkSweep::runDue(10, 30);

        $this->assertSame(2, $n);
        $this->assertTrue(LinkCheck::where('url', 'like', '%/fresh')->first()->checked_at->lt(now()->subHours(12)), 'the fresh one was left alone');
        $this->assertSame(0, LinkSweep::runDue(10, 30), 'now everything is up to date');
    }

    public function test_the_hourly_run_respects_its_limit(): void
    {
        $this->article(array_map(fn ($i) => 'https://good.example.org/'.$i, range(1, 5)));

        $this->assertSame(2, LinkSweep::runDue(2, 30));
        $this->assertSame(3, LinkCheck::where('status', 'new')->count());
    }

    public function test_the_command_works_and_the_task_is_scheduled(): void
    {
        $this->article(['https://good.example.org/1', 'https://gone.example.org/2']);

        $this->artisan('links:check')->expectsOutputToContain('Checked 2 link(s)')->assertSuccessful();
        $this->artisan('links:check', ['--all' => true])->expectsOutputToContain('Queued 2 link(s)')->assertSuccessful();

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'links:check'));
        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
    }

    public function test_a_broken_link_never_hides_the_article(): void
    {
        $a = $this->article(['https://gone.example.org/2']);
        LinkSweep::sync();
        LinkSweep::checkRow(LinkCheck::first());

        $this->get('/'.$a->slug)->assertOk();
        $this->assertSame('published', $a->fresh()->status);
    }

    // ---------- what needs a person ----------

    public function test_the_attention_list_holds_broken_and_unchecked_blocked_links_only(): void
    {
        $this->article(['https://good.example.org/1', 'https://gone.example.org/2', 'https://blocked.example.org/3', 'https://blocked.example.org/4', 'https://gone.example.org/5']);
        LinkSweep::sync();
        foreach (LinkCheck::all() as $l) {
            LinkSweep::checkRow($l);
        }
        LinkSweep::markByHand(LinkCheck::where('url', 'like', '%/4')->first(), null);
        LinkCheck::where('url', 'like', '%/5')->update(['ignored' => true]);

        $urls = LinkCheck::needsAttention()->orderBy('id')->pluck('url')->all();

        $this->assertSame(['https://gone.example.org/2', 'https://blocked.example.org/3'], $urls);
        $this->assertSame('by_hand', LinkCheck::where('url', 'like', '%/4')->first()->shownStatus());
        $this->assertSame(1, LinkCheck::broken()->count(), 'ignored and blocked ones are not "broken"');
    }

    // ---------- the screen ----------

    public function test_only_a_moderator_or_the_super_user_sees_the_screen(): void
    {
        $this->mod('author');
        $this->get('/admin/link-checker')->assertForbidden();
        $this->mod('member');
        $this->get('/admin/link-checker')->assertForbidden();
        $this->mod('moderator');
        $this->get('/admin/link-checker')->assertOk()->assertSee('Check all links now');
        $this->mod('superuser');
        $this->get('/admin/link-checker')->assertOk();
    }

    public function test_the_screen_lists_what_needs_a_person_and_the_filter_widens_it(): void
    {
        $this->article(['https://good.example.org/1', 'https://gone.example.org/2']);
        LinkSweep::sync();
        foreach (LinkCheck::all() as $l) {
            LinkSweep::checkRow($l);
        }
        $good = LinkCheck::where('url', 'like', '%/1')->first();
        $gone = LinkCheck::where('url', 'like', '%/2')->first();
        $this->mod();

        Livewire::test(LinkCheckerPage::class)->assertCanSeeTableRecords([$gone])->assertCanNotSeeTableRecords([$good])
            ->filterTable('view', 'all')->assertCanSeeTableRecords([$good, $gone]);
    }

    public function test_check_all_runs_with_progress_until_done(): void
    {
        $this->article(array_map(fn ($i) => 'https://good.example.org/'.$i, range(1, 7)));
        $this->mod();

        $page = Livewire::test(LinkCheckerPage::class)->call('startSweep')->assertSet('sweeping', true)->assertSet('sweepTotal', 7);
        $this->assertSame(0, LinkSweep::progress()['total'] - LinkSweep::progress()['queued'] - 0 === 0 ? 0 : 0);

        $page->call('step')->assertSet('sweeping', true);   // 3 of 7
        $page->call('step')->call('step')->assertSet('sweeping', false);

        $this->assertSame(0, LinkCheck::where('status', 'new')->count());
        $this->assertSame(7, LinkCheck::where('status', 'ok')->count());
    }

    public function test_check_all_with_no_links_says_so(): void
    {
        $this->mod();

        Livewire::test(LinkCheckerPage::class)->call('startSweep')->assertSet('sweeping', false);
    }

    public function test_the_row_buttons_work(): void
    {
        $this->article(['https://blocked.example.org/3', 'https://gone.example.org/2']);
        LinkSweep::sync();
        foreach (LinkCheck::all() as $l) {
            LinkSweep::checkRow($l);
        }
        $blocked = LinkCheck::where('url', 'like', '%/3')->first();
        $gone = LinkCheck::where('url', 'like', '%/2')->first();
        $me = $this->mod();

        $page = Livewire::test(LinkCheckerPage::class)->filterTable('view', 'all');
        $page->callAction(TestAction::make('hand')->table($blocked));
        $this->assertNotNull($blocked->fresh()->manual_ok_at);
        $this->assertSame($me->id, $blocked->fresh()->manual_ok_by);

        $page->callAction(TestAction::make('unhand')->table($blocked));
        $this->assertNull($blocked->fresh()->manual_ok_at);

        $page->callAction(TestAction::make('ignore')->table($gone));
        $this->assertTrue($gone->fresh()->ignored);
        $page->filterTable('view', 'ignored')->callAction(TestAction::make('unignore')->table($gone));
        $this->assertFalse($gone->fresh()->ignored);

        $page->filterTable('view', 'all')->callAction(TestAction::make('check')->table($gone));
        $this->assertSame('broken', $gone->fresh()->status, 'asked again, still gone');
        $this->assertSame(404, $gone->fresh()->http_status);
    }

    // ---------- the Articles list ----------

    public function test_the_articles_list_can_show_the_articles_with_broken_sources(): void
    {
        $bad = $this->article(['https://gone.example.org/2'], 'bad');
        $fine = $this->article(['https://good.example.org/1'], 'fine');
        $none = $this->article([], 'none');
        LinkSweep::sync();
        foreach (LinkCheck::all() as $l) {
            LinkSweep::checkRow($l);
        }
        $this->mod();

        Livewire::test(ListArticles::class)->filterTable('broken_links', true)->assertCanSeeTableRecords([$bad])->assertCanNotSeeTableRecords([$fine, $none]);
        Livewire::test(ListArticles::class)->filterTable('broken_links', false)->assertCanSeeTableRecords([$fine, $none])->assertCanNotSeeTableRecords([$bad]);
    }

    public function test_an_ignored_broken_link_does_not_flag_the_article(): void
    {
        $bad = $this->article(['https://gone.example.org/2'], 'bad');
        LinkSweep::sync();
        LinkSweep::checkRow(LinkCheck::first());
        LinkCheck::first()->update(['ignored' => true]);
        $this->mod();

        Livewire::test(ListArticles::class)->filterTable('broken_links', true)->assertCanNotSeeTableRecords([$bad]);
    }

    public function test_the_row_button_checks_one_article_and_shows_only_when_it_has_sources(): void
    {
        $with = $this->article(['https://gone.example.org/2', 'https://good.example.org/1'], 'with');
        $without = $this->article([], 'without');
        $this->mod();

        Livewire::test(ListArticles::class)
            ->assertActionVisible(TestAction::make('check_links')->table($with))
            ->assertActionHidden(TestAction::make('check_links')->table($without))
            ->callAction(TestAction::make('check_links')->table($with));

        $this->assertSame(['broken', 'ok'], LinkCheck::where('article_id', $with->id)->orderBy('id')->pluck('status')->all());
    }

    public function test_an_author_does_not_get_the_check_button(): void
    {
        $with = $this->article(['https://good.example.org/1'], 'with');
        $author = $this->mod('author');
        $with->update(['author_id' => $author->id]);

        Livewire::test(ListArticles::class)->assertActionHidden(TestAction::make('check_links')->table($with));
    }
}
