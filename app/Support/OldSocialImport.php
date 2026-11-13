<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\SocialLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the social accounts of the OLD site (the platform, the address, shown or not, the order) and the footer text.
 * The old addresses were placeholders (the home page of each network): they are brought marked as such, so you replace them with your own.
 * A network that is here already is left alone. The old site is only read.
 */
class OldSocialImport
{
    public static function available(): bool
    {
        try {
            return OldSiteImport::available() && Schema::connection('old_site')->hasTable('social_links');
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
        $report = ['new' => 0, 'existing' => 0, 'footer_text' => 0];
        $locale = config('infofluxes.default_locale');
        $old = DB::connection('old_site');

        $work = function () use ($dry, $locale, $old, &$report) {
            $have = SocialLink::query()->pluck('platform')->all();
            foreach ($old->table('social_links')->orderBy('sort_order')->orderBy('id')->get() as $row) {
                if (in_array($row->platform, $have, true) || Social::safeUrl($row->url) === null) {
                    $report['existing']++;

                    continue;
                }
                $report['new']++;
                $have[] = $row->platform;
                if (! $dry) {
                    SocialLink::create([
                        'platform' => $row->platform, 'label' => $row->label, 'url' => $row->url,
                        'is_active' => (bool) $row->is_active, 'sort_order' => (int) $row->sort_order,
                        'custom_data' => ['placeholder' => str_contains((string) $row->custom_data, 'demo')],
                    ]);
                }
            }

            $text = $old->table('settings')->where('key', 'footer_about')->value('value');
            if (filled($text) && blank(Setting::all_()['footer_about'] ?? null)) {
                $report['footer_text'] = 1;
                if (! $dry) {
                    Setting::put('footer_about', [$locale => (string) $text]);
                }
            }
        };

        $dry ? $work() : DB::transaction($work);
        Setting::forget();

        return ['total' => $report, 'dry' => $dry];
    }
}
