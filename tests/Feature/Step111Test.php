<?php

namespace Tests\Feature;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\ChallengeScores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 111: the challenge scores of a signed-in member are kept with the account (My scores). A guest's score has no name. */
class Step111Test extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['role' => 'member', 'status' => 'active']);
    }

    public function test_a_score_made_while_logged_in_is_kept_with_the_member_and_a_guest_score_has_no_name(): void
    {
        QuizAttempt::create(['quiz' => 'fact-or-myth', 'score' => 3, 'total' => 10]);
        $guest = QuizAttempt::first();
        $this->assertNull($guest->user_id);

        $me = $this->member();
        $this->actingAs($me);
        $mine = QuizAttempt::create(['quiz' => 'fact-or-myth', 'score' => 8, 'total' => 10]);

        $this->assertSame($me->id, $mine->fresh()->user_id);
    }

    public function test_my_scores_page_shows_the_best_of_each_test_and_the_history_and_only_mine(): void
    {
        $me = $this->member();
        $other = $this->member();
        QuizAttempt::create(['user_id' => $me->id, 'quiz' => 'fact-or-myth', 'score' => 4, 'total' => 10]);
        QuizAttempt::create(['user_id' => $me->id, 'quiz' => 'fact-or-myth', 'score' => 9, 'total' => 10]);
        QuizAttempt::create(['user_id' => $me->id, 'quiz' => 'quran-order-easy', 'score' => 5, 'total' => 5]);
        QuizAttempt::create(['user_id' => $other->id, 'quiz' => 'who-said-it', 'score' => 1, 'total' => 10]);
        $this->actingAs($me);

        $page = $this->get(route('account.scores'))->assertOk();
        $page->assertSee('Fact or myth?')->assertSee('9 / 10')->assertSee('90%')->assertSee('Put the chapters in order (Easy)')->assertSee('100%')
            ->assertDontSee('Who said it?')->assertSee('data-scores-best', false)->assertSee('data-scores-history', false);
        $this->assertSame(9, ChallengeScores::best($me)['fact-or-myth']->score);
        $this->assertCount(3, ChallengeScores::history($me));
    }

    public function test_a_member_with_no_scores_sees_a_friendly_empty_page_and_a_guest_is_sent_to_log_in(): void
    {
        $this->get(route('account.scores'))->assertRedirect(route('login'));

        $this->actingAs($this->member());
        $this->get(route('account.scores'))->assertOk()->assertSee('No scores yet');
    }

    public function test_the_data_download_has_the_scores_and_deleting_the_account_removes_them(): void
    {
        $me = $this->member();
        QuizAttempt::create(['user_id' => $me->id, 'quiz' => 'who-said-it', 'score' => 7, 'total' => 10]);
        $this->actingAs($me);

        $json = json_decode($this->get(route('account.data'))->getContent(), true);
        $this->assertSame(7, $json['challenge_scores'][0]['score']);

        $this->post(route('account.delete'), ['delete_password' => 'password'])->assertRedirect();
        $this->assertSame(0, QuizAttempt::where('user_id', $me->id)->count());
    }
}
