<?php

namespace App\Support;

use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Brings the contributors of the OLD site (name, role, short description, photo, public page) into this one.
 * A person who is here already (same address) is left alone. The old site is only read.
 * The people who asked for the old newsletter are NOT brought: they must confirm again with a code.
 */
class OldPeopleImport
{
    public static function available(): bool
    {
        try {
            return OldSiteImport::available() && Schema::connection('old_site')->hasTable('people');
        } catch (\Throwable) {
            return false;
        }
    }

    public function check(): array
    {
        return $this->run(true);
    }

    public function bring(): array
    {
        return $this->run(false);
    }

    private function run(bool $dry): array
    {
        $report = ['new' => 0, 'existing' => 0, 'photos' => 0, 'names' => []];

        $work = function () use ($dry, &$report) {
            foreach (DB::connection('old_site')->table('people')->whereNull('deleted_at')->orderBy('id')->get() as $old) {
                $slug = $old->slug ?: Person::freeSlug($old->name);
                if (Person::withTrashed()->where('slug', $slug)->exists()) {
                    $report['existing']++;

                    continue;
                }
                $report['new']++;
                $report['names'][] = $old->name;
                $photo = $this->photo($old->photo ?? null, $dry, $report);
                if (! $dry) {
                    Person::create([
                        'name' => $old->name, 'slug' => $slug, 'role_title' => $old->role_title, 'tradition' => $old->tradition,
                        'bio' => $old->bio, 'qualification_note' => $old->qualification_note, 'photo' => $photo, 'photo_alt' => $old->photo_alt,
                        'link' => Social::safeUrl($old->link), 'show_page' => (bool) $old->show_page,
                        'page_body' => filled($old->page_body) ? Html::clean((string) $old->page_body) : null,
                    ]);
                }
            }
        };

        $dry ? $work() : DB::transaction($work);

        return ['total' => $report, 'dry' => $dry];
    }

    private function photo(?string $path, bool $dry, array &$report): ?string
    {
        if (blank($path) || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return null;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            $source = rtrim((string) config('infofluxes.old_site_path'), '/\\').'/storage/app/public/'.$path;
            if (! is_file($source)) {
                return null;
            }
            $report['photos']++;
            if (! $dry) {
                $disk->put($path, (string) file_get_contents($source), 'public');
            }
        }

        return $path;
    }
}
