<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One run of the Story maker: what was asked, the prompt, the pasted answer and the draft story it made. */
class StoryRun extends Model
{
    protected $guarded = [];

    public const STATUSES = [
        'prompt' => 'Prompt made',
        'pasted' => 'Answer pasted',
        'checked' => 'Checked',
        'saved' => 'Saved as a draft',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'result' => 'array', 'custom_data' => 'array'];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
