<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the numbers of Google Analytics 4 for the admin dashboard (Google Analytics Data API, read only).
 * It needs the number of the GA4 property (Setting analytics_property_id) and a Google "service account" key file
 * saved on the server (storage/app/private/analytics/service-account.json, never in the public folder and never shown).
 * The key is used only to ask Google for a short pass; nothing is sent to Google except the questions about the property.
 */
class GoogleAnalytics
{
    public const KEY_FILE = 'analytics/service-account.json';

    public const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    private const API = 'https://analyticsdata.googleapis.com/v1beta/properties/';

    public const CACHE_MINUTES = 10;

    public static function propertyId(): string
    {
        return preg_replace('/\D/', '', (string) Setting::get('analytics_property_id'));
    }

    private static function disk()
    {
        return Storage::disk('local');
    }

    public static function hasKey(): bool
    {
        return static::disk()->exists(static::KEY_FILE);
    }

    /** @return array{client_email: string, private_key: string}|null */
    private static function key(): ?array
    {
        if (! static::hasKey()) {
            return null;
        }
        $k = json_decode((string) static::disk()->get(static::KEY_FILE), true);

        return is_array($k) && ! empty($k['client_email']) && ! empty($k['private_key']) ? $k : null;
    }

    /** The email of the service account: this is what must be added as a Viewer in Google Analytics. It is not a secret. */
    public static function serviceEmail(): ?string
    {
        return static::key()['client_email'] ?? null;
    }

    public static function configured(): bool
    {
        return static::propertyId() !== '' && static::key() !== null;
    }

    /** Checks and saves the key file. Returns an error message, or null when it is saved. */
    public static function saveKey(string $json): ?string
    {
        $k = json_decode($json, true);
        if (! is_array($k) || ($k['type'] ?? '') !== 'service_account' || empty($k['client_email']) || ! str_contains((string) ($k['private_key'] ?? ''), 'PRIVATE KEY')) {
            return 'This is not a Google service account key file. Please download the JSON key again from Google Cloud.';
        }
        if (! @openssl_pkey_get_private($k['private_key'])) {
            return 'The private key in this file cannot be read. Please download a new JSON key.';
        }
        static::disk()->put(static::KEY_FILE, json_encode($k, JSON_UNESCAPED_SLASHES));
        Cache::forget('ga:token');
        static::forgetReports();

        return null;
    }

    public static function removeKey(): void
    {
        static::disk()->delete(static::KEY_FILE);
        Cache::forget('ga:token');
        static::forgetReports();
    }

    public static function forgetReports(): void
    {
        foreach ([7, 28, 90] as $d) {
            Cache::forget("ga:dash:{$d}:".static::propertyId());
        }
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    /** A short pass from Google (valid about an hour), asked for with the signed key. */
    private static function token(): string
    {
        return Cache::remember('ga:token', 3300, function () {
            $key = static::key() ?? throw new GaException('The key file is missing. Upload it again.');
            $now = time();
            $claims = static::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.static::b64(json_encode([
                'iss' => $key['client_email'], 'scope' => static::SCOPE, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ]));
            if (! openssl_sign($claims, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new GaException('The key file could not be used to sign in. Upload a new key.');
            }
            $r = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $claims.'.'.static::b64($signature),
            ]);
            if (! $r->ok() || ! $r->json('access_token')) {
                throw new GaException('Google did not accept the key ('.($r->json('error_description') ?: $r->json('error') ?: 'HTTP '.$r->status()).'). Upload a fresh key, and check that the key was not deleted in Google Cloud.');
            }

            return (string) $r->json('access_token');
        });
    }

    /** One question to Google. @param 'runReport'|'runRealtimeReport' $method */
    public static function ask(string $method, array $body): array
    {
        $r = Http::withToken(static::token())->timeout(25)->acceptJson()->post(static::API.static::propertyId().':'.$method, $body);
        if (! $r->ok()) {
            throw new GaException(static::explain($r));
        }

        return (array) $r->json();
    }

