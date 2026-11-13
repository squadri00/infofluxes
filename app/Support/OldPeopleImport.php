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
        $report = ['new' => 0, 'existing' => 0, 'photos' => 0, 'credits' => 0, 'names' => []];

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

            // The credit: an old topic that named a contributor gives that credit to the article of the same title here (when it has none yet).
            $old = DB::connection('old_site');
            $oldPeople = $old->table('people')->pluck('slug', 'id');
            $locale = config('infofluxes.default_locale');
            $mine = \App\Models\Article::query()->whereNull('contributor_id')->get(['id', 'title'])
                ->groupBy(fn ($a) => mb_strtolower(trim((string) $a->getTranslation('title', $locale, true))));
            foreach ($old->table('topics')->whereNotNull('contributor_person_id')->get(['title', 'contributor_person_id']) as $topic) {
                $person = Person::withTrashed()->where('slug', $oldPeople[$topic->contributor_person_id] ?? '')->first();
                $articles = $mine->get(mb_strtolower(trim((string) $topic->title)));
                if (! $person || ! $articles) {
                    continue;
                }
                foreach ($articles as $article) {
                    $report['credits']++;
                    if (! $dry) {
                        \App\Models\Article::whereKey($article->id)->update(['contributor_id' => $person->id]);
                    }
                }
                $mine->forget(mb_strtolower(trim((string) $topic->title)));
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
