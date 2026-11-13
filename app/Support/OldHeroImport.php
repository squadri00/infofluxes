<?php

namespace App\Support;

use App\Models\HeroMessage;
use App\Models\HeroSlide;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Brings the home page banner of the OLD site into this one: the sets of words, the slider pictures (with their files),
 * and the banner settings (on or off, seconds, the home headline, introduction and message).
 * Nothing that is here already is changed or made twice. The old site is only read.
 */
class OldHeroImport
{
    private const SETTINGS = ['hero_on' => '1', 'hero_slide_seconds' => '6', 'home_headline' => null, 'home_intro' => null, 'home_message' => null];

    /** @var array<string, int> */
    private array $report = [];

    public static function available(): bool
    {
        try {
            $schema = \Illuminate\Support\Facades\Schema::connection('old_site');

            return OldSiteImport::available() && $schema->hasTable('hero_messages') && $schema->hasTable('hero_slides');
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
        $this->report = ['messages_new' => 0, 'messages_existing' => 0, 'slides_new' => 0, 'slides_existing' => 0, 'images_copied' => 0, 'images_missing' => 0, 'settings_changed' => 0];
        $locale = config('infofluxes.default_locale');
        $old = DB::connection('old_site');

        $work = function () use ($dry, $locale, $old) {
            $have = HeroMessage::all()->map(fn ($m) => mb_strtolower(trim((string) $m->getTranslation('title', $locale))))->all();
            foreach ($old->table('hero_messages')->orderBy('sort_order')->orderBy('id')->get() as $m) {
                if (in_array(mb_strtolower(trim($m->title)), $have, true)) {
                    $this->report['messages_existing']++;

                    continue;
                }
                $this->report['messages_new']++;
                $have[] = mb_strtolower(trim($m->title));
                if (! $dry) {
                    HeroMessage::create([
                        'title' => [$locale => $m->title],
                        'subtitle' => filled($m->subtitle) ? [$locale => $m->subtitle] : null,
                        'description' => filled($m->description) ? [$locale => $m->description] : null,
                        'is_active' => (bool) $m->is_active,
                        'sort_order' => (int) $m->sort_order,
                    ]);
                }
            }

            $haveImages = HeroSlide::pluck('image')->all();
            foreach ($old->table('hero_slides')->orderBy('sort_order')->orderBy('id')->get() as $s) {
                if (in_array($s->image, $haveImages, true)) {
                    $this->report['slides_existing']++;

                    continue;
                }
                $image = $this->file($s->image, $dry);
                if ($image === null) {
                    continue;     // a slide without its picture is not made
                }
                $this->report['slides_new']++;
                $haveImages[] = $image;
                if (! $dry) {
                    HeroSlide::create([
                        'image' => $image,
                        'image_alt' => filled($s->image_alt) ? [$locale => $s->image_alt] : null,
                        'heading' => filled($s->heading) ? [$locale => $s->heading] : null,
                        'text' => filled($s->text) ? [$locale => $s->text] : null,
                        'button_label' => filled($s->button_label) ? [$locale => $s->button_label] : null,
                        'button_url' => Hero::safeUrl($s->button_url),
                        'new_tab' => (bool) $s->new_tab,
                        'is_active' => (bool) $s->is_active,
                        'sort_order' => (int) $s->sort_order,
                    ]);
                }
            }

            $rows = $old->table('settings')->whereIn('key', array_keys(self::SETTINGS))->pluck('value', 'key');
            foreach (self::SETTINGS as $key => $usual) {
                if (! isset($rows[$key])) {
                    continue;
                }
                $value = (string) $rows[$key];
                $translated = in_array($key, Setting::TRANSLATED, true);
                if ($translated) {
                    if (trim($value) === '' || filled(Setting::all_()[$key] ?? null)) {
                        continue;     // nothing to bring, or this site already has its own words
                    }
                } elseif ($value === (string) Setting::get($key, $usual)) {
                    continue;
                }
                $this->report['settings_changed']++;
                if (! $dry) {
                    if ($translated) {
                        Setting::put($key, [$locale => $value]);
                    } elseif ($value === (string) $usual) {
                        Setting::query()->where('key', $key)->delete();
                    } else {
                        Setting::put($key, $value);
                    }
                }
            }
        };

        $dry ? $work() : DB::transaction($work);
        Setting::forget();

        return ['total' => $this->report, 'dry' => $dry];
    }

    /** Copy a picture of the old site here (same name). Returns its path, or null when the old file is not there. */
    private function file(?string $path, bool $dry): ?string
    {
        if (blank($path) || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            $this->report['images_missing']++;

            return null;
        }
        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            return $path;
        }
        $source = rtrim((string) config('infofluxes.old_site_path'), '/\\').'/storage/app/public/'.$path;
        if (! is_file($source)) {
            $this->report['images_missing']++;

            return null;
        }
        $this->report['images_copied']++;
        if (! $dry) {
            $disk->put($path, (string) file_get_contents($source), 'public');
        }

        return $path;
    }
}
