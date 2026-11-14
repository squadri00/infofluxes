<?php

use App\Models\AiPrompt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('kind', 20)->default('specialist'); // master | specialist
            $table->string('title');
            $table->string('use_for')->nullable();
            $table->longText('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        AiPrompt::installDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};
