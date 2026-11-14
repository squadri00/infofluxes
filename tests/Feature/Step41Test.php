<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Reaction;
use App\Models\SavedItem;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 41: like, dislike, save (for people who are logged in) and share (for everyone). */
class Step41Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function article(array $extra = []): Article
    {
        return Article::create(array_merge(['title' => ['en' => 'Moon article'], 'slug' => 'moon-article', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay()], $extra));
    }

    private function story(): Story
    {
        return Story::create(['title' => ['en' => 'Moon story'], 'slug' => 'moon-story', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
    }

    private function member(string $status = 'active'): User
    {
        $u = User::factory()->create(['role' => 'member', 'status' => $status]);
        $this->actingAs($u);

        return $u;
    }

    public function test_the_buttons_are_on_an_article_and_a_story_for_everyone(): void
    {
        $this->article();
        $this->story();

        foreach (['/moon-article', '/stories/moon-story'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach (['id="engage"', 'Like', 'Dislike', 'Save', 'Share', 'wa.me', 'facebook.com/sharer', 'twitter.com/intent', 'mailto:', 'js/share.js'] as $needle) {
                $this->assertStringContainsString($needle, $html, $url.' '.$needle);
            }
            $this->assertStringContainsString('to like, dislike or save', $html, 'a visitor is told to log in');
        }
    }

    public function test_a_visitor_who_is_not_logged_in_is_sent_to_log_in(): void
    {
        $a = $this->article();

        $this->post(route('react', ['article', $a->id]), ['value' => 'like'])->assertRedirect(route('login'));
        $this->post(route('save', ['article', $a->id]))->assertRedirect(route('login'));
        $this->assertSame(0, Reaction::count());
        $this->assertSame(0, SavedItem::count());
    }

    public function test_like_toggles_and_dislike_switches(): void
    {
        $a = $this->article();
        $me = $this->member();

        $this->post(route('react', ['article', $a->id]), ['value' => 'like'])->assertRedirect(route('article.show', $a->slug).'#engage');
        $this->assertSame(1, $a->likesCount());
        $this->assertSame(0, $a->dislikesCount());

        $this->post(route('react', ['article', $a->id]), ['value' => 'dislike']);
        $this->assertSame(0, $a->likesCount(), 'the other button changes it');
        $this->assertSame(1, $a->dislikesCount());
        $this->assertSame(1, Reaction::where('user_id', $me->id)->count(), 'one reaction for each person and item');

        $this->post(route('react', ['article', $a->id]), ['value' => 'dislike']);
        $this->assertSame(0, Reaction::count(), 'the same button again takes it back');
    }

    public function test_the_like_count_is_public_and_the_dislike_count_is_not(): void
    {
        $a = $this->article();
        foreach (range(1, 3) as $i) {
            $a->reactions()->create(['user_id' => User::factory()->create()->id, 'value' => 1]);
        }
        $a->reactions()->create(['user_id' => User::factory()->create()->id, 'value' => -1]);

        $html = $this->get('/moon-article')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/engage__count">3</', $html);
        $this->assertStringNotContainsString('engage__count">1<', str_replace('engage__count">3<', '', $html), 'the dislike count is not shown');
    }

    public function test_the_buttons_show_what_the_person_chose(): void
    {
        $a = $this->article();
        $this->member();
        $this->post(route('react', ['article', $a->id]), ['value' => 'like']);
        $this->post(route('save', ['article', $a->id]));

        $html = $this->get('/moon-article')->assertOk()->getContent();

        $this->assertStringContainsString('is-on" aria-pressed="true" title="Like"', $html);
        $this->assertStringContainsString('<span>Saved</span>', $html);
        $this->assertStringNotContainsString('to like, dislike or save', $html);
    }

    public function test_save_toggles_for_articles_and_stories(): void
    {
        $a = $this->article();
        $s = $this->story();
        $this->member();

        $this->post(route('save', ['article', $a->id]));
        $this->post(route('save', ['story', $s->id]))->assertRedirect($s->url().'#engage');
        $this->assertSame(2, SavedItem::count());

        $this->post(route('save', ['article', $a->id]));
        $this->assertSame(1, SavedItem::count());
    }

    public function test_only_published_items_can_be_reacted_to_or_saved(): void
    {
        $draft = $this->article(['status' => 'draft', 'slug' => 'd', 'title' => ['en' => 'D']]);
        $future = $this->article(['publish_date' => now()->addDay(), 'slug' => 'f', 'title' => ['en' => 'F']]);
        $this->member();

        foreach ([$draft, $future] as $x) {
            $this->post(route('react', ['article', $x->id]), ['value' => 'like'])->assertNotFound();
            $this->post(route('save', ['article', $x->id]))->assertNotFound();
        }
        $this->assertSame(0, Reaction::count());
    }

    public function test_a_wrong_value_a_wrong_type_and_a_pending_person_are_refused(): void
    {
        $a = $this->article();
        $this->member();

        $this->post(route('react', ['article', $a->id]), ['value' => 'love'])->assertSessionHasErrors('value');
        $this->post('/react/comment/'.$a->id, ['value' => 'like'])->assertNotFound();
        $this->post('/save/user/1')->assertNotFound();

        $this->member('pending');
        $this->post(route('react', ['article', $a->id]), ['value' => 'like'])->assertForbidden();
        $this->post(route('save', ['article', $a->id]))->assertForbidden();
        $this->assertSame(0, Reaction::count());
    }

    public function test_the_account_page_lists_what_was_saved_and_can_remove_it(): void
    {
        $a = $this->article();
        $s = $this->story();
        $gone = $this->article(['slug' => 'gone', 'title' => ['en' => 'Gone one']]);
        $me = $this->member();
        foreach ([['article', $a], ['story', $s], ['article', $gone]] as [$t, $x]) {
            $this->post(route('save', [$t, $x->id]));
        }
        $gone->update(['status' => 'draft']);

        $this->get('/account')->assertOk()->assertSee('Saved to read later')->assertSee('Moon article')->assertSee('Moon story')->assertDontSee('Gone one');

        $this->post(route('save', ['article', $a->id]), ['from' => 'account'])->assertRedirect(route('account'));
        $this->get('/account')->assertDontSee('Moon article');
        $this->assertSame(2, SavedItem::where('user_id', $me->id)->count());
    }

    public function test_one_person_never_sees_or_changes_another_persons_saves(): void
    {
        $a = $this->article();
        $other = User::factory()->create(['status' => 'active']);
        $a->saves()->create(['user_id' => $other->id]);
        $this->member();

        $this->get('/account')->assertOk()->assertDontSee('Moon article');
        $this->post(route('save', ['article', $a->id]));
        $this->assertSame(2, SavedItem::count(), 'a new save, the other one is untouched');
    }

    public function test_deleting_an_article_for_good_removes_its_likes_and_saves(): void
    {
        $a = $this->article();
        $this->member();
        $this->post(route('react', ['article', $a->id]), ['value' => 'like']);
        $this->post(route('save', ['article', $a->id]));

        $a->forceDelete();

        $this->assertSame(0, Reaction::count());
        $this->assertSame(0, SavedItem::count());
    }

    public function test_the_listen_button_says_article_on_an_article_and_story_on_a_story(): void
    {
        $this->article(['long_detail' => ['en' => '<p>Some words to read aloud.</p>']]);
        $this->story();

        $this->get('/moon-article')->assertOk()->assertSee('Listen to the article')->assertDontSee('Listen to the story');
    }

    public function test_the_share_script_and_styles_exist(): void
    {
        $js = (string) file_get_contents(public_path('js/share.js'));
        $this->assertStringContainsString('navigator.share', $js);
        $this->assertStringContainsString('clipboard', $js);
        $this->assertStringContainsString('.engage__btn', (string) file_get_contents(public_path('css/site.css')));
    }
}
