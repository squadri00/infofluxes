<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Quran;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Verse helper: type one or more references (30:30, Surah Ar-Rum 30:30, 52:35-36) and get the verified Arabic, the English of a translation, and a ready
 * [QURAN] block to copy into an article or into the text you give to the AI import.
 */
class VerseHelper extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Verse helper';

    protected static ?string $title = 'Verse helper';

    protected static ?string $slug = 'verse-helper';

    protected string $view = 'filament.pages.verse-helper';

    public string $refs = '';

    public string $translation = '';

    public static function canAccess(): bool
    {
        return AiImport::canAccess();
    }

    /** @return array{items: list<array<string, mixed>>, blocks: string, ready: bool} */
    public function result(): array
    {
        $t = Quran::translation($this->translation ?: null);
        $items = [];
        $blocks = [];
        foreach (Quran::parse($this->refs) as $ref) {
            $name = $ref['valid'] ? 'Surah '.Quran::surahName($ref['surah']).' '.$ref['ref'] : $ref['ref'];
            if (! $ref['valid']) {
                $items[] = ['ref' => $name, 'problem' => ucfirst((string) $ref['problem']).'.'];

                continue;
            }
            $arabic = Quran::arabic($ref['surah'], $ref['from'], $ref['to']);
            $english = Quran::english($ref['surah'], $ref['from'], $ref['to'], $t);
            $items[] = ['ref' => $name, 'arabic' => $arabic, 'english' => $english, 'problem' => $arabic === null ? 'The verse is not in the Quran text of this site yet.' : null];
            if ($arabic !== null && ($block = Quran::block($ref, $t))) {
                $blocks[] = $block;
            }
        }

        return ['items' => $items, 'blocks' => implode("\n\n", $blocks), 'ready' => Quran::available()];
    }

    /** @return array<string, string> code => name */
    public function translationChoices(): array
    {
        return Quran::translations()->pluck('name', 'code')->all();
    }

    /** The notice that goes with the Arabic text (its license asks for it). */
    public function arabicNotice(): string
    {
        return (string) Setting::get('quran_arabic_notice');
    }
}
