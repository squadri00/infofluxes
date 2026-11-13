<?php

namespace Tests\Feature;

use App\Filament\Pages\ChallengeSettings;
use App\Filament\Resources\Challenge\Pages\ManageFactStatements;
use App\Filament\Resources\Challenge\Pages\ManageQuizPassages;
use App\Filament\Resources\Challenge\Pages\ManageQuranChapters;
use App\Models\FactStatement;
use App\Models\QuizAttempt;
use App\Models\QuizPassage;
use App\Models\QuotePassage;
use App\Models\QuranChapter;
use App\Models\ScripturePassage;
use App\Models\Setting;
use App\Models\User;
use App\Support\OldChallengeImport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 18: "Challenge yourself" with its five tests, the top-bar link, the language button and the admin screens. */
class Step18Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
    }

    private function passages(): void
    {
        foreach (range(1, 5) as $i) {
            QuizPassage::create(['kind' => 'quran', 'reference' => '1:'.$i, 'arabic' => 'قل هو الله أحد '.$i]);
        }
        foreach (['hadith', 'bible', 'poetry'] as $kind) {
            foreach (range(1, 5) as $i) {
                QuizPassage::create(['kind' => $kind, 'reference' => $kind.$i, 'arabic' => 'نص عربي '.$kind.' '.$i.' كلمة أخرى']);
            }
        }
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    public function test_the_top_bar_has_the_challenge_link_and_the_hub_lists_the_tests(): void
    {
        $this->get('/')->assertOk()->assertSee('Challenge yourself')->assertSee(route('challenge'), false);

        $this->get(route('challenge'))->assertOk()
            ->assertSee('Which one is from the Quran?')->assertSee('Which scripture?')->assertSee('Fact or myth?')
            ->assertSee('Who said it?')->assertSee('Put the chapters in order');
    }

    public function test_a_test_that_is_switched_off_is_not_listed_and_not_open(): void
    {
        Setting::put('facts_on', '0');

        $this->get(route('challenge'))->assertOk()->assertDontSee('Fact or myth?');
        $this->get(route('challenge.show', 'facts'))->assertNotFound();
        $this->get(route('challenge.show', 'nothing'))->assertNotFound();
    }

    public function test_when_every_test_is_off_the_link_goes_away(): void
    {
        foreach (['quiz', 'scripture', 'facts', 'who', 'order'] as $t) {
            Setting::put($t.'_on', '0');
        }

        $this->get('/')->assertOk()->assertDontSee(route('challenge'), false);
    }

    public function test_a_test_page_without_questions_says_it_is_not_ready(): void
    {
        $this->get(route('challenge.show', 'quran'))->assertOk()->assertSee(\App\Support\Words::get('quiz_not_ready'));
    }

    public function test_a_quran_round_runs_from_start_to_finish_without_giving_the_answer_away(): void
    {
        $this->passages();
        Setting::put('quiz_questions', '3');

        $start = $this->getJson(route('quiz.start'))->assertOk()->json();
        $this->assertCount(3, $start['questions']);
        $this->assertArrayNotHasKey('answer', $start['questions'][0], 'the page never gets the answer before it is asked');

        $answer = $this->postJson(route('quiz.answer'), ['token' => $start['token'], 'question' => 0, 'choice' => 0])->assertOk()->json();
        $this->assertArrayHasKey('correct', $answer);

        $finish = $this->postJson(route('quiz.finish'), ['token' => $start['token'], 'choices' => [$answer['answer'], null, null]])->assertOk()->json();
        $this->assertSame(1, $finish['score']);
        $this->assertSame(3, $finish['total']);
        $this->assertSame(1, QuizAttempt::count());
        $this->assertSame(['score' => 1, 'total' => 3], QuizAttempt::first()->only('score', 'total'), 'only the score is kept');
    }

    public function test_a_broken_token_is_refused(): void
    {
        $this->postJson(route('quiz.answer'), ['token' => 'nonsense', 'question' => 0, 'choice' => 0])->assertStatus(422);
    }

    public function test_the_other_three_tests_start_when_they_have_questions(): void
    {
        foreach (range(1, 6) as $i) {
            ScripturePassage::create(['kind' => ['quran', 'hebrew_bible', 'new_testament', 'gita', 'dhammapada', 'quran'][$i - 1], 'reference' => 'r'.$i, 'text' => 'A passage number '.$i]);
            FactStatement::create(['topic' => 'history', 'statement' => 'Statement '.$i, 'is_true' => $i % 2 === 0, 'explanation' => 'Because '.$i]);
            QuotePassage::create(['kind' => ['muslim', 'christian', 'jewish', 'hindu', 'buddhist', 'secular'][$i - 1], 'quote' => 'A quote '.$i, 'speaker' => 'Speaker '.$i]);
        }

        $this->getJson(route('scripture.start'))->assertOk()->assertJsonStructure(['token', 'questions']);
        $this->getJson(route('facts.start'))->assertOk()->assertJsonStructure(['token', 'questions']);
        $this->getJson(route('who.start'))->assertOk()->assertJsonStructure(['token', 'questions']);
    }

    public function test_the_ordering_test_needs_the_chapters(): void
    {
        $this->get(route('challenge.show', 'order'))->assertOk();

        foreach (range(1, 114) as $n) {
            QuranChapter::create(['number' => $n, 'arabic_name' => 'س'.$n, 'name' => 'Sura '.$n, 'meaning' => 'Meaning '.$n, 'verses' => 5 + $n]);
        }
        $this->get(route('challenge.show', 'order'))->assertOk()->assertSee('data-order', false);
        $this->getJson(route('order.start').'?level=easy')->assertOk();
    }

    public function test_the_language_button_is_in_the_top_bar(): void
    {
        $this->get('/')->assertSee('lang-menu', false);
    }

    public function test_the_words_come_from_the_language_file(): void
    {
        $this->assertSame('Start the test', \App\Support\Words::get('quiz_start'));
        $this->assertSame('no_such_word', \App\Support\Words::get('no_such_word'));
        $this->assertSame('fallback', \App\Support\Words::get('no_such_word', 'fallback'));
    }

    public function test_the_admin_lists_open_for_the_super_user_and_not_for_an_author(): void
    {
        $this->passages();
        FactStatement::create(['topic' => 'history', 'statement' => 'A statement', 'is_true' => true, 'explanation' => 'Because']);

        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active']));
        Livewire::test(ManageQuizPassages::class)->assertForbidden();

        $this->superuser();
        Livewire::test(ManageQuizPassages::class)->assertSuccessful()->assertSee('قل هو الله أحد');
        Livewire::test(ManageFactStatements::class)->assertSuccessful()->assertSee('A statement');
        Livewire::test(ManageQuranChapters::class)->assertSuccessful();
    }

    public function test_a_moderator_can_be_given_the_challenge_tests_screen(): void
    {
        $this->actingAs($mod = User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse($mod->canUseScreen('tests'));

        $mod->update(['admin_screens' => ['tests']]);
        $this->assertTrue($mod->fresh()->canUseScreen('tests'));
    }

    public function test_the_test_settings_page_saves_and_keeps_only_what_is_not_usual(): void
    {
        $this->superuser();

        Livewire::test(ChallengeSettings::class)
            ->fillForm(['facts_on' => false, 'quiz_questions' => '7', 'order_size' => '12'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('0', Setting::get('facts_on'));
        $this->assertSame('7', Setting::get('quiz_questions'));
        $this->assertNull(Setting::query()->where('key', 'order_size')->first(), 'a usual value is not kept');
    }

    public function test_the_import_only_exists_when_the_old_site_is_there(): void
    {
        $this->assertIsBool(OldChallengeImport::available());
    }
}
