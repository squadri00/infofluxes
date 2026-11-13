<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The emails the site sends. The words of each email are in lang/<locale>/newsletter.php (keys email_<kind>_subject and email_<kind>_body);
 * a Setting with the same name overrides them. In the text, {name}, {site}, {link}, {code} and {minutes} are replaced.
 * Sending never stops what the person was doing: if the mail server refuses, it is written to the log.
 */
class SiteMail
{
    public static function send(string $toEmail, string $kind, array $vars = []): bool
    {
        $vars += ['site' => Setting::get('site_name', config('app.name'))];

        $subject = static::fill(static::words("email_{$kind}_subject"), $vars);
        $body = static::fill(static::words("email_{$kind}_body"), $vars);

        if ($subject === '' || $body === '') {
            Log::warning("Email \"{$kind}\" has no text, so it was not sent.");

            return false;
        }

        try {
            Mail::raw($body, function ($message) use ($toEmail, $subject) {
                $message->to($toEmail)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning("Email \"{$kind}\" could not be sent: ".$e->getMessage());

            return false;
        }
    }

    private static function words(string $key): string
    {
        $own = Setting::get($key);
        if (is_string($own) && trim($own) !== '') {
            return $own;
        }

        $line = 'newsletter.'.$key;
        $text = __($line);

        return $text !== $line ? (string) $text : '';
    }

    public static function fill(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{'.$key.'}', (string) $value, $text);
        }

        return trim($text);
    }
}
