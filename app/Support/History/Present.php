<?php

namespace App\Support\History;

use App\Models\Activity;
use App\Models\Article;
use App\Models\Category;
use App\Models\Person;
use App\Models\Story;
use App\Models\User;

/** Turns the stored values of a history line into words a person can read. */
class Present
{
    public static function fieldName(string $field): string
    {
        return ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $field)));
    }

    /**
     * One value as readable text: translated fields per language, ids as names, JSON tidied.
     *
     * @return array<string, string>  label => text (one entry for a plain value, one per language for a translated one)
     */
    public static function value(string $field, mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return ['' => ''];
        }
        $raw = (string) $raw;

        if (in_array($field, ['category_id', 'author_id', 'contributor_id'], true) && ctype_digit($raw)) {
            return ['' => static::named($field, (int) $raw)];
        }
        if ($field === 'status') {
            return ['' => Article::STATUSES[$raw] ?? ucfirst(str_replace('_', ' ', $raw))];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            // Translations ({"en": "..."}): one text for each language. Other lists and objects: tidy JSON.
            if ($decoded !== [] && ! array_is_list($decoded) && count(array_filter(array_keys($decoded), fn ($k) => preg_match('/^[a-z]{2,3}(-[A-Za-z]+)?$/', (string) $k))) === count($decoded) && collect($decoded)->every(fn ($v) => is_string($v) || $v === null)) {
                return collect($decoded)->mapWithKeys(fn ($v, $k) => [strtoupper((string) $k) => TextDiff::plain((string) $v)])->all();
            }

            return ['' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }

        return ['' => TextDiff::plain($raw)];
    }

    private static function named(string $field, int $id): string
    {
        return match ($field) {
            'category_id' => ($c = Category::withTrashed()->find($id)) ? $c->code.' '.$c->mainName() : 'Category #'.$id,
            'author_id' => User::find($id)?->name ?? 'Person #'.$id,
            'contributor_id' => Person::withTrashed()->find($id)?->name ?? 'Contributor #'.$id,
            default => (string) $id,
        };
    }

    /** The comparison of one changed field, for the details window. @return list<array{label: string, old: string, new: string, diff: ?string}> */
    public static function comparison(Activity $a, string $field): array
    {
        $old = static::value($field, ((array) $a->old_values)[$field] ?? null);
        $new = static::value($field, ((array) $a->new_values)[$field] ?? null);
        $out = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $label) {
            $o = $old[$label] ?? '';
            $n = $new[$label] ?? '';
            if ($o === $n) {
                continue;
            }
            $out[] = ['label' => $label, 'old' => $o, 'new' => $n, 'diff' => TextDiff::inline($o, $n)];
        }

        return $out;
    }

    /** "Status (Written → Published), Long detail" for the list. */
    public static function summary(Activity $a): string
    {
        if ($a->event !== 'updated') {
            return '';
        }
        $parts = [];
        foreach ($a->changedFields() as $field) {
            $name = static::fieldName($field);
            if (in_array($field, ['status', 'category_id'], true)) {
                $old = array_values(static::value($field, ((array) $a->old_values)[$field] ?? null))[0] ?? '';
                $new = array_values(static::value($field, ((array) $a->new_values)[$field] ?? null))[0] ?? '';
                $name .= ' ('.($old === '' ? 'empty' : $old).' → '.($new === '' ? 'empty' : $new).')';
            }
            $parts[] = $name;
        }

        return implode(', ', $parts);
    }
}
