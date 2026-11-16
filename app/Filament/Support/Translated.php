<?php

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Form boxes that are ready for more than one language.
 * With only English switched on you see one box. Switch on another language in config/infofluxes.php
 * and a box for it appears under each of these, with the language named in the label.
 */
class Translated
{
    /** @return array<int, TextInput> */
    public static function text(string $field, string $label, bool|\Closure $required = false, int $max = 255, ?string $help = null): array
    {
        return static::boxes($field, $label, $required, fn (string $name, string $text, $must) => TextInput::make($name)->label($text)->required($must)->maxLength($max)->helperText($help));
    }

    /** @return array<int, Textarea> */
    public static function textarea(string $field, string $label, bool $required = false, int $rows = 4, ?string $help = null): array
    {
        return static::boxes($field, $label, $required, fn (string $name, string $text, $must) => Textarea::make($name)->label($text)->required($must)->rows($rows)->helperText($help)->columnSpanFull());
    }

    /** @return array<int, RichEditor> a text editor (bold, lists, links, headings) for each language */
    public static function rich(string $field, string $label, bool $required = false, ?string $help = null): array
    {
        return static::boxes($field, $label, $required, fn (string $name, string $text, $must) => Editor::make($name)->label($text)->required($must)->helperText($help)->columnSpanFull());
    }

    private static function boxes(string $field, string $label, bool|\Closure $required, \Closure $make): array
    {
        $locales = \App\Support\Languages::forEditors();
        $main = config('infofluxes.default_locale');
        $out = [];
        foreach ($locales as $code => $name) {
            $text = count($locales) > 1 ? "{$label} ({$name})" : $label;
            // Only the main language is required; the others may stay empty (the main text is shown instead).
            $isMain = $code === $main;
            $out[] = $make("{$field}.{$code}", $text, $required instanceof \Closure ? fn (\Filament\Schemas\Components\Utilities\Get $get) => $isMain && (bool) $required($get) : ($required && $isMain));
        }

        return $out;
    }
}
