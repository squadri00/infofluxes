<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Languages;
use Illuminate\Database\Eloquent\Model;

/** A language of the site: its name, its flag, whether it is written right to left, and whether visitors can use it yet. */
class Language extends Model
{
    use LogsActivity;

    public const STATUSES = [
        'active' => 'On: visitors can choose it',
        'coming_soon' => 'Coming soon: shown, but not yet to choose',
        'off' => 'Off: not shown',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_rtl' => 'boolean', 'is_default' => 'boolean', 'custom_data' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $l) {
            $l->code = mb_strtolower(trim((string) $l->code));
            if ($l->is_default) {
                $l->status = 'active';   // the main language is always on
            }
        });
        static::saved(fn () => Languages::forget());
        static::deleted(fn () => Languages::forget());
        // The main language cannot be removed.
        static::deleting(function (self $l) {
            if ($l->is_default) {
                return false;
            }
        });
    }

    public function activityLabel(): string
    {
        return $this->name.' ('.$this->code.')';
    }

    public function flagUrl(): string
    {
        return asset('flags/'.(file_exists(public_path('flags/'.$this->flag.'.svg')) ? $this->flag : 'un').'.svg');
    }

    /** Are the words of the site (buttons, menus) translated into this language? Without them English words are shown. */
    public function hasSiteWords(): bool
    {
        return is_dir(lang_path($this->code)) && $this->code !== 'en' || $this->code === 'en';
    }

    /** Puts English in, once: only when there are no languages yet. */
    public static function installDefaults(): int
    {
        if (static::query()->exists()) {
            return 0;
        }
        $main = config('infofluxes.default_locale', 'en');
        [$name, $native, $flag, $rtl] = (require resource_path('data/languages.php'))['catalog'][$main] ?? [config('infofluxes.locales')[$main] ?? 'English', config('infofluxes.locales')[$main] ?? 'English', 'gb', false];
        static::create(['code' => $main, 'name' => $name, 'native_name' => $native, 'flag' => $flag, 'is_rtl' => $rtl, 'status' => 'active', 'is_default' => true, 'sort_order' => 0]);

        return 1;
    }
}
