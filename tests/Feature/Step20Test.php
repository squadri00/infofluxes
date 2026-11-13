<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 20: the search finds words in any capital letters, only in the text (not in hidden JSON keys), and a chosen category alone lists its topics. */
class Step20Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function article(string $title, ?Category $category = null, array $extra = []): Article
    {
        return Article::create(array_merge([
            'title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'category_id' => $category?->id,
            'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => '<p>Plain text.</p>'],
        ], $extra));
    }

    public function test_capital_letters_do_not_matter(): void
    {
        $this->article('What happened to the Prophet before the Night Journey');

        foreach (['prophet', 'PROPHET', 'Prophet', 'night journey'] as $word) {
            $this->get(route('search', ['q' => $word]))->assertOk()->assertSee('What happened to the Prophet');
        }
    }

    public function test_every_word_must_be_found(): void
    {
        $this->article('The Lote Tree of the Boundary');

        $this->get(route('search', ['q' => 'lote boundary']))->assertSee('The Lote Tree');
        $this->get(route('search', ['q' => 'lote mountain']))->assertDontSee('The Lote Tree')->assertSee('Nothing was found');
    }

    public function test_the_hidden_language_keys_of_the_stored_text_are_not_searched(): void
    {
        $this->article('Zakat explained');

        $this->get(route('search', ['q' => 'en']))->assertDontSee('Zakat explained');
    }

    public function test_the_long_text_and_the_search_words_are_searched(): void
    {
        $this->article('Plain title', null, ['long_detail' => ['en' => '<p>The sidrat marks a boundary.</p>'], 'search_words' => 'Lotus, Boundary']);

        $this->get(route('search', ['q' => 'SIDRAT']))->assertSee('Plain title');
        $this->get(route('search', ['q' => 'lotus']))->assertSee('Plain title');
    }

    public function test_draft_articles_are_never_found(): void
    {
        $this->article('Secret draft', null, ['status' => 'draft']);

        $this->get(route('search', ['q' => 'secret']))->assertDontSee('Secret draft');
    }

    public function test_symbols_that_mean_something_to_the_database_are_plain_text(): void
    {
        $this->article('A fifty percent claim');

        $this->get(route('search', ['q' => '%']))->assertOk()->assertSee('Nothing was found');
        $this->get(route('search', ['q' => '_']))->assertOk()->assertSee('Nothing was found');
        $this->get(route('search', ['q' => "it's | a \\ test"]))->assertOk();
    }

    public function test_a_category_limits_the_search_to_its_branch(): void
    {
        $faith = Category::create(['name' => ['en' => 'Faith'], 'slug' => 'faith']);
        $prayer = Category::create(['name' => ['en' => 'Prayer'], 'slug' => 'prayer', 'parent_id' => $faith->id]);
        $history = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);
        $this->article('Why we pray every day', $prayer);
        $this->article('Why empires fall every day', $history);

        $this->get(route('search', ['q' => 'every day', 'category' => $faith->id]))->assertSee('Why we pray')->assertDontSee('Why empires fall');
        $this->get(route('search', ['q' => 'every day']))->assertSee('Why we pray')->assertSee('Why empires fall');
    }

    public function test_a_category_without_a_word_lists_its_topics(): void
    {
        $faith = Category::create(['name' => ['en' => 'Faith'], 'slug' => 'faith']);
        $history = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);
        $this->article('Why we pray', $faith);
        $this->article('Why empires fall', $history);

        $this->get(route('search', ['category' => $faith->id]))->assertOk()
            ->assertSee('Topics in Faith')->assertSee('Why we pray')->assertDontSee('Why empires fall')->assertDontSee('Type a word to search');
    }

    public function test_nothing_typed_and_nothing_chosen_asks_for_a_word(): void
    {
        $this->get(route('search'))->assertOk()->assertSee('Type a word to search');
    }

    public function test_a_hidden_category_is_not_a_way_in(): void
    {
        $secret = Category::create(['name' => ['en' => 'Secret'], 'slug' => 'secret', 'is_visible' => false]);
        $this->article('In the secret place', $secret);

        $this->get(route('search', ['category' => $secret->id]))->assertOk()->assertDontSee('In the secret place')->assertSee('Type a word to search');
    }

    public function test_the_header_search_form_points_at_the_search_page(): void
    {
        $this->get('/')->assertSee('action="'.route('search').'"', false)->assertSee('name="q"', false);
    }
}
