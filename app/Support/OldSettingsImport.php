<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Brings the slide viewer choices of the OLD site (the Slide viewer, Visual Stories and Listen settings you had changed) into this site.
 * Only the controls this site has are brought, and only the ones whose value differs from what this site uses now.
 */
class OldSettingsImport
{
    public static function available(): bool
    {
        try {
            return OldSiteImport::available() && \Illuminate\Support\Facades\Schema::connection('old_site')->hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, array{old: string, now: string, label: string}> the controls that would change
     */
    public function check(): array
    {
        $known = ViewerSettings::definitions();
        $rows = DB::connection('old_site')->table('settings')->whereIn('key', array_keys($known))->pluck('value', 'key');

        $changes = [];
        foreach ($rows as $key => $old) {
            [$label, $type, $usual] = $known[$key];
            $old = (string) $old;
            $now = (string) Setting::get($key, $usual);
            if ($old !== $now && ($type !== 'select' || array_key_exists($old, $known[$key][3] ?? []))) {
                $changes[$key] = ['old' => $old, 'now' => $now, 'label' => $label];
            }
        }

        return $changes;
    }

    /** Make the choices of the old site the choices of this one. Returns how many were changed. */
    public function bring(): int
    {
        $known = ViewerSettings::definitions();
        $changes = $this->check();
        foreach ($changes as $key => $change) {
            if ($change['old'] === (string) $known[$key][2]) {
                Setting::query()->where('key', $key)->delete();   // back to the usual value
            } else {
                Setting::put($key, $change['old']);
            }
        }
        Setting::forget();

        return count($changes);
    }
}
