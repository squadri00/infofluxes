<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the tests of "Challenge yourself" from the OLD site: the Arabic passages, the scripture passages, the fact statements,
 * the quotes, the 114 chapters, the saved scores and the test settings (on or off, how many questions).
 * Nothing that is here already is changed or made twice. The old site is only read.
 */
class OldChallengeImport
{
    /** table => the columns that say "this row is the same one" */
    private const TABLES = [
        'quiz_passages' => ['kind', 'reference', 'arabic'],
        'scripture_passages' => ['kind', 'reference', 'text'],
        'fact_statements' => ['statement'],
        'quote_passages' => ['quote'],
        'quran_chapters' => ['number'],
    ];

    /** These old columns are not brought (they point at old people). */
    private const SKIP = ['id', 'created_by', 'updated_by', 'deleted_at'];

    private const SETTING_PREFIXES = ['quiz_', 'scripture_', 'facts_', 'who_', 'order_'];

    /** @var array<string, int> */
    private array $report = [];

    public static function available(): bool
    {
        try {
            $schema = Schema::connection('old_site');

            return OldSiteImport::available() && $schema->hasTable('quiz_passages') && $schema->hasTable('quran_chapters');
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
        $this->report = ['settings_changed' => 0, 'attempts_new' => 0];
        $old = DB::connection('old_site');

        $work = function () use ($dry, $old) {
            foreach (self::TABLES as $table => $keys) {
                $this->report[$table.'_new'] = 0;
                $this->report[$table.'_existing'] = 0;
                if (! $old->getSchemaBuilder()->hasTable($table)) {
                    continue;
                }
                $columns = Schema::getColumnListing($table);
                $have = DB::table($table)->get($keys)->map(fn ($r) => $this->fingerprint((array) $r, $keys))->all();

                foreach ($old->table($table)->orderBy('id')->get() as $row) {
                    $row = (array) $row;
                    $print = $this->fingerprint($row, $keys);
                    if (in_array($print, $have, true)) {
                        $this->report[$table.'_existing']++;

                        continue;
                    }
                    $have[] = $print;
                    $this->report[$table.'_new']++;
                    if (! $dry) {
                        $values = collect($row)->except(self::SKIP)->only($columns)->all();
                        DB::table($table)->insert($values);
                    }
                }
            }

            // The saved scores are only brought once, into an empty table.
            if ($old->getSchemaBuilder()->hasTable('quiz_attempts') && DB::table('quiz_attempts')->count() === 0) {
                foreach ($old->table('quiz_attempts')->orderBy('id')->get() as $row) {
                    $this->report['attempts_new']++;
                    if (! $dry) {
                        DB::table('quiz_attempts')->insert(collect((array) $row)->except(self::SKIP)->all());
                    }
                }
            }

            $rows = $old->table('settings')->where(function ($q) {
                foreach (self::SETTING_PREFIXES as $prefix) {
                    $q->orWhere('key', 'like', $prefix.'%');
                }
            })->pluck('value', 'key');

            foreach ($rows as $key => $value) {
                if ((string) $value === (string) Setting::get($key, null) || ($value === null)) {
                    continue;
                }
                $this->report['settings_changed']++;
                if (! $dry) {
                    Setting::put($key, (string) $value);
                }
            }
        };

        $dry ? $work() : DB::transaction($work);
        Setting::forget();

        return ['total' => $this->report, 'dry' => $dry];
    }

    private function fingerprint(array $row, array $keys): string
    {
        return mb_strtolower(implode('|', array_map(fn ($k) => trim((string) ($row[$k] ?? '')), $keys)));
    }
}
