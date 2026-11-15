<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * An old web address that leads to a new one (a 301 "moved for good" unless said otherwise).
 * Auto ones are made by themselves when the address of an article, a story, a category, a page or a contributor changes,
 * so a shared or bookmarked link keeps working. A redirect is only used when nothing lives at the old address.
 */
class Redirect extends Model
{
    use LogsActivity;

    protected $guarded = [];

    /** Beginnings of addresses that are never redirected (the admin and the technical ones). */
    public const NEVER = ['/admin', '/livewire', '/storage', '/up', '/build'];

    /** The most steps followed from one address to the next (an old address that leads to an older one). */
    public const MAX_HOPS = 5;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_hit_at' => 'datetime', 'custom_data' => 'array'];
    }

    protected static function booted(): void
    {
        // The address is always kept in the same shape, and its look-up key follows it.
        static::saving(function (self $r) {
            $r->from_path = static::normalize((string) $r->from_path);
            $r->from_key = mb_strtolower($r->from_path);
            if (! static::isExternal((string) $r->to)) {
                $r->to = static::normalize((string) $r->to);
            }
        });
    }

    public function activityLabel(): string
    {
        return $this->from_path.' → '.$this->to;
    }

    /** "/Old-Address/?x=1#top" becomes "/Old-Address". Only the path; no trailing slash. */
    public static function normalize(string $path): string
    {
        $path = trim($path);
        $only = parse_url($path, PHP_URL_PATH);
        $only = is_string($only) ? $only : $path;
        $only = '/'.trim($only, '/');

        return $only === '' ? '/' : $only;
    }

    public static function isExternal(string $to): bool
    {
        return (bool) preg_match('~^https?://~i', trim($to));
    }

    public static function isNever(string $path): bool
    {
        $key = mb_strtolower(static::normalize($path));
        foreach (self::NEVER as $prefix) {
            if ($key === $prefix || str_starts_with($key, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function tableExists(): bool
    {
        static $known = null;
        if ($known === true) {
            return true;
        }
        $known = Schema::hasTable('redirects');

        return $known;
    }

    /**
     * An address changed. The old one now leads to the new one; anything that led to the old one is moved on to the new one
     * (so there are no chains), and a redirect that stood at the new address is removed (the new address lives now).
     */
    public static function remember(string $fromPath, string $toPath, ?string $subjectType = null, ?int $subjectId = null, ?string $note = null): ?self
    {
        $from = static::normalize($fromPath);
        $to = static::normalize($toPath);
        if (mb_strtolower($from) === mb_strtolower($to) || static::isNever($from) || ! static::tableExists()) {
            return null;
        }
        static::freePath($to);
        static::query()->whereRaw('LOWER(`to`) = ?', [mb_strtolower($from)])->each(fn (self $r) => $r->update(['to' => $to]));
        static::query()->where('from_key', mb_strtolower($to))->delete();

        $existing = static::query()->where('from_key', mb_strtolower($from))->first();
        if ($existing && $existing->type === 'manual') {
            return $existing;   // a redirect someone made by hand is not overwritten
        }

        return static::query()->updateOrCreate(
            ['from_key' => mb_strtolower($from)],
            ['from_path' => $from, 'to' => $to, 'type' => 'auto', 'code' => 301, 'is_active' => true, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'note' => $note],
        );
    }

    /** A record now lives at this address, so no redirect may start there. */
    public static function freePath(string $path): void
    {
        if (static::tableExists()) {
            static::query()->where('from_key', mb_strtolower(static::normalize($path)))->get()->each->delete();
        }
    }

    /**
     * Where an old address leads (following redirects that lead to other redirects), or null.
     *
     * @return array{id: int, to: string, code: int}|null
     */
    public static function resolve(string $path): ?array
    {
        if (! static::tableExists() || static::isNever($path)) {
            return null;
        }
        $key = mb_strtolower(static::normalize($path));
        $seen = [$key];
        $first = null;
        $hit = null;
        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            $hit = static::query()->where('from_key', $key)->where('is_active', true)->first();
            if (! $hit) {
                break;
            }
            $first ??= $hit;
            if (static::isExternal($hit->to)) {
                return ['id' => $first->id, 'to' => $hit->to, 'code' => (int) $hit->code];
            }
            $key = mb_strtolower(static::normalize($hit->to));
            if (in_array($key, $seen, true)) {
                return null;   // a loop: it leads back to where it started
            }
            $seen[] = $key;
            $last = $hit;
        }

        return $first && isset($last) ? ['id' => $first->id, 'to' => $last->to, 'code' => (int) $first->code] : null;
    }
}
