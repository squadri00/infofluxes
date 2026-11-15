<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Writes the history of a record: when it is made, changed (only the fields that changed, old and new), moved to the Trash,
 * restored, or removed for good. A change that touches only fields nobody reads (the update time, a password) writes nothing.
 * A model may list more fields to leave out in $activityIgnore, and the fields that may be put back in $activityUndo
 * (without it, the translated fields can be put back).
 */
trait LogsActivity
{
    /** Fields that are never written to the history. */
    private static array $alwaysIgnore = ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'password', 'sort_order', 'tree_path', 'depth'];

    public static function bootLogsActivity(): void
    {
        static::created(fn ($m) => $m->logActivity('created'));
        static::updated(function ($m) {
            $changes = $m->activityChanges();
            if ($changes !== null) {
                $m->logActivity('updated', $changes['old'], $changes['new']);
            }
        });
        static::deleted(function ($m) {
            // A soft delete is "moved to the Trash". A hard delete is written by forceDeleted (or here when the model has no Trash).
            if (! static::usesTrash()) {
                $m->logActivity('purged');
            } elseif (! $m->isForceDeleting()) {
                $m->logActivity('deleted');
            }
        });
        if (static::usesTrash()) {
            static::restored(fn ($m) => $m->logActivity('restored'));
            static::forceDeleted(fn ($m) => $m->logActivity('purged'));
        }
    }

    protected static function usesTrash(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class), true);
    }

    /** What the record is called in the history. */
    public function activityLabel(): string
    {
        $label = method_exists($this, 'mainTitle') ? $this->mainTitle() : (method_exists($this, 'mainName') ? $this->mainName() : (string) ($this->name ?? $this->title ?? ''));
        $code = $this->code ?? null;

        return trim(($code ? $code.' ' : '').$label) ?: class_basename($this).' '.$this->getKey();
    }

    /** The fields that may be put back from the history. @return list<string> */
    public static function activityUndoFields(): array
    {
        $own = property_exists(static::class, 'activityUndo') ? (array) (new static)->activityUndo : [];
        $translated = method_exists(static::class, 'getTranslatableAttributes') ? (new static)->getTranslatableAttributes() : [];

        return array_values(array_unique(array_merge($translated, $own)));
    }

    /** @return array{old: array<string, mixed>, new: array<string, mixed>}|null */
    private function activityChanges(): ?array
    {
        $ignore = array_merge(self::$alwaysIgnore, property_exists($this, 'activityIgnore') ? (array) $this->activityIgnore : []);
        $old = [];
        $new = [];
        foreach ($this->getChanges() as $field => $value) {
            if (in_array($field, $ignore, true)) {
                continue;
            }
            $old[$field] = $this->getOriginal($field);
            $new[$field] = $value;
        }
        // The raw stored values are kept. A value that was an array is already its JSON text here.
        $old = array_map(fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v, $old);
        $new = array_map(fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v, $new);

        return $new === [] ? null : ['old' => $old, 'new' => $new];
    }

    private function logActivity(string $event, array $old = [], array $new = []): void
    {
        if (Activity::isQuiet() || ! Activity::tableExists()) {
            return;   // (the table does not exist yet while the first migrations run)
        }
        try {
            Activity::create([
                'user_id' => auth()->id(),
                'event' => $event,
                'subject_type' => static::class,
                'subject_id' => $this->getKey(),
                'subject_label' => mb_substr($this->activityLabel(), 0, 250),
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'source' => app()->runningInConsole() && ! app()->runningUnitTests() ? 'command' : 'web',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // A problem with the history must never stop someone from saving their work.
            report($e);
        }
    }
}
