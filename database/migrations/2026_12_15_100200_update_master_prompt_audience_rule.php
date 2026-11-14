<?php

use App\Models\AiPrompt;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** The master rules get the audience and writing-style rule first. A master prompt the owner already changed is left alone. */
    public function up(): void
    {
        $p = AiPrompt::query()->where('slug', 'master')->first();
        if ($p && md5(trim($p->body)) === 'fc0864866dc82054e64dcbc214f3d69f') {
            $p->update(['body' => AiPrompt::fileBody('master')]);
        }
    }

    public function down(): void {}
};
