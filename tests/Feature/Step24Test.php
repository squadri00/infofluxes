<?php

namespace Tests\Feature;

use App\Filament\Resources\Newsletter\Pages\ManageNewsletterIssues;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterIssue;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use App\Models\User;
use App\Support\NewsletterSender;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 24: the newsletter sending screen, and the Subscribe button under the social buttons in the footer. */
class Step24Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
        config(['mail.default' => 'array']);
        Mail::purge();
    }

    private function mails(): array
    {
        $transport = app('mailer')->getSymfonyTransport();

        return collect($transport->messages())->map(fn ($m) => $m->getOriginalMessage())->all();
    }

    private function subscribers(int $active, int $other = 0): void
    {
        foreach (range(1, $active) as $i) {
            NewsletterSubscriber::create(['email' => "reader{$i}@example.com", 'status' => 'active', 'unsubscribe_token' => str_repeat((string) ($i % 10), 30).$i]);
        }
        foreach (range(1, $other) as $i) {
            NewsletterSubscriber::create(['email' => "other{$i}@example.com", 'status' => $i % 2 ? 'pending' : 'unsubscribed', 'unsubscribe_token' => str_repeat('z', 30).$i]);
        }
    }

    private function issue(array $extra = []): NewsletterIssue
    {
        return NewsletterIssue::create(array_merge(['subject' => 'November news', 'body' => '<h2>Hello</h2><p>New <strong>topics</strong> this month.</p>', 'status' => 'draft'], $extra));
    }

    private function superuser(): User
    {
        return tap(User::factory()->create(['role' => 'superuser', 'status' => 'active']), fn ($u) => $this->actingAs($u));
    }

    // ---------------- the footer button ----------------

    public function test_the_footer_has_a_subscribe_button_right_under_the_social_buttons(): void
    {
        \App\Models\SocialLink::create(['platform' => 'facebook', 'url' => 'https://facebook.com/us']);

        $html = $this->get('/')->assertOk()->getContent();
        $brand = substr($html, strpos($html, 'site-footer__brand'), 4000);

        $this->assertLessThan(strpos($brand, 'newsletter-btn'), strpos($brand, 'social__btn--facebook'), 'the button comes after the social buttons');
        $this->assertStringContainsString('href="'.route('newsletter.show').'"', substr($brand, strpos($brand, 'newsletter-btn') - 40, 200));
        $this->assertStringContainsString('Subscribe', substr($brand, strpos($brand, 'newsletter-btn'), 700));
    }

    public function test_the_button_leaves_with_the_sign_up_when_it_is_switched_off(): void
    {
        Setting::put('newsletter_on', false);

        $this->get('/')->assertDontSee('newsletter-btn', false);
    }

    // ---------------- sending ----------------

    public function test_a_test_copy_goes_to_one_address_and_records_nothing(): void
    {
        $this->subscribers(3);
        $issue = $this->issue();

        $this->assertTrue(NewsletterSender::sendTest($issue, 'me@example.com'));

        $this->assertCount(1, $this->mails());
        $this->assertSame('me@example.com', $this->mails()[0]->getTo()[0]->getAddress());
        $this->assertSame('draft', $issue->fresh()->status);
        $this->assertSame(0, NewsletterDelivery::count());
    }

    public function test_an_email_has_the_site_name_the_text_and_an_unsubscribe_link_in_html_and_plain_text(): void
    {
        $this->subscribers(1);
        $issue = $this->issue();
        NewsletterSender::start($issue);
        NewsletterSender::sendBatch($issue->fresh());

        $mail = $this->mails()[0];
        $token = NewsletterSubscriber::first()->unsubscribe_token;

        $this->assertSame('November news', $mail->getSubject());
        $this->assertStringContainsString('New <strong>topics</strong>', $mail->getHtmlBody());
        $this->assertStringContainsString('/newsletter/unsubscribe/'.$token, $mail->getHtmlBody());
        $this->assertStringContainsString('New topics this month.', $mail->getTextBody());
        $this->assertStringContainsString('/newsletter/unsubscribe/'.$token, $mail->getTextBody());
        $this->assertStringContainsString('/newsletter/unsubscribe/'.$token, $mail->getHeaders()->get('List-Unsubscribe')->getBodyAsString(), 'the one-click unsubscribe header');
    }

    public function test_only_confirmed_people_get_the_issue_once_each(): void
    {
        $this->subscribers(3, 4);
        $issue = $this->issue();

        $this->assertSame(3, NewsletterSender::start($issue));
        NewsletterSender::sendBatch($issue->fresh());

        $to = collect($this->mails())->map(fn ($m) => $m->getTo()[0]->getAddress())->sort()->values()->all();
        $this->assertSame(['reader1@example.com', 'reader2@example.com', 'reader3@example.com'], $to);
        $this->assertSame('sent', $issue->fresh()->status);

        // Starting again, or sending again, sends nothing more.
        NewsletterSender::start($issue->fresh());
        NewsletterSender::sendBatch($issue->fresh());
        $this->assertCount(3, $this->mails());
    }

    public function test_an_issue_goes_in_batches_of_the_chosen_size(): void
    {
        Setting::put('newsletter_per_run', '2');
        $this->subscribers(5);
        $issue = $this->issue();
        NewsletterSender::start($issue);

        $this->assertSame(['sent' => 2, 'failed' => 0, 'left' => 3], NewsletterSender::sendBatch($issue->fresh()));
        $this->assertSame('sending', $issue->fresh()->status);
        $this->assertSame(['sent' => 2, 'failed' => 0, 'left' => 1], NewsletterSender::sendBatch($issue->fresh()));
        $this->assertSame(['sent' => 1, 'failed' => 0, 'left' => 0], NewsletterSender::sendBatch($issue->fresh()));

        $issue->refresh();
        $this->assertSame('sent', $issue->status);
        $this->assertSame([5, 5, 0], [$issue->recipients_total, $issue->sent_count, $issue->failed_count]);
        $this->assertNotNull($issue->finished_at);
    }

    public function test_someone_who_unsubscribes_after_the_start_gets_nothing(): void
    {
        $this->subscribers(2);
        $issue = $this->issue();
        NewsletterSender::start($issue);
        NewsletterSubscriber::where('email', 'reader2@example.com')->update(['status' => 'unsubscribed']);

        NewsletterSender::sendBatch($issue->fresh());

        $this->assertCount(1, $this->mails());
        $this->assertSame('skipped', NewsletterDelivery::whereHas('subscriber', fn ($q) => $q->where('email', 'reader2@example.com'))->first()->status);
    }

    public function test_a_failed_address_is_counted_and_can_be_tried_again(): void
    {
        $this->subscribers(2);
        $issue = $this->issue();
        NewsletterSender::start($issue);

        // Make the mail server refuse the first send only.
        $calls = 0;
        Mail::shouldReceive('html')->andReturnUsing(function () use (&$calls) {
            if (++$calls === 1) {
                throw new \RuntimeException('Mailbox full');
            }
        });

        $result = NewsletterSender::sendBatch($issue->fresh());
        $this->assertSame(['sent' => 1, 'failed' => 1, 'left' => 0], $result);
        $this->assertSame('Mailbox full', NewsletterDelivery::where('status', 'failed')->first()->error);

        $this->assertSame(1, NewsletterSender::retryFailed($issue->fresh()));
        $this->assertSame('sending', $issue->fresh()->status);
        $this->assertSame(['sent' => 1, 'failed' => 0, 'left' => 0], NewsletterSender::sendBatch($issue->fresh()));
        $this->assertSame('sent', $issue->fresh()->status);
    }

    public function test_the_text_of_an_issue_is_cleaned_of_scripts(): void
    {
        $this->subscribers(1);
        $issue = $this->issue(['body' => '<p>Safe</p><script>alert(1)</script>']);
        NewsletterSender::start($issue);
        NewsletterSender::sendBatch($issue->fresh());

        $this->assertStringNotContainsString('<script', $this->mails()[0]->getHtmlBody());
        $this->assertStringContainsString('Safe', $this->mails()[0]->getHtmlBody());
    }

    // ---------------- the admin screen ----------------

    public function test_the_screen_is_for_the_super_user_and_for_a_moderator_who_is_given_the_tick(): void
    {
        $this->actingAs($author = User::factory()->create(['role' => 'author', 'status' => 'active']));
        Livewire::test(ManageNewsletterIssues::class)->assertForbidden();

        $this->actingAs($mod = User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        Livewire::test(ManageNewsletterIssues::class)->assertForbidden();
        $mod->update(['admin_screens' => ['newsletter']]);
        Livewire::test(ManageNewsletterIssues::class)->assertSuccessful();

        $this->superuser();
        Livewire::test(ManageNewsletterIssues::class)->assertSuccessful();
    }

    public function test_a_new_issue_is_saved_as_a_clean_draft(): void
    {
        $this->superuser();

        Livewire::test(ManageNewsletterIssues::class)
            ->callAction('create', ['subject' => 'Hello readers', 'body' => '<p>Text</p><script>x</script>'])
            ->assertHasNoActionErrors();

        $issue = NewsletterIssue::first();
        $this->assertSame('draft', $issue->status);
        $this->assertStringNotContainsString('<script', $issue->body);
        $this->assertNotNull($issue->created_by);
    }

    public function test_the_send_button_starts_the_issue_and_sends_the_first_batch(): void
    {
        $this->superuser();
        Setting::put('newsletter_per_run', '2');
        $this->subscribers(3);
        $issue = $this->issue();

        Livewire::test(ManageNewsletterIssues::class)->callTableAction('send', $issue)->assertHasNoTableActionErrors();

        $issue->refresh();
        $this->assertSame('sending', $issue->status);
        $this->assertSame([3, 2], [$issue->recipients_total, $issue->sent_count]);

        Livewire::test(ManageNewsletterIssues::class)->callTableAction('next', $issue)->assertHasNoTableActionErrors();
        $this->assertSame('sent', $issue->fresh()->status);
        $this->assertCount(3, $this->mails());
    }

    public function test_sending_with_nobody_subscribed_does_not_lock_the_draft(): void
    {
        $this->superuser();
        $issue = $this->issue();

        Livewire::test(ManageNewsletterIssues::class)->callTableAction('send', $issue);

        $this->assertSame('draft', $issue->fresh()->status);
        $this->assertCount(0, $this->mails());
    }

    public function test_the_test_action_sends_one_copy_and_an_issue_that_started_cannot_be_changed_or_deleted(): void
    {
        $this->superuser();
        $this->subscribers(1);
        $issue = $this->issue();

        Livewire::test(ManageNewsletterIssues::class)->callTableAction('test', $issue, ['email' => 'me@example.com']);
        $this->assertCount(1, $this->mails());
        $this->assertSame('draft', $issue->fresh()->status);

        NewsletterSender::start($issue);
        $this->assertFalse(\App\Filament\Resources\Newsletter\NewsletterIssueResource::canEdit($issue->fresh()));
        $this->assertFalse(\App\Filament\Resources\Newsletter\NewsletterIssueResource::canDelete($issue->fresh()));
    }

    public function test_a_clone_is_a_fresh_draft(): void
    {
        $this->superuser();
        $issue = $this->issue(['status' => 'sent', 'recipients_total' => 5, 'sent_count' => 5]);

        Livewire::test(ManageNewsletterIssues::class)->callTableAction('replicate', $issue);

        $copy = NewsletterIssue::where('id', '!=', $issue->id)->first();
        $this->assertSame(['draft', 0, 0], [$copy->status, $copy->recipients_total, $copy->sent_count]);
    }

    public function test_the_batch_size_is_a_setting_with_safe_limits(): void
    {
        $this->assertSame(100, NewsletterSender::perRun());
        Setting::put('newsletter_per_run', '9999');
        $this->assertSame(500, NewsletterSender::perRun());
        Setting::put('newsletter_per_run', '0');
        $this->assertSame(100, NewsletterSender::perRun(), 'an empty or zero value means the usual one');
    }
}
