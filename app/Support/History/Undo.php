<?php

namespace App\Support\History;

use App\Models\Activity;

/**
 * Puts the old words of a record back, from a "Changed" line of the history. Only the fields a model lists as undoable
 * (its texts), and only while the record still exists. The undo is itself written to the history, so nothing is ever lost.
 */
class Undo
{
    /** The fields of this line that can be put back. @return array<string, string> field => name */
    public static function fields(Activity $a): array
    {
        if ($a->event !== 'updated' || ! class_exists($a->subject_type) || ! method_exists($a->subject_type, 'activityUndoFields')) {
            return [];
        }
        if ($a->subject() === null) {
            return [];
        }
        $allowed = $a->subject_type::activityUndoFields();
        $out = [];
        foreach ($a->changedFields() as $field) {
            if (in_array($field, $allowed, true)) {
                $out[$field] = Present::fieldName($field);
            }
        }

        return $out;
    }

    /** Has the field been changed again since this line? (Putting the old words back would also remove that later change.) */
    public static function changedSince(Activity $a, string $field): bool
    {
        $subject = $a->subject();
        if (! $subject) {
            return false;
        }

        return (string) $subject->getRawOriginal($field) !== (string) (((array) $a->new_values)[$field] ?? '');
    }

    /**
     * @param  list<string>  $fields
     * @return array{restored: list<string>, skipped: list<string>}
     */
    public static function apply(Activity $a, array $fields): array
    {
        $allowed = static::fields($a);
        $subject = $a->subject();
        $restored = [];
        $skipped = [];
        if (! $subject) {
            return ['restored' => [], 'skipped' => $fields];
        }

        foreach ($fields as $field) {
            if (! isset($allowed[$field])) {
                $skipped[] = $field;

                continue;
            }
            $old = ((array) $a->old_values)[$field] ?? null;
            if (in_array($field, method_exists($subject, 'getTranslatableAttributes') ? $subject->getTranslatableAttributes() : [], true)) {
                $subject->setTranslations($field, is_string($old) ? (array) json_decode($old, true) : []);
            } else {
                $decoded = is_string($old) ? json_decode($old, true) : null;
                $subject->{$field} = is_array($decoded) ? $decoded : $old;
            }
            $restored[] = $field;
        }

        if ($restored !== []) {
            $subject->save();
            // Note on the new line which line it undid.
            Activity::query()->where('subject_type', $a->subject_type)->where('subject_id', $a->subject_id)->where('event', 'updated')->latest('id')->first()
                ?->update(['custom_data' => ['undo_of' => $a->id, 'fields' => $restored]]);
        }

        return ['restored' => $restored, 'skipped' => $skipped];
    }
}
