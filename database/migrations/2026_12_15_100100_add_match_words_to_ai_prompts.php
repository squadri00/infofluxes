<?php

use App\Models\AiPrompt;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** The prompts made in Phase 1 get their match words (a prompt that already has some is left alone). */
    public function up(): void
    {
        foreach ((array) require resource_path('prompts/library/index.php') as $slug => [, , , , $match]) {
            $p = AiPrompt::query()->where('slug', $slug)->first();
            if ($p && ! isset($p->custom_data['match'])) {
                $p->update(['custom_data' => array_merge((array) $p->custom_data, ['match' => $match])]);
            }
        }
    }

    public function down(): void {}
};
