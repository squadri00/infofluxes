<?php

namespace Tests\Feature;

use App\Filament\Resources\SearchSynonyms\Pages\ManageSearchSynonyms;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\SearchSynonym;
use App\Models\Story;
use App\Models\User;
use App\Support\Synonyms;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 53: search synonyms: words the search treats as the same. */
class Step53Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        SearchSynonym::query()->delete();   // each test starts with no groups, and makes its own
        Synonyms::forget();
    }

    private function group(array $terms, bool $on = true, ?string $note = null): SearchSynonym
    {
        return SearchSynonym::create(['terms' => $terms, 'is_active' => $on, 'note' => $note]);
    }

    private function article(string $title, string $words = '<p>Words.</p>'): Article
    {
        return Article::create(['title' => ['en' => $title], 'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(), 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => $words]]);
    }

    // ---------- the words ----------

    public function test_a_word_is_kept_in_a_plain_form(): void
    {
        $this->assertSame("qur'an", Synonyms::plain("  Qur’An "));
        $this->assertSame("ka'ba", Synonyms::plain('KAʿBA'));
        $this->assertSame('day of judgment', Synonyms::plain("Day   of\tJudgment"));
        $this->assertSame(['quran', "qur'an", 'koran'], Synonyms::cleanTerms(['Quran', 'Qur’an', 'quran', ' ', 'KORAN']));
        $this->assertSame(['one two'], Synonyms::cleanTerms(['one,two']), 'a comma never stays in a word');
    }

    public function test_saving_a_group_cleans_it_and_writes_the_history(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        Activity::query()->delete();

        $g = $this->group(['Quran', 'KORAN', 'quran']);

        $this->assertSame(['quran', 'koran'], $g->fresh()->terms);
        $this->assertSame(SearchSynonym::class, Activity::first()->subject_type);
    }

    // ---------- what is looked for ----------

    public function test_a_word_in_a_group_is_looked_for_with_the_others(): void
    {
        $this->group(['quran', 'koran', 'qur\'an']);

        $units = Synonyms::units(['quran']);

        $this->assertSame('quran', $units[0]['typed']);
        $this->assertEqualsCanonicalizing(['quran', 'koran', "qur'an", 'qur’an'], $units[0]['look_for']);
        $this->assertSame(['koran', "qur'an"], $units[0]['also']);
    }

    public function test_a_word_with_no_group_is_looked_for_as_typed(): void
    {
        $this->group(['quran', 'koran']);

        $units = Synonyms::units(['patience']);

        $this->assertSame(['patience'], $units[0]['look_for']);
        $this->assertSame([], $units[0]['also']);
    }

    public function test_a_term_of_several_words_is_found_in_what_was_typed(): void
    {
        $this->group(['qiyamah', 'day of judgment', 'last day']);

        $units = Synonyms::units(['the', 'day', 'of', 'judgment', 'explained']);

        $this->assertSame(['the', 'day of judgment', 'explained'], array_column($units, 'typed'));
        $this->assertContains('qiyamah', $units[1]['look_for']);
    }

    public function test_the_longest_term_wins(): void
    {
        $this->group(['day', 'daylight']);
        $this->group(['day of judgment', 'qiyamah']);

        $units = Synonyms::units(['day', 'of', 'judgment']);

        $this->assertCount(1, $units);
        $this->assertContains('qiyamah', $units[0]['look_for']);
    }

    public function test_short_words_are_not_added_from_a_group(): void
    {
        $this->group(['isa', 'eesa', 'esa', 'issa']);

        $this->assertSame(['eesa', 'issa'], Synonyms::units(['isa'])[0]['also'], 'a three-letter spelling would match half the words');
        $this->assertContains('isa', Synonyms::units(['isa'])[0]['look_for'], 'but what the visitor typed is always searched');
    }

    public function test_an_apostrophe_is_forgiven_even_with_no_group(): void
    {
        $look = Synonyms::units(["ka'ba"])[0]['look_for'];

        $this->assertEqualsCanonicalizing(["ka'ba", 'ka’ba', 'kaba'], $look);
    }

    public function test_groups_that_are_off_are_not_used_and_groups_that_share_a_word_are_joined(): void
    {
        $this->group(['musa', 'moses'], false);
        $this->assertSame([], Synonyms::units(['musa'])[0]['also']);

        $this->group(['musa', 'moosa', 'musaa']);
        $this->group(['musa', 'moses']);

        $this->assertEqualsCanonicalizing(['moosa', 'musaa', 'moses'], Synonyms::units(['musa'])[0]['also']);
        $this->assertEqualsCanonicalizing(['musa', 'moosa', 'musaa'], Synonyms::units(['moses'])[0]['also']);
    }

    public function test_a_change_is_seen_at_once(): void
    {
        $g = $this->group(['quran', 'koran']);
        $this->assertSame(['koran'], Synonyms::units(['quran'])[0]['also']);

        $g->update(['terms' => ['quran', 'quraan']]);
        $this->assertSame(['quraan'], Synonyms::units(['quran'])[0]['also']);

        $g->update(['is_active' => false]);
        $this->assertSame([], Synonyms::units(['quran'])[0]['also']);

        $g->delete();
        $this->assertSame([], Synonyms::units(['quran'])[0]['also']);
    }

    public function test_a_group_of_one_word_does_nothing(): void
    {
        $this->group(['alone']);

        $this->assertSame([], Synonyms::units(['alone'])[0]['also']);
    }

    // ---------- the search itself ----------

    public function test_a_spelling_finds_the_other_spellings(): void
    {
        $this->group(['quran', 'koran', "qur'an"]);
        $a = $this->article('What the Koran says about patience');
        $b = $this->article('The Qur’an and its story');
        $c = $this->article('Unrelated article');

        $this->get('/search?q=quran')->assertOk()->assertSee($a->mainTitle())->assertSee($b->mainTitle())->assertDontSee($c->mainTitle());
        $this->get('/search?q='.urlencode("qur'an"))->assertSee($a->mainTitle())->assertSee($b->mainTitle());
    }

    public function test_every_word_typed_is_still_needed_and_each_may_be_a_synonym(): void
    {
        $this->group(['quran', 'koran']);
        $this->group(['hadith', 'hadeeth']);
        $both = $this->article('The Koran and the hadeeth');
        $one = $this->article('Only the Quran here');

        $this->get('/search?q='.urlencode('quran hadith'))->assertSee($both->mainTitle())->assertDontSee($one->mainTitle());
    }

    public function test_a_phrase_synonym_works_in_the_search(): void
    {
        $this->group(['qiyamah', 'day of judgment']);
        $a = $this->article('Signs of the Qiyamah');

        $this->get('/search?q='.urlencode('day of judgment'))->assertSee($a->mainTitle());
    }

    public function test_stories_facts_and_categories_use_synonyms_too(): void
    {
        $this->group(['tawhid', 'tawheed']);
        $story = Story::create(['title' => ['en' => 'A story about Tawheed'], 'slug' => 'st', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
        $fact = DidYouKnow::create(['title' => ['en' => 'A tawheed fact'], 'body' => ['en' => 'Words'], 'status' => 'published', 'slug' => 'f', 'source' => 'A source']);
        Category::create(['name' => ['en' => 'Tawheed basics'], 'slug' => 'tb']);

        $html = $this->get('/search?q=tawhid')->assertOk()->getContent();

        $this->assertStringContainsString('A story about Tawheed', $html);
        $this->assertStringContainsString('A tawheed fact', $html);
        $this->assertStringContainsString('Tawheed basics', $html);
    }

    public function test_the_page_says_what_else_was_searched(): void
    {
        $this->group(['quran', 'koran']);

        $this->get('/search?q=quran')->assertSee('Also searched for:')->assertSee('koran');
        $this->get('/search?q=patience')->assertDontSee('Also searched for:');
    }

    public function test_ids_and_plain_searches_still_work(): void
    {
        $this->group(['quran', 'koran']);
        $a = $this->article('A plain article');

        $this->get('/search?q='.$a->code)->assertSee('A plain article');
        $this->get('/search?q=plain')->assertSee('A plain article');
    }

    public function test_a_search_with_a_group_off_finds_only_what_was_typed(): void
    {
        $this->group(['quran', 'koran'], false);
        $a = $this->article('The Koran explained');

        $this->get('/search?q=quran')->assertDontSee($a->mainTitle());
    }

    // ---------- the starting list ----------

    public function test_the_starting_list_has_the_spellings_on_and_the_translations_off(): void
    {
        SearchSynonym::installDefaults();

        $quran = SearchSynonym::all()->first(fn ($g) => in_array('quran', $g->terms, true));
        $this->assertTrue($quran->is_active);
        $this->assertContains('koran', $quran->terms);
        $this->assertContains("qur'an", $quran->terms);

        $moses = SearchSynonym::all()->first(fn ($g) => $g->terms === ['musa', 'moses']);
        $this->assertFalse($moses->is_active, 'it says two names are the same person: the owner decides');
        $this->assertNotEmpty($moses->note);

        $this->assertSame(0, SearchSynonym::installDefaults(), 'it is never put in twice');
    }

    public function test_the_starting_list_never_says_god_and_allah_are_the_same(): void
    {
        SearchSynonym::installDefaults();

        $this->assertNull(SearchSynonym::all()->first(fn ($g) => in_array('allah', $g->terms, true) && in_array('god', $g->terms, true)));
        $this->assertSame([], SearchSynonym::all()->filter(fn ($g) => $g->is_active && in_array('satan', $g->terms, true))->all(), 'Satan and Shaytan are not treated as the same unless the owner switches it on');
    }

    public function test_a_removed_starting_group_is_not_put_back(): void
    {
        SearchSynonym::installDefaults();
        SearchSynonym::query()->first()->delete();
        $before = SearchSynonym::count();

        $this->assertSame(0, SearchSynonym::installDefaults());
        $this->assertSame($before, SearchSynonym::count());
    }

    // ---------- the screen ----------

    public function test_a_moderator_and_the_super_user_see_the_screen_but_an_author_does_not(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active']));
        $this->get('/admin/search-synonyms')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active']));
        $this->get('/admin/search-synonyms')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->get('/admin/search-synonyms')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->get('/admin/search-synonyms')->assertOk()->assertSee('New group of words')->assertSee('Try a search');
    }

    public function test_a_group_can_be_made_from_the_screen_and_needs_two_words(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));

        Livewire::test(ManageSearchSynonyms::class)->callAction('create', ['terms' => ['Only one'], 'is_active' => true])->assertHasActionErrors(['terms']);
        $this->assertSame(0, SearchSynonym::count());

        Livewire::test(ManageSearchSynonyms::class)->callAction('create', ['terms' => ['Hijab', 'Hejab'], 'is_active' => true, 'note' => 'spelling'])->assertHasNoActionErrors();
        $this->assertSame(['hijab', 'hejab'], SearchSynonym::first()->terms);
        $this->assertSame(['hejab'], Synonyms::units(['hijab'])[0]['also']);
    }

    public function test_a_group_can_be_switched_off_edited_and_deleted_from_the_screen(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $g = $this->group(['quran', 'koran']);

        Livewire::test(ManageSearchSynonyms::class)
            ->callAction(TestAction::make('edit')->table($g), ['terms' => ['quran', 'koran', 'quraan'], 'is_active' => true])->assertHasNoActionErrors();
        $this->assertSame(['quran', 'koran', 'quraan'], $g->fresh()->terms);

        Livewire::test(ManageSearchSynonyms::class)->callAction(TestAction::make('delete')->table($g));
        $this->assertSame(0, SearchSynonym::count());
    }

    public function test_the_try_button_shows_what_would_be_searched(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->group(['quran', 'koran']);

        Livewire::test(ManageSearchSynonyms::class)->callAction('try', ['query' => 'quran'])->assertHasNoActionErrors()->assertNotified('Also searched for:');
        $this->assertSame(['koran'], Synonyms::alsoFor('Quran'));
        $this->assertSame([], Synonyms::alsoFor('patience'));
    }
}
