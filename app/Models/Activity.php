<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the history: who did what to which record, and what changed. */
class Activity extends Model
{
    protected $table = 'activity_log';

    public $timestamps = false;

    protected $guarded = [];

    public const EVENTS = [
        'created' => 'Created',
        'updated' => 'Changed',
        'deleted' => 'Moved to Trash',
        'restored' => 'Restored from Trash',
        'purged' => 'Removed for good',
    ];

    /** While this is above zero nothing is written to the history (big imports use it, so they do not flood it). */
    private static int $quiet = 0;

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'custom_data' => 'array', 'created_at' => 'datetime'];
    }

    /** Runs something without writing to the history. */
    public static function quiet(callable $work): mixed
    {
        self::$quiet++;
        try {
            return $work();
        } finally {
            self::$quiet--;
        }
    }

    public static function isQuiet(): bool
    {
        return self::$quiet > 0;
    }

    /** Is the table there? (Not yet, while the first migrations run.) */
    public static function tableExists(): bool
    {
        static $known = null;
        if ($known === true) {
            return true;
        }
        $known = \Illuminate\Support\Facades\Schema::hasTable('activity_log');

        return $known;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Article", "Visual story", ... for people to read. */
    public function subjectName(): string
    {
        return str(class_basename($this->subject_type))->snake(' ')->title()->toString();
    }

    public function who(): string
    {
        return $this->user?->name ?? ($this->user_id ? 'Someone who was deleted' : 'The system');
    }

    /** The record itself, or null when it is gone. @return \Illuminate\Database\Eloquent\Model|null */
    public function subject(): ?Model
    {
        $class = $this->subject_type;
        if (! class_exists($class)) {
            return null;
        }
        $query = $class::query();
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->withTrashed();
        }

        return $query->find($this->subject_id);
    }

    /** The names of the fields that changed. @return list<string> */
    public function changedFields(): array
    {
        return array_keys((array) $this->new_values);
    }
}
