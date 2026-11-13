<?php

namespace App\Support;

use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The newsletter list. A person is only "subscribed" after typing a code that was sent to their own address.
 * The code is never stored, only a one-way fingerprint of it, and it works for a short time and a few tries.
 */
class Newsletter
{
    public const MAX_TRIES = 5;

    public const COOLDOWN_SECONDS = 60;

    public const DAILY_CODES = 8;

    public static function enabled(): bool
    {
        return filter_var(Setting::get('newsletter_on', true), FILTER_VALIDATE_BOOLEAN);
    }

    public static function minutes(): int
    {
        return max(1, min(120, (int) Setting::get('newsletter_code_minutes', 15)));
    }

    /** The words of the sign-up, from lang/<locale>/newsletter.php. */
    public static function words(string $key): string
    {
        $line = 'newsletter.'.$key;
        $text = __($line);

        return $text !== $line ? (string) $text : $key;
    }

    /** Lower case, no spaces, and only something that looks like an address. Null when it does not. */
    public static function clean(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return $email !== '' && strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private static function fingerprint(string $email, string $code): string
    {
        return hash_hmac('sha256', $email.'|'.$code, (string) config('app.key'));
    }

    /**
     * Send a code. Answers: invalid, already, wait (with "seconds"), limit, failed, sent.
     *
     * @return array{status: string, seconds?: int}
     */
    public static function request(?string $rawEmail, ?string $source = 'site'): array
    {
        $email = static::clean($rawEmail);
        if ($email === null) {
            return ['status' => 'invalid'];
        }

        $person = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($person && $person->status === 'active') {
            return ['status' => 'already'];
        }

        if ($person?->code_sent_at && $person->code_sent_at->gt(now()->subSeconds(static::COOLDOWN_SECONDS))) {
            return ['status' => 'wait', 'seconds' => max(1, static::COOLDOWN_SECONDS - (int) $person->code_sent_at->diffInSeconds(now()))];
        }

        $key = 'newsletter-codes:'.sha1($email).':'.now()->toDateString();
        if (RateLimiter::tooManyAttempts($key, static::DAILY_CODES)) {
            return ['status' => 'limit'];
        }
        RateLimiter::hit($key, 86400);

        $code = (string) random_int(100000, 999999);

        $person ??= new NewsletterSubscriber(['email' => $email, 'unsubscribe_token' => Str::random(48), 'source' => $source]);
        $person->fill([
            'status' => 'pending',
            'code_hash' => static::fingerprint($email, $code),
            'code_expires_at' => now()->addMinutes(static::minutes()),
            'code_sent_at' => now(),
            'attempts' => 0,
            'consent_at' => now(),
            'language' => app()->getLocale(),
            'unsubscribed_at' => null,
        ])->save();

        $sent = SiteMail::send($email, 'newsletter_code', ['code' => $code, 'minutes' => static::minutes(), 'name' => '']);

        return ['status' => $sent ? 'sent' : 'failed'];
    }

    /**
     * Check a code. Answers: none, locked, expired, wrong (with "left"), ok.
     *
     * @return array{status: string, left?: int}
     */
    public static function confirm(?string $rawEmail, ?string $rawCode): array
    {
        $email = static::clean($rawEmail);
        $code = preg_replace('/\D/', '', (string) $rawCode);

        $person = $email ? NewsletterSubscriber::query()->where('email', $email)->first() : null;
        if (! $person || $person->status !== 'pending' || ! $person->code_hash) {
            return ['status' => 'none'];
        }

        if ($person->attempts >= static::MAX_TRIES) {
            return ['status' => 'locked'];
        }

        if (! $person->code_expires_at || $person->code_expires_at->isPast()) {
            return ['status' => 'expired'];
        }

        if (strlen($code) !== 6 || ! hash_equals($person->code_hash, static::fingerprint($email, $code))) {
            $person->increment('attempts');

            return ['status' => 'wrong', 'left' => max(0, static::MAX_TRIES - (int) $person->attempts)];
        }

        $person->forceFill(['status' => 'active', 'confirmed_at' => now(), 'code_hash' => null, 'code_expires_at' => null, 'attempts' => 0])->save();

        SiteMail::send($email, 'newsletter_welcome', ['name' => '', 'link' => route('newsletter.unsubscribe', $person->unsubscribe_token)]);

        return ['status' => 'ok'];
    }

    public static function unsubscribe(string $token): ?NewsletterSubscriber
    {
        $person = NewsletterSubscriber::query()->where('unsubscribe_token', $token)->first();

        if ($person && $person->status !== 'unsubscribed') {
            $person->forceFill(['status' => 'unsubscribed', 'unsubscribed_at' => now(), 'code_hash' => null, 'code_expires_at' => null])->save();
        }

        return $person;
    }

    /** The sentence that answers a result, with :email, :minutes, :seconds and :left filled in. */
    public static function message(array $result, ?string $email = null): string
    {
        $key = match ($result['status']) {
            'sent' => 'newsletter_sent',
            'already' => 'newsletter_already',
            'wait' => 'newsletter_wait',
            'limit' => 'newsletter_limit',
            'failed' => 'newsletter_failed',
            'invalid' => 'newsletter_invalid',
            'ok' => 'newsletter_done',
            'wrong' => 'newsletter_wrong',
            'expired' => 'newsletter_expired',
            'locked' => 'newsletter_locked',
            default => 'newsletter_none',
        };

        return str_replace(
            [':email', ':minutes', ':seconds', ':left'],
            [(string) $email, (string) static::minutes(), (string) ($result['seconds'] ?? ''), (string) ($result['left'] ?? '')],
            static::words($key),
        );
    }
}
