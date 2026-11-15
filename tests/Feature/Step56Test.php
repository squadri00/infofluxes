<?php

namespace Tests\Feature;

use App\Filament\Pages\Help;
use App\Models\User;
use App\Support\Guide;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 56: the Help guide of the admin, and the promise that it cannot fall behind. */
class Step56Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function as(string $role, array $screens = []): User
    {
        $u = User::factory()->create(['role' => $role, 'status' => 'active', 'admin_screens' => $screens ?: null]);
        $this->actingAs($u);

        return $u;
    }

    public function test_every_screen_of_the_admin_has_an_entry_in_the_guide(): void
    {
        $missing = array_keys(array_filter(Guide::coverage(), fn ($key) => $key === null));

        $this->assertSame([], $missing, 'Write a guide entry (resources/guide/guide.php) for: '.implode(', ', $missing));
    }

    public function test_every_entry_has_the_parts_it_needs_and_a_known_group_and_a_known_access(): void
    {
        $keys = [];
        foreach (Guide::all() as $e) {
            $this->assertNotSame('', trim($e['title']), $e['key']);
            $this->assertNotSame('', trim($e['what']), $e['key'].' says nothing');
            $this->assertContains($e['group'], ['General', 'Content', 'Challenge yourself', 'Settings'], $e['key']);
            $this->assertTrue(in_array($e['access'], [null, 'publish', 'superuser'], true) || isset(User::SCREENS[$e['access']]), $e['key'].' has an unknown access: '.$e['access']);
            $this->assertNotContains($e['key'], $keys, 'a key is used twice: '.$e['key']);
            $keys[] = $e['key'];
            foreach ($e['classes'] as $class) {
                $this->assertTrue(class_exists($class), $e['key'].' names a class that does not exist: '.$class);
            }
            foreach (array_merge($e['steps'] ?? [], $e['tips'] ?? []) as $line) {
                $this->assertNotSame('', trim($line), $e['key'].' has an empty line');
            }
        }
    }

    public function test_the_guide_names_the_things_a_person_needs_to_know_about_the_new_features(): void
    {
        $all = collect(Guide::all())->map(fn ($e) => $e['title'].' '.$e['what'].' '.implode(' ', $e['steps'] ?? []))->implode(' ');

        foreach (['AI import', 'Written, waiting for review', 'Link checker', 'Redirects', 'Backups', 'History', 'Explainers', '@[', 'Search synonyms', 'last-level category', 'Trash'] as $word) {
            $this->assertStringContainsString($word, $all, $word);
        }
    }

    public function test_a_person_sees_only_the_topics_of_the_screens_they_may_use(): void
    {
        $this->as('author');
        $keys = collect(Guide::for(auth()->user()))->flatten(1)->pluck('key')->all();
        $this->assertContains('start', $keys);
        $this->assertContains('articles', $keys);
        $this->assertContains('stories', $keys);
        $this->assertNotContains('backups', $keys);
        $this->assertNotContains('ai-import', $keys, 'an author has no AI import unless it is ticked');
        $this->assertNotContains('ai-prompts', $keys);

        $this->as('author', ['articles', 'import']);
        $this->assertContains('ai-import', collect(Guide::for(auth()->user()))->flatten(1)->pluck('key')->all());

        $this->as('moderator');
        $keys = collect(Guide::for(auth()->user()))->flatten(1)->pluck('key')->all();
        $this->assertContains('link-checker', $keys);
        $this->assertContains('search-synonyms', $keys);
        $this->assertContains('explainers', $keys);
        $this->assertNotContains('history', $keys);
        $this->assertNotContains('users', $keys);

        $this->as('superuser');
        $all = collect(Guide::for(auth()->user()))->flatten(1)->pluck('key')->all();
        $this->assertCount(count(Guide::all()), $all, 'the super user sees everything');
    }

    public function test_a_member_and_a_visitor_see_nothing(): void
    {
        $this->assertSame([], Guide::for(null));
        $this->assertSame([], Guide::for(User::factory()->create(['role' => 'member', 'status' => 'active'])));
        $this->assertSame([], Guide::for(User::factory()->create(['role' => 'moderator', 'status' => 'switched_off'])));
    }

    public function test_the_help_page_opens_for_staff_only(): void
    {
        $this->get('/admin/help')->assertRedirect();

        $this->as('member');
        $this->get('/admin/help')->assertForbidden();

        foreach (['author', 'moderator', 'superuser'] as $role) {
            $this->as($role);
            $this->get('/admin/help')->assertOk()->assertSee('Start here')->assertSee('From idea to published article');
        }
    }

    public function test_the_page_shows_topics_in_groups_with_a_link_to_the_screen(): void
    {
        $this->as('superuser');

        Livewire::test(Help::class)->assertSee('General')->assertSee('Content')->assertSee('Settings')->assertSee('Backups')
            ->assertSee('Open this screen')->assertSee('Search the help');

        $this->assertStringContainsString('/admin/articles', (string) Guide::url(collect(Guide::all())->firstWhere('key', 'articles')));
        $this->assertNull(Guide::url(collect(Guide::all())->firstWhere('key', 'start')), 'a general topic has no screen');
    }

    public function test_an_author_does_not_get_a_link_to_a_screen_that_is_not_theirs(): void
    {
        $this->as('author');

        $this->assertNull(Guide::url(collect(Guide::all())->firstWhere('key', 'backups')));
        $this->assertNotNull(Guide::url(collect(Guide::all())->firstWhere('key', 'articles')));
    }

    public function test_the_help_button_is_at_the_top_of_the_admin_for_staff(): void
    {
        $this->as('moderator');

        $this->get('/admin')->assertOk()->assertSee('/admin/help', false)->assertSee('Help');
    }

    public function test_the_help_page_is_in_the_menu_for_staff(): void
    {
        $this->as('author');

        $this->get('/admin/articles')->assertOk()->assertSee('/admin/help', false);
    }
}
