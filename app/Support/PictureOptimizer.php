<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the pictures that are already on the site light: every one that is not a WebP, or is heavier or wider than the set limits
 * (see App\Support\ImageStore), is saved again as a WebP, and the place that uses it is pointed at the new file.
 * The original is not deleted: it is moved to a private folder (storage/app/private/originals) that visitors cannot reach.
 * Pictures that nothing uses are left alone and only counted. The story pictures are not touched (they are WebP of about 200 KB already).
 * Without "apply" nothing is changed: it only says what would happen.
 */
class PictureOptimizer
{
    /** folder => [table, column] : where the pictures of that folder are used */
    private const PLACES = [
        'categories' => [['categories', 'image']],
        'hero' => [['hero_slides', 'image']],
        'people' => [['people', 'photo']],
        'facts' => [['did_you_know_entries', 'image']],
        'articles' => [['article_images', 'image'], ['articles', 'main_image']],
    ];

    /**
     * @return array{files: list<array{path: string, before: int, after: int|null, status: string}>, saved: int, unused: list<string>, applied: bool}
     */
    public static function run(bool $apply): array
    {
        $disk = Storage::disk(ImageStore::DISK);
        $files = [];
        $unused = [];
        $saved = 0;

        $places = self::PLACES;
        $places['site'] = [['settings:logo', 'value']];

        foreach ($places as $folder => $columns) {
            $used = [];
            foreach ($columns as [$table, $column]) {
                foreach (static::references($table, $column) as $path) {
                    $used[$path][] = [$table, $column];   // one picture may be used in more than one place
                }
            }

            foreach ($disk->files($folder) as $path) {
                // Only pictures; the copies the home gallery makes for itself are redone from the originals anyway.
                if (! preg_match('/\.(png|jpe?g|webp|gif)$/i', $path)) {
                    continue;
                }
                if (! isset($used[$path])) {
                    $unused[] = $path;

                    continue;
                }

                $binary = (string) $disk->get($path);
                $webp = ImageStore::lighten($binary);
                if ($webp === null) {
                    $files[] = ['path' => $path, 'before' => strlen($binary), 'after' => null, 'status' => 'kept'];

                    continue;
                }

                $row = ['path' => $path, 'before' => strlen($binary), 'after' => strlen($webp), 'status' => 'would change'];
                if ($apply) {
                    $new = $folder.'/'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(24)).'.webp';
                    $disk->put($new, $webp, 'public');
                    foreach ($used[$path] as [$table, $column]) {
                        static::repoint($table, $column, $path, $new);
                    }
                    // The original goes to a private place, not to the bin.
                    Storage::disk('local')->put('originals/'.$path, $binary);
                    $disk->delete($path);
                    $row['status'] = 'changed';
                    $row['new_path'] = $new;
                }
                $saved += $row['before'] - $row['after'];
                $files[] = $row;
            }
        }

        if ($apply) {
            Setting::forget();
        }

        return ['files' => $files, 'saved' => $saved, 'unused' => $unused, 'applied' => $apply];
    }

    /** @return list<string> the paths in use */
    private static function references(string $table, string $column): array
    {
        if (str_starts_with($table, 'settings:')) {
            $value = Setting::get(substr($table, 9));

            return is_string($value) && $value !== '' ? [$value] : [];
        }
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)->whereNotNull($column)->where($column, '!=', '')->pluck($column)->unique()->values()->all();
    }

    private static function repoint(string $table, string $column, string $old, string $new): void
    {
        if (str_starts_with($table, 'settings:')) {
            Setting::put(substr($table, 9), $new);

            return;
        }
        DB::table($table)->where($column, $old)->update([$column => $new]);
    }
}