    private static function explain(Response $r): string
    {
        $msg = (string) ($r->json('error.message') ?? '');
        $status = (string) ($r->json('error.status') ?? '');
        $email = static::serviceEmail() ?: 'the service account email';
        if (str_contains($msg, 'has not been used') || str_contains($msg, 'is disabled') || $status === 'SERVICE_DISABLED') {
            return 'The "Google Analytics Data API" is switched off for your Google Cloud project. Open it in Google Cloud and press Enable, wait a minute, then press Refresh.';
        }
        if ($r->status() === 403) {
            return "Google says this key may not read the property. In Google Analytics go to Admin > Property access management and add {$email} with the role Viewer. Then press Refresh.";
        }
        if ($r->status() === 400 || $r->status() === 404) {
            return 'Google cannot find that property. Check the Property ID: it is a number (like 123456789), not the G-... measurement ID. '.($msg !== '' ? '('.$msg.')' : '');
        }
        if ($r->status() === 401) {
            Cache::forget('ga:token');

            return 'Google refused the sign-in. Press Refresh. If it happens again, upload a new key.';
        }

        return 'Google Analytics did not answer well (HTTP '.$r->status().($msg !== '' ? ': '.$msg : '').'). Try again in a few minutes.';
    }

    /** @return array<int, array{dims: list<string>, metrics: list<float>}> */
    private static function rows(array $response): array
    {
        $out = [];
        foreach ($response['rows'] ?? [] as $row) {
            $out[] = [
                'dims' => array_map(fn ($d) => (string) ($d['value'] ?? ''), $row['dimensionValues'] ?? []),
                'metrics' => array_map(fn ($m) => (float) ($m['value'] ?? 0), $row['metricValues'] ?? []),
            ];
        }

        return $out;
    }

    private static function top(string $dimension, string $metric, int $limit, array $range): array
    {
        return static::rows(static::ask('runReport', [
            'dateRanges' => [$range], 'dimensions' => [['name' => $dimension]], 'metrics' => [['name' => $metric]],
            'orderBys' => [['metric' => ['metricName' => $metric], 'desc' => true]], 'limit' => $limit,
        ]));
    }

