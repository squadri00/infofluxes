<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Asks a web address "do you open?". Only http and https to public servers; never a local or private address.
 * The answer is kept for ten minutes, so pressing Check and then Save does not ask twice.
 * "ok" = the page opened; "broken" = an error or no answer; "skipped" = not a web address we may visit.
 */
class LinkChecker
{
    public const MAX_LINKS = 30;

    /** @return 'ok'|'broken'|'skipped' */
    public static function check(string $url): string
    {
        $url = trim($url);

        return Cache::remember('linkcheck:'.md5($url), 600, fn () => static::ask($url));
    }

    /** @return 'ok'|'broken'|'skipped' */
    private static function ask(string $url): string
    {
        if (! static::allowed($url)) {
            return 'skipped';
        }
        try {
            $opts = ['User-Agent' => 'InfoFluxesLinkCheck/1.0'];
            $r = Http::timeout(6)->withHeaders($opts)->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['http', 'https']]])->head($url);
            if ($r->status() === 405 || $r->status() === 403 || $r->status() === 501 || $r->status() >= 400) {
                $r = Http::timeout(8)->withHeaders($opts)->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['http', 'https']], 'stream' => true])->get($url);
            }

            return $r->successful() ? 'ok' : 'broken';
        } catch (\Throwable) {
            return 'broken';
        }
    }

    /** A public http(s) address only: no other scheme, no login in the address, no local or private server. */
    public static function allowed(string $url): bool
    {
        $p = parse_url($url);
        if (! $p || ! in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            return false;
        }
        $host = strtolower($p['host']);
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || ! str_contains($host, '.') && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return true; // could not resolve here; the request itself will say "broken"
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
