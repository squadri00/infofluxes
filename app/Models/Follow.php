<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing a member follows: a category (with everything below it), a contributor, or a word. */
class Follow extends Model
{
    public const KINDS = ['category', 'person', 'keyword'];

    /** A member may follow this many things. */
    public const MAX = 100;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['custom_data' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The category or the person that is followed (null for a word, or when it is gone). */
    public function target(): ?Model
    {
        return match ($this->kind) {
            'category' => Category::query()->find($this->target_id),
            'person' => Person::query()->find($this->target_id),
            default => null,
        };
    }

    /** What the member sees in the list. */
    public function label(): string
    {
        return match ($this->kind) {
            'category' => ($c = $this->target()) ? $c->mainName() : '(removed)',
            'person' => ($p = $this->target()) ? (string) $p->name : '(removed)',
            default => (string) $this->keyword,
        };
    }
}