    /**
     * Everything the dashboard shows for the last $days days, compared with the $days before. Kept 10 minutes.
     *
     * @return array<string, mixed>
     */
    public static function dashboard(int $days, bool $fresh = false): array
    {
        $days = in_array($days, [7, 28, 90], true) ? $days : 28;
        $key = "ga:dash:{$days}:".static::propertyId();
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, static::CACHE_MINUTES * 60, function () use ($days) {
            $end = now()->startOfDay();
            $start = $end->copy()->subDays($days - 1);
            $prevEnd = $start->copy()->subDay();
            $prevStart = $prevEnd->copy()->subDays($days - 1);
            $cur = ['startDate' => $start->toDateString(), 'endDate' => $end->toDateString()];
            $prev = ['startDate' => $prevStart->toDateString(), 'endDate' => $prevEnd->toDateString()];

            // The totals, this period and the one before.
            $names = ['activeUsers', 'newUsers', 'sessions', 'screenPageViews', 'averageSessionDuration', 'engagementRate'];
            $sum = static::ask('runReport', ['dateRanges' => [$cur, $prev], 'metrics' => array_map(fn ($n) => ['name' => $n], $names)]);
            $totals = ['now' => array_fill_keys($names, 0.0), 'before' => array_fill_keys($names, 0.0)];
            foreach (static::rows($sum) as $row) {
                $which = ($row['dims'][0] ?? 'date_range_0') === 'date_range_1' ? 'before' : 'now';
                foreach ($names as $i => $n) {
                    $totals[$which][$n] = $row['metrics'][$i] ?? 0.0;
                }
            }
            $now = $totals['now'];
            $before = $totals['before'];

            // Visitors and page views, one point for each day (days without a visit count as 0).
            $perDay = [];
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $perDay[$d->format('Ymd')] = ['date' => $d->toDateString(), 'visitors' => 0, 'views' => 0];
            }
            foreach (static::rows(static::ask('runReport', ['dateRanges' => [$cur], 'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'activeUsers'], ['name' => 'screenPageViews']], 'limit' => 400])) as $row) {
                if (isset($perDay[$row['dims'][0]])) {
                    $perDay[$row['dims'][0]]['visitors'] = (int) $row['metrics'][0];
                    $perDay[$row['dims'][0]]['views'] = (int) $row['metrics'][1];
                }
            }

            $pages = static::rows(static::ask('runReport', [
                'dateRanges' => [$cur], 'dimensions' => [['name' => 'pageTitle'], ['name' => 'pagePath']], 'metrics' => [['name' => 'screenPageViews'], ['name' => 'activeUsers']],
                'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]], 'limit' => 10,
            ]));

            return [
                'from' => $cur['startDate'], 'to' => $cur['endDate'], 'days' => $days, 'fetched_at' => now()->toDateTimeString(),
                'now' => $now, 'before' => $before,
                'per_day' => array_values($perDay),
                'pages' => array_map(fn ($r) => ['title' => $r['dims'][0], 'path' => $r['dims'][1], 'views' => (int) $r['metrics'][0], 'visitors' => (int) $r['metrics'][1]], $pages),
                'channels' => array_map(fn ($r) => ['name' => $r['dims'][0], 'value' => (int) $r['metrics'][0]], static::top('sessionDefaultChannelGroup', 'sessions', 8, $cur)),
                'sources' => array_map(fn ($r) => ['name' => $r['dims'][0], 'value' => (int) $r['metrics'][0]], static::top('sessionSource', 'sessions', 8, $cur)),
                'countries' => array_map(fn ($r) => ['name' => $r['dims'][0], 'value' => (int) $r['metrics'][0]], static::top('country', 'activeUsers', 8, $cur)),
                'devices' => array_map(fn ($r) => ['name' => $r['dims'][0], 'value' => (int) $r['metrics'][0]], static::top('deviceCategory', 'activeUsers', 4, $cur)),
            ];
        });
    }

    /** Who is on the site in the last 30 minutes, and on which pages. Asked every time (it is not kept). */
    public static function realtime(): array
    {
        $total = static::rows(static::ask('runRealtimeReport', ['metrics' => [['name' => 'activeUsers']]]));
        $pages = static::rows(static::ask('runRealtimeReport', [
            'dimensions' => [['name' => 'unifiedScreenName']], 'metrics' => [['name' => 'activeUsers']],
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]], 'limit' => 5,
        ]));

        return [
            'users' => (int) ($total[0]['metrics'][0] ?? 0),
            'pages' => array_map(fn ($r) => ['name' => $r['dims'][0], 'value' => (int) $r['metrics'][0]], $pages),
        ];
    }

    /** Google's channel names, in plain words. */
    public static function channelName(string $name): string
    {
        return [
            'Organic Search' => 'Search engines (Google, Bing...)', 'Direct' => 'Typed the address or a bookmark', 'Organic Social' => 'Social networks',
            'Referral' => 'Links on other websites', 'Paid Search' => 'Paid search ads', 'Paid Social' => 'Paid social ads', 'Email' => 'Email',
            'Organic Video' => 'Video sites', 'Unassigned' => 'Not known', 'Display' => 'Banner ads', 'Organic Shopping' => 'Shopping',
        ][$name] ?? $name;
    }

    public static function deviceName(string $name): string
    {
        return ['desktop' => 'Computer', 'mobile' => 'Phone', 'tablet' => 'Tablet', 'smart tv' => 'Smart TV'][strtolower($name)] ?? ucfirst($name);
    }
}
