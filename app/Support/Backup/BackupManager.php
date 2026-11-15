<?php

namespace App\Support\Backup;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Makes, lists, downloads and tidies backups.
 * They live in storage/app/backups, outside the public folder, with a file that tells the web server never to serve them.
 * A backup never contains .env. The database copy is written with plain PHP, so it also works on a shared host.
 */
class BackupManager
{
    public const NAME_PATTERN = '/^(database|files)-\d{4}-\d{2}-\d{2}_\d{6}(-\d{1,3})?\.(sql\.gz|zip)$/';

    public const DEFAULT_KEEP_DAYS = 14;

    /** A backup older than this many days is called out of date. */
    public const STALE_DAYS = 2;

    public static function directory(): string
    {
        $dir = (string) config('infofluxes.backup_path', storage_path('app/backups'));
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0750, true);
        }
        // If this folder were ever served by mistake, the file keeps the copies closed.
        if (! is_file($dir.'/.htaccess')) {
            file_put_contents($dir.'/.htaccess', "Require all denied\nDeny from all\n");
        }

        return $dir;
    }

    public static function keepDays(): int
    {
        return max(1, min(365, (int) Setting::get('backup_keep_days', self::DEFAULT_KEEP_DAYS)));
    }

    /** The copy of the database, as a file name. */
    public static function makeDatabase(): string
    {
        static::relax();
        $name = static::freeName('database', 'sql.gz');
        $part = static::directory().'/'.$name.'.part';
        try {
            SqlDump::write($part);
            if (! SqlDump::isComplete($part)) {
                throw new \RuntimeException('The database copy did not finish correctly, so it was not kept.');
            }
        } catch (\Throwable $e) {
            @unlink($part);
            throw $e;
        }
        rename($part, static::directory().'/'.$name);

        return $name;
    }

    /** The copy of the uploaded pictures and files (storage/app/public), as a file name. */
    public static function makeFiles(): string
    {
        static::relax();
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('This server cannot make zip files (the zip extension is off).');
        }
        $name = static::freeName('files', 'zip');
        $part = static::directory().'/'.$name.'.part';
        $source = (string) config('infofluxes.backup_source', storage_path('app/public'));
        $zip = new \ZipArchive;
        if ($zip->open($part, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('The zip file could not be created.');
        }
        $count = 0;
        if (is_dir($source)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->isFile() && $file->getFilename() !== '.gitignore') {
                    $zip->addFile($file->getPathname(), str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1)));
                    $count++;
                }
            }
        }
        if ($count === 0) {
            $zip->addFromString('README.txt', "There were no uploaded files when this copy was made.\n");
        }
        if (! $zip->close()) {
            @unlink($part);
            throw new \RuntimeException('The zip file could not be finished.');
        }
        rename($part, static::directory().'/'.$name);

        return $name;
    }

    /**
     * Both copies, then the old ones are tidied. An error in one does not stop the other.
     *
     * @param  'all'|'database'|'files'  $only
     * @return array{made: list<string>, removed: int, errors: list<string>}
     */
    public static function run(string $only = 'all'): array
    {
        $made = [];
        $errors = [];
        foreach (['database' => 'makeDatabase', 'files' => 'makeFiles'] as $kind => $method) {
            if ($only !== 'all' && $only !== $kind) {
                continue;
            }
            try {
                $made[] = static::{$method}();
            } catch (\Throwable $e) {
                $errors[] = ucfirst($kind).': '.$e->getMessage();
            }
        }
        $removed = static::prune();

        Setting::put('backup_last_run', now()->toDateTimeString());
        Setting::put('backup_last_errors', $errors ? implode(' | ', $errors) : '');

        return ['made' => $made, 'removed' => $removed, 'errors' => $errors];
    }

    /**
     * All copies, newest first.
     *
     * @return list<array{name: string, kind: string, bytes: int, made_at: Carbon}>
     */
    public static function all(): array
    {
        $out = [];
        foreach (File::files(static::directory()) as $file) {
            if (preg_match(self::NAME_PATTERN, $file->getFilename(), $m)) {
                $out[] = ['name' => $file->getFilename(), 'kind' => $m[1], 'bytes' => $file->getSize(), 'made_at' => Carbon::createFromTimestamp($file->getMTime())];
            }
        }
        usort($out, fn ($a, $b) => $b['made_at']->timestamp <=> $a['made_at']->timestamp ?: strcmp($b['name'], $a['name']));

        return $out;
    }

    /** The full path of a copy, or null when the name is not one of ours or the file is gone. A name from a browser is never trusted. */
    public static function path(string $name): ?string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }
        $path = static::directory().'/'.$name;

        return is_file($path) ? $path : null;
    }

    public static function delete(string $name): bool
    {
        $path = static::path($name);

        return $path !== null && @unlink($path);
    }

    /** Copies older than the keep time are removed. The newest copy of each kind is always kept. */
    public static function prune(?int $keepDays = null): int
    {
        $limit = now()->subDays($keepDays ?? static::keepDays())->timestamp;
        $newest = [];
        foreach (static::all() as $copy) {
            $newest[$copy['kind']] ??= $copy['name'];
        }
        $removed = 0;
        foreach (static::all() as $copy) {
            if ($copy['made_at']->timestamp < $limit && $newest[$copy['kind']] !== $copy['name'] && static::delete($copy['name'])) {
                $removed++;
            }
        }

        return $removed;
    }

    public static function last(string $kind): ?Carbon
    {
        foreach (static::all() as $copy) {
            if ($copy['kind'] === $kind) {
                return $copy['made_at'];
            }
        }

        return null;
    }

    public static function usedBytes(): int
    {
        return array_sum(array_column(static::all(), 'bytes'));
    }

    /** No database copy, or the last one is older than two days. */
    public static function isStale(): bool
    {
        $last = static::last('database');

        return $last === null || $last->lt(now()->subDays(self::STALE_DAYS));
    }

    public static function human(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $unit) {
            if ($bytes < 1024 ** ($i + 1) || $unit === 'GB') {
                return ($i === 0 ? $bytes : round($bytes / 1024 ** $i, 1)).' '.$unit;
            }
        }

        return $bytes.' B';
    }

    /** A new name; if two copies are made in the same second, a number is added. */
    private static function freeName(string $kind, string $ext): string
    {
        $base = $kind.'-'.now()->format('Y-m-d_His');
        $name = $base.'.'.$ext;
        $n = 1;
        while (is_file(static::directory().'/'.$name)) {
            $name = $base.'-'.$n++.'.'.$ext;
        }

        return $name;
    }

    /** A big site can take a while to copy. */
    private static function relax(): void
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
    }
}
