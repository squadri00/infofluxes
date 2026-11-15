<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport as AiImportPage;
use App\Filament\Resources\Explainers\Pages\ManageExplainers;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Category;
use App\Models\Explainer;
use App\Models\Page;
use App\Models\Story;
use App\Models\User;
use App\Support\AiImport as Importer;
use App\Support\Explainers;
use App\Support\Prompts;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 54: Explainers (word notes), and the AI import that makes them. */
class Step54Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        Explainers::forget();
    }

    private function note(string $term, string $status = 'published', array $extra = []): Explainer
    {
        return Explainer::create(array_merge(['term' => ['en' => $term], 'body' => ['en' => 'A plain note about '.$term.'.'], 'kind' => 'word', 'status' => $status], $extra));
    }

    private function article(string $html, array $extra = []): Article
    {
        return Article::create(array_merge(['title' => ['en' => 'An article'], 'slug' => 'an-article', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => $html]], $extra));
    }

    private function staff(string $role = 'superuser', array $screens = []): User
    {
        $u = User::factory()->create(['role' => $role, 'status' => 'active', 'admin_screens' => $screens ?: null]);
        $this->actingAs($u);

        return $u;
    }

    // ---------- the notes ----------

    public function test_a_note_has_a_code_a_main_word_and_answers_to_its_spellings(): void
    {
        $e = $this->note('Taghut', 'published', ['aliases' => ['Taghoot', ' taghout ']]);

        $this->assertSame('E-'.$e->id, $e->code);
        $this->assertSame('Taghut', $e->mainTerm());
        $this->assertSame(['taghut', 'taghoot', 'taghout'], $e->lookupWords());
    }

    public function test_only_published_notes_are_known_to_the_site_and_a_change_is_seen_at_once(): void
    {
        $e = $this->note('Taghut', 'draft', ['aliases' => ['Taghoot']]);
        $this->assertSame([], Explainers::map());

        $e->update(['status' => 'published']);
        $this->assertSame(['taghut' => $e->id, 'taghoot' => $e->id], Explainers::map());

        $e->delete();
        $this->assertSame([], Explainers::map());
    }

    // ---------- showing a note ----------

    public function test_a_mark_becomes_a_tappable_word_the_first_time_only(): void
    {
        $e = $this->note('Taghut');

        $r = Explainers::render('<p>First @[Taghut] here and again @[taghut] later.</p>');

        $this->assertSame('<p>First <button type="button" class="exp" data-exp="'.$e->id.'" aria-expanded="false" aria-controls="exp-card-'.$e->id.'">Taghut</button> here and again taghut later.</p>', $r['html']);
        $this->assertSame([$e->id], array_map(fn ($x) => $x->id, $r['used']));
    }

    public function test_the_shown_words_can_differ_from_the_word_of_the_note(): void
    {
        $e = $this->note('Taghut');

        $r = Explainers::render('<p>They avoid @[Taghut|false objects of worship].</p>');

        $this->assertStringContainsString('>false objects of worship</button>', $r['html']);
        $this->assertSame(1, count($r['used']));
    }

    public function test_a_word_with_no_published_note_is_just_its_words(): void
    {
        $this->note('Draftword', 'draft');

        $r = Explainers::render('<p>See @[Unknown] and @[Draftword|the draft one] here.</p>');

        $this->assertSame('<p>See Unknown and the draft one here.</p>', $r['html']);
        $this->assertSame([], $r['used']);
    }

    public function test_a_text_without_marks_is_not_touched(): void
    {
        $this->assertSame(['html' => '<p>Nothing here.</p>', 'used' => []], Explainers::render('<p>Nothing here.</p>'));
    }

    public function test_marks_are_not_shown_inside_links_headings_buttons_or_code(): void
    {
        $this->note('Taghut');

        $html = '<h2>About @[Taghut]</h2><p><a href="/x">a @[Taghut] link</a> <code>@[Taghut]</code></p>';

        $r = Explainers::render($html);

        $this->assertSame($html, $r['html'], 'left exactly as they are');
        $this->assertSame([], $r['used']);
    }

    public function test_a_word_with_an_apostrophe_is_found(): void
    {
        $e = $this->note("Qur'an");

        $r = Explainers::render('<p>Read the @[Qur&#039;an] daily.</p>');

        $this->assertStringContainsString('data-exp="'.$e->id.'"', $r['html']);
    }

    // ---------- marking a text ----------

    public function test_the_first_whole_word_is_marked(): void
    {
        $r = Explainers::mark('<p>The Imamate is not the imam. An Imam leads. The imam prays.</p>', [['term' => 'Imam']]);

        $this->assertSame('<p>The Imamate is not the @[Imam|imam]. An Imam leads. The imam prays.</p>', $r['html']);
        $this->assertSame(['Imam'], $r['marked']);
    }

    public function test_marking_skips_headings_links_quotations_and_citations(): void
    {
        $html = '<h2>Taghut</h2><p><a href="/x">Taghut</a></p><blockquote class="quote quote--quran"><p>Taghut</p><cite>Taghut</cite></blockquote><p>Plain Taghut here.</p>';

        $r = Explainers::mark($html, [['term' => 'Taghut']]);

        $this->assertSame('<h2>Taghut</h2><p><a href="/x">Taghut</a></p><blockquote class="quote quote--quran"><p>Taghut</p><cite>Taghut</cite></blockquote><p>Plain @[Taghut] here.</p>', $r['html']);
    }

    public function test_a_spelling_is_used_when_the_main_word_is_not_there(): void
    {
        $r = Explainers::mark('<p>The word taghoot appears once.</p>', [['term' => 'Taghut', 'aliases' => ['Taghoot']]]);

        $this->assertSame('<p>The word @[Taghut|taghoot] appears once.</p>', $r['html']);
    }

    public function test_the_longest_spelling_wins(): void
    {
        $r = Explainers::mark('<p>On the Day of Judgment the day comes.</p>', [['term' => 'Day', 'aliases' => ['Day of Judgment']]]);

        $this->assertSame('<p>On the @[Day|Day of Judgment] the day comes.</p>', $r['html']);
    }

    public function test_a_word_that_is_not_there_is_reported_and_a_second_run_changes_nothing(): void
    {
        $once = Explainers::mark('<p>Only Imam here.</p>', [['term' => 'Imam'], ['term' => 'Caliph']]);
        $twice = Explainers::mark($once['html'], [['term' => 'Imam']]);

        $this->assertSame(['Imam'], $once['marked']);
        $this->assertSame(['Caliph'], $once['missing']);
        $this->assertSame($once['html'], $twice['html'], 'a word is marked once');
    }

    public function test_a_word_with_an_apostrophe_is_marked_and_shown_again(): void
    {
        $e = $this->note("Qur'an");
        $marked = Explainers::mark('<p>The Qur&#039;an is read.</p>', [['term' => "Qur'an"]])['html'];

        $this->assertStringContainsString('@[Qur&#039;an]', $marked);
        $this->assertStringContainsString('data-exp="'.$e->id.'"', Explainers::render($marked)['html']);
    }

    // ---------- the AI answer ----------

    public function test_the_lines_of_an_answer_are_read(): void
    {
        $r = Explainers::readAnswer("- Taghut | word | Anything worshipped instead of Allah (SWT). | https://example.org/t | Taghoot, Taghout\n- Nimrod | person | A king in the story of Ibrahim (AS).\n- Bare note | not a kind | The second part was the note.\n- NONE\n- Broken [word] | word | x\n- | word | no word\n- Empty | word |\n- Taghut | word | Repeated word is skipped.");

        $this->assertSame(['Taghut', 'Nimrod', 'Bare note'], array_column($r['notes'], 'term'));
        $this->assertSame(['word', 'person', 'word'], array_column($r['notes'], 'kind'));
        $this->assertSame('https://example.org/t', $r['notes'][0]['source']);
        $this->assertSame(['Taghoot', 'Taghout'], $r['notes'][0]['aliases']);
        $this->assertNull($r['notes'][1]['source']);
        $this->assertSame('not a kind', $r['notes'][2]['text'], 'with no kind the second part is the note');
        $this->assertCount(3, $r['problems']);
    }

    public function test_an_existing_note_is_found_by_any_of_its_spellings_in_any_status(): void
    {
        $this->note('Taghut', 'draft', ['aliases' => ['Taghoot']]);

        $this->assertNotNull(Explainers::existing(['term' => 'Taghoot', 'aliases' => []]));
        $this->assertNotNull(Explainers::existing(['term' => 'Other', 'aliases' => ['taghut']]));
        $this->assertNull(Explainers::existing(['term' => 'Unknown', 'aliases' => []]));
    }

    private function answer(string $notes, string $long = "## One\nThe Taghut is a false object of worship.\n\nNimrod was a king. He argued about Taghut again."): string
    {
        return "TYPE: article\nCATEGORY: {$this->cat->code}\nTITLE: With notes\nSHORT: Short.\nLONG:\n{$long}\nSOURCE: A source\nEXPLAINERS:\n{$notes}\nEND";
    }

    public function test_the_prompt_asks_for_word_notes_only_when_it_is_on(): void
    {
        $on = Prompts::research(['topic' => 'x']);
        $off = Prompts::research(['topic' => 'x', 'explainers' => false]);

        $this->assertStringContainsString('EXPLAINERS:', $on);
        $this->assertStringContainsString('Word notes: choose 3 to 8 words', $on);
        $this->assertStringContainsString('At most '.Explainers::MAX_PER_ARTICLE.' lines', $on);
        $this->assertStringNotContainsString('EXPLAINERS', $off);
        $this->assertStringNotContainsString('Word notes', $off);
        $this->assertStringNotContainsString('{{', $on.$off);
    }

    public function test_the_page_has_the_switch_and_passes_it_to_the_prompt(): void
    {
        $this->staff();

        $page = Livewire::test(AiImportPage::class)->assertSet('explainers', true)->assertSee('Also ask for word notes');
        $this->assertStringContainsString('EXPLAINERS:', $page->instance()->prompt());

        $page->set('explainers', false);
        $this->assertStringNotContainsString('EXPLAINERS', $page->instance()->prompt());
    }

    public function test_check_reads_the_notes_and_says_what_it_found(): void
    {
        $this->note('Nimrod', 'published');
        $notes = "- Taghut | word | A false object of worship. | https://example.org/t | Taghoot\n- Nimrod | person | A king. | https://example.org/n\n- Caliph | word | A leader. |";

        $item = Importer::parse($this->answer($notes))[0];

        $this->assertSame([], $item['errors']);
        $byTerm = collect($item['data']['explainers'])->keyBy('term');
        $this->assertTrue($byTerm['Taghut']['found']);
        $this->assertNull($byTerm['Taghut']['exists']);
        $this->assertStringContainsString('Nimrod', (string) $byTerm['Nimrod']['exists']);
        $this->assertFalse($byTerm['Caliph']['found']);
        $w = implode(' | ', $item['warnings']);
        $this->assertStringContainsString('"Caliph" is not in the article text', $w);
        $points = implode(' | ', $item['data']['please_check']);
        $this->assertStringContainsString('2 new word note(s) will be made as drafts', $points);
        $this->assertStringContainsString('"Caliph" has no source', $points);
    }

    public function test_check_keeps_at_most_twelve_notes_and_cuts_a_very_long_one(): void
    {
        $lines = collect(range(1, 14))->map(fn ($i) => "- Word{$i} | word | Note {$i}.")->implode("\n")."\n- Long | word | ".str_repeat('x', 600);

        $item = Importer::parse($this->answer($lines))[0];

        $this->assertCount(12, $item['data']['explainers']);
        $this->assertStringContainsString('More than 12 word notes', implode(' ', $item['warnings']));
    }

    public function test_saving_marks_the_words_and_makes_draft_notes_that_readers_do_not_see_yet(): void
    {
        $me = $this->staff();
        $notes = "- Taghut | word | A false object of worship. | https://example.org/t | Taghoot\n- Nimrod | person | A king in the story of Ibrahim (AS). | https://example.org/n";

        Livewire::test(AiImportPage::class)->set('paste', $this->answer($notes))->call('check')->call('save');

        $article = Article::where('slug', 'with-notes')->firstOrFail();
        $html = $article->getTranslation('long_detail', 'en');
        $this->assertStringContainsString('The @[Taghut] is a false object', $html, 'the first place only');
        $this->assertStringContainsString('argued about Taghut again', $html);
        $this->assertStringContainsString('@[Nimrod] was a king', $html);

        $notes = Explainer::orderBy('id')->get();
        $this->assertSame(['Taghut', 'Nimrod'], $notes->map->mainTerm()->all());
        foreach ($notes as $n) {
            $this->assertSame('draft', $n->status);
            $this->assertTrue($n->ai_assisted);
            $this->assertSame($me->id, $n->author_id);
        }
        $this->assertSame(['Taghoot'], $notes[0]->aliases);
        $this->assertSame('https://example.org/t', $notes[0]->source_line);

        $article->update(['status' => 'published', 'publish_date' => now()->subDay()]);
        auth()->logout();
        $page = $this->get('/with-notes')->assertOk()->getContent();
        $this->assertStringNotContainsString('class="exp"', $page, 'draft notes are not shown');
        $this->assertStringContainsString('The Taghut is a false object', $page);

        $notes[0]->update(['status' => 'published']);
        $this->get('/with-notes')->assertOk()->assertSee('class="exp"', false)->assertSee('Words explained on this page');
    }

    public function test_an_existing_note_is_reused_and_not_made_twice(): void
    {
        $this->staff();
        $existing = $this->note('Nimrod', 'published');

        Livewire::test(AiImportPage::class)->set('paste', $this->answer("- Nimrod | person | A king. | https://example.org/n"))->call('check')->call('save');

        $this->assertSame(1, Explainer::count());
        $this->assertSame($existing->id, Explainer::first()->id);
        $this->assertStringContainsString('@[Nimrod] was a king', Article::where('slug', 'with-notes')->first()->getTranslation('long_detail', 'en'));
    }

    public function test_filling_an_index_entry_marks_the_words_and_makes_the_notes_too(): void
    {
        $this->staff();
        $entry = Article::create(['title' => ['en' => 'Planned'], 'slug' => 'planned', 'category_id' => $this->cat->id, 'working_name' => 'AL-1.1.001', 'is_index_line' => true, 'status' => 'draft']);

        Livewire::test(AiImportPage::class)->call('selectEntry', $entry->id)->set('paste', $this->answer("- Taghut | word | A false object of worship. | https://example.org/t"))->call('check')->call('save');

        $this->assertStringContainsString('@[Taghut]', $entry->fresh()->getTranslation('long_detail', 'en'));
        $this->assertSame(1, Explainer::count());
    }

    public function test_an_answer_with_no_notes_still_works(): void
    {
        $item = Importer::parse("TYPE: article\nCATEGORY: {$this->cat->code}\nTITLE: Plain\nSHORT: S\nLONG:\n## One\nWords here.\nEND")[0];

        $this->assertSame([], $item['errors']);
        $this->assertSame([], $item['data']['explainers']);
    }

    // ---------- on the site ----------

    public function test_an_article_shows_its_tappable_words_the_notes_list_and_the_script(): void
    {
        $e = $this->note('Taghut', 'published', ['source_line' => 'A book, page 3']);
        $this->article('<p>They avoid @[Taghut] always.</p>');

        $html = $this->get('/an-article')->assertOk()->getContent();

        $this->assertStringContainsString('data-exp="'.$e->id.'"', $html);
        $this->assertStringContainsString('id="exp-card-'.$e->id.'"', $html);
        $this->assertStringContainsString('A plain note about Taghut.', $html);
        $this->assertStringContainsString('A book, page 3', $html);
        $this->assertStringContainsString('js/explainers.js', $html);
        $this->assertStringContainsString(route('words'), $html);
    }

    public function test_an_article_without_notes_loads_no_extra_script(): void
    {
        $this->article('<p>Plain words.</p>');

        $this->get('/an-article')->assertOk()->assertDontSee('js/explainers.js', false)->assertDontSee('Words explained on this page');
    }

    public function test_it_also_works_inside_the_parts_of_a_drop_down_article(): void
    {
        $e = $this->note('Taghut');
        $this->article('<h2>One</h2><p>Text with @[Taghut] in it.</p><h2>Two</h2><p>More.</p>', ['custom_data' => ['accordion' => true]]);

        $html = $this->get('/an-article')->assertOk()->getContent();

        $this->assertStringContainsString('<details class="acc"', $html);
        $this->assertStringContainsString('data-exp="'.$e->id.'"', $html);
    }

    public function test_a_visual_story_shows_the_words_too(): void
    {
        $e = $this->note('Nimrod');
        Story::create(['title' => ['en' => 'A story'], 'slug' => 'a-story', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => '<p>The king @[Nimrod] ruled.</p>']]);

        $this->get('/stories/a-story')->assertOk()->assertSee('data-exp="'.$e->id.'"', false)->assertSee('js/explainers.js', false);
    }

    public function test_the_words_page_lists_the_published_notes_from_a_to_z(): void
    {
        $this->note('Zakat');
        $this->note('Adhan');
        $this->note('Aqidah');
        $this->note('Hidden one', 'draft');

        $html = $this->get('/words')->assertOk()->assertSee('Words explained')->getContent();

        $this->assertStringNotContainsString('Hidden one', $html);
        $this->assertLessThan(strpos($html, 'Aqidah'), strpos($html, 'Adhan'));
        $this->assertLessThan(strpos($html, 'Zakat'), strpos($html, 'Aqidah'));
        $this->assertStringContainsString('id="word-', $html);
    }

    public function test_the_words_page_has_an_empty_state_and_the_footer_and_sitemap_follow(): void
    {
        $this->get('/words')->assertOk()->assertSee('There are no word notes yet.');
        $this->get('/')->assertDontSee(route('words'), false);
        $this->assertStringNotContainsString(route('words'), $this->get('/sitemap.xml')->getContent());

        $this->note('Adhan');

        $this->get('/')->assertSee(route('words'), false);
        $this->assertStringContainsString(route('words'), $this->get('/sitemap.xml')->getContent());
    }

    public function test_the_code_of_a_note_opens_the_words_page_and_the_word_words_is_reserved(): void
    {
        $e = $this->note('Adhan');
        $draft = $this->note('Secret', 'draft');

        $this->get('/id/E-'.$e->id)->assertRedirect(route('words').'#word-'.$e->id);
        $this->get('/id/E-'.$draft->id)->assertNotFound();
        $this->assertNotSame('words', Category::uniqueSlug('Words'));
        $this->assertContains('words', Page::RESERVED);
    }

    public function test_the_script_and_the_styles_exist(): void
    {
        $this->assertStringContainsString('button.exp', (string) file_get_contents(public_path('js/explainers.js')));
        $this->assertStringContainsString('.exp-pop', (string) file_get_contents(public_path('css/site.css')));
    }

    // ---------- the screen ----------

    public function test_a_moderator_has_the_screen_by_default_an_author_only_when_ticked(): void
    {
        $this->staff('author');
        $this->get('/admin/explainers')->assertForbidden();
        $this->staff('author', ['articles', 'explainers']);
        $this->get('/admin/explainers')->assertOk();
        $this->staff('moderator');
        $this->get('/admin/explainers')->assertOk();
        $this->staff('superuser');
        $this->get('/admin/explainers')->assertOk()->assertSee('New explainer');
    }

    public function test_a_note_can_be_made_by_hand_and_the_change_is_in_the_history(): void
    {
        $me = $this->staff();
        Activity::query()->delete();

        Livewire::test(ManageExplainers::class)->callAction('create', [
            'term' => ['en' => 'Adhan'], 'kind' => 'word', 'body' => ['en' => 'The call to prayer.'], 'status' => 'published', 'aliases' => ['Azan'],
        ])->assertHasNoActionErrors();

        $e = Explainer::first();
        $this->assertSame($me->id, $e->author_id);
        $this->assertFalse($e->ai_assisted);
        $this->assertSame('published', $e->status, 'a note written by hand needs no reviewer');
        $this->assertSame(Explainer::class, Activity::first()->subject_type);
        $this->assertSame(['adhan' => $e->id, 'azan' => $e->id], Explainers::map());
    }

    public function test_a_note_written_with_ai_help_needs_a_reviewer_to_be_published(): void
    {
        $this->staff();
        $e = $this->note('Taghut', 'draft', ['ai_assisted' => true]);

        Livewire::test(ManageExplainers::class)->callAction(TestAction::make('edit')->table($e), ['term' => ['en' => 'Taghut'], 'kind' => 'word', 'body' => ['en' => 'Words.'], 'status' => 'published', 'reviewer_name' => ''])->assertHasNoActionErrors();
        $this->assertSame('draft', $e->fresh()->status);

        Livewire::test(ManageExplainers::class)->callAction(TestAction::make('edit')->table($e), ['term' => ['en' => 'Taghut'], 'kind' => 'word', 'body' => ['en' => 'Words.'], 'status' => 'published', 'reviewer_name' => 'Dr Check'])->assertHasNoActionErrors();
        $this->assertSame('published', $e->fresh()->status);
    }

    public function test_the_reviewer_can_publish_many_notes_at_once(): void
    {
        $this->staff();
        $a = $this->note('One', 'draft', ['ai_assisted' => true]);
        $b = $this->note('Two', 'draft', ['ai_assisted' => true, 'reviewer_name' => 'Already named']);

        Livewire::test(ManageExplainers::class)->selectTableRecords([$a->id, $b->id])
            ->callAction(TestAction::make('publish')->table()->bulk(), ['reviewer_name' => 'Dr Check'])->assertHasNoActionErrors();

        $this->assertSame(['published', 'Dr Check'], [$a->fresh()->status, $a->fresh()->reviewer_name]);
        $this->assertSame(['published', 'Already named'], [$b->fresh()->status, $b->fresh()->reviewer_name], 'a name that was written already is kept');
    }
}
