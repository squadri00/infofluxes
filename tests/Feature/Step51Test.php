<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\RecentChanges;
use App\Filament\Widgets\SiteNumbers;
use App\Filament\Widgets\WaitingForYou;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\LinkCheck;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 51: the dashboard boxes: Waiting for you, Needs attention, Site numbers, Recently changed. */
class Step51Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function as(string $role, array $screens = []): User
    {
        $u = User::factory()->create(['role' => $role, 'status' => 'active', 'admin_screens' => $screens ?: null]);
        $this->actingAs($u);

        return $u;
    }

    private function article(string $slug, string $status, array $extra = []): Article
    {
        return Article::create(array_merge(['title' => ['en' => 'T '.$slug], 'slug' => $slug, 'category_id' => $this->cat->id, 'status' => $status], $extra));
    }

    public function test_each_role_sees_its_own_boxes_on_the_dashboard(): void
    {
        $this->as('superuser');
        $this->get('/admin')->assertOk()->assertSee('Waiting for you')->assertSee('Needs attention')->assertSee('Site numbers')->assertSee('Recently changed');

        $this->as('moderator');
        $this->get('/admin')->assertOk()->assertSee('Waiting for you')->assertSee('Needs attention')->assertSee('Site numbers')->assertDontSee('Recently changed');

        $this->as('author');
        $this->get('/admin')->assertOk()->assertSee('Waiting for you')->assertDontSee('Needs attention')->assertDontSee('Site numbers')->assertDontSee('Recently changed');
    }

    public function test_the_boxes_check_the_role_themselves(): void
    {
        $this->as('author');
        $this->assertTrue(WaitingForYou::canView());
        $this->assertFalse(NeedsAttention::canView());
        $this->assertFalse(SiteNumbers::canView());
        $this->assertFalse(RecentChanges::canView());

        $this->as('moderator');
        $this->assertTrue(NeedsAttention::canView());
        $this->assertFalse(RecentChanges::canView());

        $this->as('member');
        $this->assertFalse(WaitingForYou::canView(), 'a member has no admin area');
    }

    // ---------- Waiting for you ----------

    public function test_waiting_for_you_counts_what_needs_a_decision(): void
    {
        $author = $this->as('moderator');
        $this->article('w1', 'written');
        $this->article('w2', 'written');
        $this->article('r1', 'in_review');
        $this->article('d1', 'draft');
        $this->article('p1', 'published');
        $u = User::factory()->create();
        $a = $this->article('c', 'published');
        Comment::create(['article_id' => $a->id, 'user_id' => $u->id, 'body' => 'x', 'status' => 'pending']);
        Comment::create(['article_id' => $a->id, 'user_id' => $u->id, 'body' => 'y', 'status' => 'approved']);

        $data = Livewire::test(WaitingForYou::class)->instance();
        $tiles = collect((new \ReflectionMethod($data, 'getViewData'))->invoke($data)['tiles'])->keyBy('label');

        $this->assertSame(2, $tiles['Written, waiting for review']['count']);
        $this->assertSame(1, $tiles['Sent in, waiting for a moderator']['count']);
        $this->assertSame(1, $tiles['Comments waiting for approval']['count']);
    }

    public function test_an_author_sees_only_their_own_articles_and_only_their_screens(): void
    {
        $me = $this->as('author');
        $this->article('mine', 'written', ['author_id' => $me->id]);
        $this->article('theirs', 'written', ['author_id' => User::factory()->create()->id]);

        $data = Livewire::test(WaitingForYou::class)->instance();
        $tiles = collect((new \ReflectionMethod($data, 'getViewData'))->invoke($data)['tiles'])->keyBy('label');

        $this->assertSame(1, $tiles['Your articles, written and waiting']['count']);
        $this->assertArrayNotHasKey('Comments waiting for approval', $tiles->all(), 'an author does not have the Comments screen');
    }

    public function test_a_tile_opens_the_list_already_filtered(): void
    {
        $this->as('moderator');
        $written = $this->article('w', 'written');
        $draft = $this->article('d', 'draft');

        $data = Livewire::test(WaitingForYou::class)->instance();
        $tile = collect((new \ReflectionMethod($data, 'getViewData'))->invoke($data)['tiles'])->firstWhere('label', 'Written, waiting for review');
        parse_str((string) parse_url($tile['url'], PHP_URL_QUERY), $query);

        Livewire::withQueryParams($query)->test(ListArticles::class)->assertCanSeeTableRecords([$written])->assertCanNotSeeTableRecords([$draft]);
    }

    public function test_the_box_says_so_when_nothing_is_waiting_for_the_person(): void
    {
        $this->as('author', ['stories']);   // no Articles screen, no Comments screen

        Livewire::test(WaitingForYou::class)->assertSee('Nothing is waiting for you.');
    }

    // ---------- Needs attention ----------

    public function test_needs_attention_is_quiet_when_all_is_well(): void
    {
        $this->as('superuser');

        Livewire::test(NeedsAttention::class)->assertSee('Nothing needs attention. All is well.');
    }

    public function test_needs_attention_shows_only_the_problems_that_exist(): void
    {
        $this->as('superuser');
        $a = $this->article('a', 'published');
        LinkCheck::create(['article_id' => $a->id, 'url' => 'https://x.example.org/1', 'url_hash' => sha1('1'), 'status' => 'broken']);
        LinkCheck::create(['article_id' => $a->id, 'url' => 'https://x.example.org/2', 'url_hash' => sha1('2'), 'status' => 'blocked']);
        LinkCheck::create(['article_id' => $a->id, 'url' => 'https://x.example.org/3', 'url_hash' => sha1('3'), 'status' => 'blocked', 'manual_ok_at' => now()]);   // checked by hand: not a problem
        LinkCheck::create(['article_id' => $a->id, 'url' => 'https://x.example.org/4', 'url_hash' => sha1('4'), 'status' => 'broken', 'ignored' => true]);          // ignored: not a problem
        $old = $this->article('old', 'written');
        Article::whereKey($old->id)->toBase()->update(['updated_at' => now()->subDays(20)]);
        $this->article('fresh', 'written');
        $this->article('noname', 'published', ['ai_assisted' => true]);
        $this->article('named', 'published', ['ai_assisted' => true, 'reviewer_name' => 'Dr Check']);
        Setting::put('backup_last_errors', 'Files: the zip failed');

        $component = Livewire::test(NeedsAttention::class);
        $tiles = collect((new \ReflectionMethod($component->instance(), 'getViewData'))->invoke($component->instance())['tiles'])->keyBy('label');

        $this->assertSame(1, $tiles['Broken source links']['count']);
        $this->assertSame(1, $tiles['Source links to open yourself']['count']);
        $this->assertSame(1, $tiles['Written, not reviewed for 14+ days']['count']);
        $this->assertSame(1, $tiles['Published with AI help, no reviewer name']['count']);
        $this->assertSame(1, $tiles['The last automatic backup had a problem']['count']);
        $component->assertDontSee('All is well');
    }

    public function test_the_backup_problem_is_shown_to_the_super_user_only(): void
    {
        Setting::put('backup_last_errors', 'Files: the zip failed');
        $this->as('moderator');

        $component = Livewire::test(NeedsAttention::class);

        $component->assertDontSee('The last automatic backup had a problem');
    }

    // ---------- Site numbers ----------

    public function test_site_numbers_show_how_far_the_index_is_written(): void
    {
        $this->as('moderator');
        foreach (['blank1', 'blank2', 'blank3'] as $slug) {
            $this->article($slug, 'draft', ['is_index_line' => true]);
        }
        $this->article('done', 'written', ['is_index_line' => true, 'long_detail' => ['en' => '<p>Words.</p>']]);
        $this->article('gone', 'archived', ['is_index_line' => true]);   // archived entries are not part of the plan
        $this->article('pub', 'published');

        $component = Livewire::test(SiteNumbers::class);

        $component->assertSee('1 of 4')->assertSee('25% of the planned index has text.')->assertSee('Published articles');
    }

    // ---------- Recently changed ----------

    public function test_recently_changed_lists_the_latest_lines_and_links_the_history(): void
    {
        $me = $this->as('superuser');
        $a = $this->article('shown', 'draft');
        $a->update(['status' => 'published']);

        Livewire::test(RecentChanges::class)->assertSee($me->name)->assertSee('changed')->assertSee('T shown')->assertSee('Open the whole History');
    }

    public function test_recently_changed_has_an_empty_state(): void
    {
        $this->as('superuser');
        Activity::query()->delete();

        Livewire::test(RecentChanges::class)->assertSee('Nothing has been changed yet.');
    }

    public function test_recently_changed_shows_at_most_eight_lines(): void
    {
        $this->as('superuser');
        foreach (range(1, 12) as $i) {
            $this->article('x'.$i, 'draft');
        }

        $component = Livewire::test(RecentChanges::class);
        $lines = (new \ReflectionMethod($component->instance(), 'getViewData'))->invoke($component->instance())['lines'];

        $this->assertCount(8, $lines);
    }
}
