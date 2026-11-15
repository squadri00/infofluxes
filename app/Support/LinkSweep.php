<?php

namespace App\Support;

use App\Models\Article;
use App\Models\LinkCheck;
use Illuminate\Support\Facades\DB;

/**
 * Checks the source links of all articles, a few at a time and politely (one request a second to the same website).
 * The table link_checks holds one row for each link of an article. sync() makes it match the articles, and the checks fill in what was found.
 * A broken link never hides an article. It is only shown to a person who can fix it.
 */
class LinkSweep
{
    /** A link is checked again when its last check is older than this many days. */
    public const DUE_DAYS = 7;

    /** @var array<string, float> when each website was last asked (so the same site is not asked twice within a second) */
    private static array $lastAsked = [];

    /** Makes the table match the sources of the articles. @return array{total: int, added: int, removed: int} */
    public static function sync(): array
    {
        $added = 0;
        $withLinks = [];
        Article::query()->whereNotNull('custom_data')->select(['id', 'custom_data'])->chunkById(500, function ($articles) use (&$added, &$withLinks) {
            foreach ($articles as $article) {
                [$has, $new] = static::syncArticle($article);
                if ($has) {
                    $withLinks[] = $article->id;
                }
                $added += $new;
            }
        });
        // Rows of articles that are gone (or no longer have any link) are removed.
        $removed = LinkCheck::query()->whereNotIn('article_id', $withLinks)->delete();

        return ['total' => LinkCheck::count(), 'added' => $added, 'removed' => $removed];
    }

    /**
     * Makes the rows of one article match its sources.
     *
     * @return array{0: bool, 1: int} whether it has links, and how many rows were added
     */
    public static function syncArticle(Article $article): array
    {
        $wanted = [];
        foreach ((array) ($article->custom_data['sources'] ?? []) as $source) {
            $url = trim((string) ($source['url'] ?? ''));
            if ($url !== '') {
                $wanted[sha1($url)] = ['url' => mb_substr($url, 0, 2000), 'ref' => mb_substr((string) ($source['ref'] ?? ''), 0, 400)];
            }
        }
        $rows = LinkCheck::query()->where('article_id', $article->id)->get()->keyBy('url_hash');
        $added = 0;
        foreach ($wanted as $hash => $link) {
            if ($rows->has($hash)) {
                if ($rows[$hash]->ref !== $link['ref']) {
                    $rows[$hash]->update(['ref' => $link['ref']]);
                }

                continue;
            }
            LinkCheck::create(['article_id' => $article->id, 'url' => $link['url'], 'url_hash' => $hash, 'ref' => $link['ref']]);
            $added++;
        }
        $gone = $rows->keys()->diff(array_keys($wanted))->all();
        if ($gone !== []) {
            LinkCheck::query()->where('article_id', $article->id)->whereIn('url_hash', $gone)->delete();
        }

        return [$wanted !== [], $added];
    }

    /** Asks every link (that is not ignored) to be checked in the next steps. @return int how many */
    public static function queueAll(): int
    {
        return LinkCheck::query()->where('ignored', false)->update(['queued' => true]);
    }

    /** @return array{total: int, queued: int} */
    public static function progress(): array
    {
        return ['total' => LinkCheck::query()->where('ignored', false)->count(), 'queued' => LinkCheck::query()->where('queued', true)->where('ignored', false)->count()];
    }

    /**
     * Checks the next queued links: at most $limit, and it stops after $seconds.
     *
     * @return int how many were checked
     */
    public static function step(int $limit = 3, int $seconds = 20): int
    {
        $rows = LinkCheck::query()->where('queued', true)->where('ignored', false)->orderBy('checked_at')->orderBy('id')->limit($limit)->get();

        return static::checkRows($rows, $seconds);
    }

    /** The command: checks the links that are due, up to $limit, within $seconds. @return int how many were checked */
    public static function runDue(int $limit = 60, int $seconds = 50): int
    {
        static::sync();
        $rows = LinkCheck::query()->where('ignored', false)
            ->where(fn ($q) => $q->where('queued', true)->orWhereNull('checked_at')->orWhere('checked_at', '<', now()->subDays(self::DUE_DAYS)))
            ->orderByRaw('checked_at IS NULL DESC')->orderBy('checked_at')->limit($limit)->get();

        return static::checkRows($rows, $seconds);
    }

    /** All the links of one article, now. @return array{checked: int, broken: int, blocked: int} */
    public static function checkArticle(Article $article): array
    {
        static::syncArticle($article);
        $rows = LinkCheck::query()->where('article_id', $article->id)->get();
        $checked = static::checkRows($rows, 60);
        $fresh = LinkCheck::query()->where('article_id', $article->id)->get();

        return ['checked' => $checked, 'broken' => $fresh->where('status', 'broken')->count(), 'blocked' => $fresh->where('status', 'blocked')->count()];
    }

    /** One link, now. */
    public static function checkRow(LinkCheck $link): LinkCheck
    {
        static::politeWait($link->url);
        $result = LinkChecker::probe($link->url);
        $failing = $result['status'] === 'broken';
        $link->update([
            'status' => $result['status'],
            'http_status' => $result['code'],
            'checked_at' => now(),
            'queued' => false,
            'failed_since' => $failing ? ($link->failed_since ?? now()) : null,
        ]);

        return $link;
    }

    /** @param  \Illuminate\Support\Collection<int, LinkCheck>  $rows */
    private static function checkRows($rows, int $seconds): int
    {
        $start = microtime(true);
        $done = 0;
        foreach ($rows as $row) {
            if ($done > 0 && microtime(true) - $start > $seconds) {
                break;
            }
            static::checkRow($row);
            $done++;
        }

        return $done;
    }

    /** The same website is asked at most once a second. */
    private static function politeWait(string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return;
        }
        $now = microtime(true);
        $wait = (self::$lastAsked[$host] ?? 0) + 1.0 - $now;
        if ($wait > 0 && ! app()->runningUnitTests()) {
            usleep((int) ($wait * 1_000_000));
        }
        self::$lastAsked[$host] = microtime(true);
    }

    /** A person opened the link by hand and it was fine. */
    public static function markByHand(LinkCheck $link, ?int $userId): void
    {
        $link->update(['manual_ok_at' => now(), 'manual_ok_by' => $userId]);
    }
}
