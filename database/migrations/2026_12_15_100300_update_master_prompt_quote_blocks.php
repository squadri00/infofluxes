<?php

use App\Models\AiPrompt;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** The master rules get the verse and hadith block format. A master prompt the owner already changed is left alone. */
    public function up(): void
    {
        $p = AiPrompt::query()->where('slug', 'master')->first();
        if ($p && md5(trim($p->body)) === '07df06b38ab534b89ac40ac397ebaaa4') {
            $p->update(['body' => AiPrompt::fileBody('master')]);
        }
    }

    public function down(): void {}
};
