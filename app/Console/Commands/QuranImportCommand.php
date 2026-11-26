<?php

namespace App\Console\Commands;

use App\Models\QuranTranslation;
use App\Models\QuranTranslationVerse;
use App\Models\QuranVerse;
use App\Models\Setting;
use App\Support\Quran;
use App\Support\QuranRefs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Puts a Quran text file in the Tanzil format ("surah|ayah|text" on each line, "#" lines are the notice) into the database.
 * The text is stored exactly as it is in the file. The whole file is checked first (114 surahs, 6,236 verses, the right number in each surah),
 * and nothing is written when it does not fit.
 *
 *   php artisan quran:import --arabic=path/quran-uthmani.txt
 *   php artisan quran:import --translation=path/en.sahih.txt --code=sahih --name="Saheeh International" [--translator=...] [--source=...] [--default]
 */
class QuranImportCommand extends Command
{
    protected $signature = 'quran:import {--arabic= : the Arabic text file} {--translation= : a translation file} {--code= : short code of the translation} {--name= : its name} {--translator= : who made it} {--source= : where it came from} {--default : use it when no translation is chosen}';

    protected $description = 'Import the Arabic Quran text or a translation (Tanzil format) into the database';

    public function handle(): int
    {
        if (! $this->option('arabic') && ! $this->option('translation')) {
            $this->error('Use --arabic=file or --translation=file.');

            return self::FAILURE;
        }
        if ($this->option('arabic')) {
            $r = $this->read((string) $this->option('arabic'));
            if ($r === null) {
                return self::FAILURE;
            }
            DB::transaction(function () use ($r) {
                QuranVerse::query()->delete();
                foreach (array_chunk($r['verses'], 500, true) as $chunk) {
                    $rows = [];
                    foreach ($chunk as [$surah, $ayah, $text]) {
                        $rows[] = ['surah' => $surah, 'ayah' => $ayah, 'arabic' => $text, 'created_at' => now(), 'updated_at' => now()];
                    }
                    QuranVerse::query()->insert($rows);
                }
                Setting::put('quran_arabic_notice', $r['notice']);
            });
            Quran::forget();
            $this->info('Arabic text imported: '.QuranVerse::count().' verses.');
        }
        if ($this->option('translation')) {
            $code = trim((string) $this->option('code'));
            $name = trim((string) $this->option('name'));
            if ($code === '' || $name === '') {
                $this->error('A translation needs --code and --name.');

                return self::FAILURE;
            }
            $r = $this->read((string) $this->option('translation'));
            if ($r === null) {
                return self::FAILURE;
            }
            DB::transaction(function () use ($r, $code, $name) {
                $t = QuranTranslation::query()->updateOrCreate(['code' => $code], [
                    'name' => $name, 'translator' => $this->option('translator') ?: null, 'source' => $this->option('source') ?: null, 'notice' => $r['notice'],
                ]);
                if ($this->option('default') || QuranTranslation::query()->count() === 1) {
                    QuranTranslation::query()->where('id', '!=', $t->id)->update(['is_default' => false]);
                    $t->update(['is_default' => true]);
                }
                QuranTranslationVerse::query()->where('translation_id', $t->id)->delete();
                foreach (array_chunk($r['verses'], 500, true) as $chunk) {
                    $rows = [];
                    foreach ($chunk as [$surah, $ayah, $text]) {
                        $rows[] = ['translation_id' => $t->id, 'surah' => $surah, 'ayah' => $ayah, 'text' => $text, 'created_at' => now(), 'updated_at' => now()];
                    }
                    QuranTranslationVerse::query()->insert($rows);
                }
            });
            $this->info('Translation "'.$name.'" imported: '.count($r['verses']).' verses.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{verses: list<array{0: int, 1: int, 2: string}>, notice: string}|null
     */
    private function read(string $path): ?array
    {
        if (! is_file($path)) {
            $this->error('There is no file at '.$path);

            return null;
        }
        $verses = [];
        $notice = [];
        $counts = [];
        foreach (preg_split('/\R/u', (string) file_get_contents($path)) as $line) {
            if (str_starts_with($line, '#')) {
                $notice[] = rtrim(preg_replace('/^#\s{0,3}/', '', $line));

                continue;
            }
            if (trim($line) === '') {
                continue;
            }
            $p = explode('|', $line, 3);
            if (count($p) !== 3 || ! ctype_digit($p[0]) || ! ctype_digit($p[1]) || trim($p[2]) === '') {
                $this->error('A line is not "surah|ayah|text": '.mb_substr($line, 0, 60));

                return null;
            }
            $verses[] = [(int) $p[0], (int) $p[1], $p[2]];
            $counts[(int) $p[0]] = ($counts[(int) $p[0]] ?? 0) + 1;
        }
        $expected = QuranRefs::counts();
        $same = count($counts) === count($expected);
        foreach ($expected as $i => $n) {
            $same = $same && (($counts[$i + 1] ?? 0) === $n);
        }
        if (! $same || count($verses) !== array_sum($expected)) {
            $this->error('The file does not have the 114 surahs and '.number_format(array_sum($expected)).' verses (it has '.count($verses).' verses in '.count($counts).' surahs). Nothing was imported.');

            return null;
        }

        return ['verses' => $verses, 'notice' => trim(implode("\n", $notice))];
    }
}
