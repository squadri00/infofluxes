<?php

namespace App\Models;

use App\Support\Social;
use Illuminate\Database\Eloquent\Model;

/** One of the site's own accounts on a social network. */
class SocialLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'custom_data' => 'array'];
    }

    /** The words that name the button (for screen readers and the hover hint). */
    public function buttonTitle(): string
    {
        return $this->label ?: (Social::PLATFORMS[$this->platform][0] ?? ucfirst($this->platform));
    }
}
