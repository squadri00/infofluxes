<?php

namespace App\Support;

use App\Models\NewsletterDelivery;
use App\Models\NewsletterIssue;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends an issue of the newsletter to the people who confirmed their address.
 * An issue is sent in batches (Setting newsletter_per_run, usually 100) because cheap hosting limits how many emails go out each hour:
 * "Send" starts the issue and sends the first batch; "Send the next batch" carries on until nobody is left.
 * Each person has one delivery line per issue, so nobody gets the same issue twice, even if a send is stopped and started again.
 */
class NewsletterSender
{
    public static function perRun(): int
    {
        $n = (int) Setting::get('newsletter_per_run', 100);

        return $n >= 1 ? min(500, $n) : 100;
    }

    /** How many people an issue would go to now. */
    public static function audience(): int
    {
        return NewsletterSubscriber::query()->where('status', 'active')->count();
    }

    /** The text of an issue made safe, and its plain-text twin for mail programs that show no pictures or styles. */
    public static function html(NewsletterIssue $issue): string
    {
        return Html::clean((string) $issue->body);
    }

    public static function plain(NewsletterIssue $issue, string $unsubscribe): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<\/(p|h[1-6]|li|blockquote|div)>|<br\s*\/?>/i', "\n", static::html($issue))), ENT_QUOTES | ENT_HTML5);
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text));

        return $issue->subject."\n\n".$text."\n\n--\n".Newsletter::words('newsletter_mail_footer').' '.$unsubscribe;
    }

    /** Send one copy of an issue to one address. Returns null when it went, or the reason it did not. */
    private static function deliver(NewsletterIssue $issue, string $email, string $unsubscribe): ?string
    {
        $site = (string) Setting::get('site_name', config('app.name'));

        try {
            $html = view('emails.newsletter', ['subject' => $issue->subject, 'site' => $site, 'body' => static::html($issue), 'unsubscribe' => $unsubscribe])->render();

            Mail::html($html, function ($message) use ($issue, $email, $unsubscribe) {
                $message->to($email)->subject($issue->subject)->text(static::plain($issue, $unsubscribe));
                $headers = $message->getSymfonyMessage()->getHeaders();
                $headers->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });

            return null;
        } catch (\Throwable $e) {
            Log::warning('Newsletter issue '.$issue->id.' could not be sent to one address: '.$e->getMessage());

            return mb_substr($e->getMessage(), 0, 290);
        }
    }

    /** A test copy to one address. Nothing is recorded and nobody on the list gets anything. */
    public static function sendTest(NewsletterIssue $issue, string $email): bool
    {
        $email = Newsletter::clean($email);

        return $email !== null && static::deliver($issue, $email, route('newsletter.show')) === null;
    }

    /** Lock the issue and list everyone who is subscribed now. Only a draft can be started. */
    public static function start(NewsletterIssue $issue): int
    {
        if (! $issue->isDraft()) {
            return $issue->recipients_total;
        }

        $ids = NewsletterSubscriber::query()->where('status', 'active')->orderBy('id')->pluck('id');
        foreach ($ids->chunk(500) as $chunk) {
            NewsletterDelivery::query()->insertOrIgnore($chunk->map(fn ($id) => [
                'newsletter_issue_id' => $issue->id, 'newsletter_subscriber_id' => $id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ])->all());
        }

        $issue->update(['status' => 'sending', 'recipients_total' => $ids->count(), 'started_at' => now()]);

        return $ids->count();
    }

    /**
     * Send the next batch of an issue that has started.
     *
     * @return array{sent: int, failed: int, left: int}
     */
    public static function sendBatch(NewsletterIssue $issue): array
    {
        $sent = $failed = 0;

        if ($issue->status === 'sending') {
            $batch = $issue->deliveries()->where('status', 'pending')->with('subscriber')->orderBy('id')->limit(static::perRun())->get();

            foreach ($batch as $delivery) {
                $person = $delivery->subscriber;

                // Someone who left after the issue was started gets nothing.
                if (! $person || $person->status !== 'active') {
                    $delivery->update(['status' => 'skipped']);

                    continue;
                }

                $problem = static::deliver($issue, $person->email, route('newsletter.unsubscribe', $person->unsubscribe_token));
                if ($problem === null) {
                    $delivery->update(['status' => 'sent', 'sent_at' => now()]);
                    $sent++;
                } else {
                    $delivery->update(['status' => 'failed', 'error' => $problem]);
                    $failed++;
                }
            }

            $issue->update([
                'sent_count' => $issue->deliveries()->where('status', 'sent')->count(),
                'failed_count' => $issue->deliveries()->where('status', 'failed')->count(),
            ]);

            if ($issue->pendingCount() === 0) {
                $issue->update(['status' => 'sent', 'finished_at' => now()]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'left' => $issue->pendingCount()];
    }

    /** Put the failed lines of an issue back in the queue, so the next batch tries them again. */
    public static function retryFailed(NewsletterIssue $issue): int
    {
        $n = $issue->deliveries()->where('status', 'failed')->update(['status' => 'pending', 'error' => null]);
        if ($n > 0 && $issue->status === 'sent') {
            $issue->update(['status' => 'sending', 'finished_at' => null]);
        }

        return $n;
    }
}
