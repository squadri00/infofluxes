<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Searching inside the text columns (the titles and details are stored as JSON). On MySQL a plain LIKE on a JSON column tells "History" from
 * "history", so a person who types a small letter finds nothing. This puts both sides in small letters first.
 */
class TextSearch
{
    /** @param list<string> $columns */
    public static function match(Builder $query, array $columns, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($term)).'%';
        $grammar = $query->getQuery()->getGrammar();

        return $query->where(function ($w) use ($columns, $like, $grammar) {
            foreach ($columns as $column) {
                $w->orWhereRaw('LOWER(CAST('.$grammar->wrap($column).' AS CHAR)) LIKE ?', [$like]);
            }
        });
    }
}
