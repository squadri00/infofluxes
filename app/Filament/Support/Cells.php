<?php

namespace App\Filament\Support;

/** Helpers for the cells of admin lists. */
class Cells
{
    /**
     * A long text in a cell of limited width that scrolls sideways by itself (see the style in AdminPanelProvider),
     * so every column of the list stays in view. The full text is also in the tooltip.
     */
    public static function scroll(?string $text, string $class = ''): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        return '<div class="if-scroll '.e($class).'" title="'.e($text).'" tabindex="0">'.e($text).'</div>';
    }
}