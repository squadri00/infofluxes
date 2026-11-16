<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Story maker keeps every run: the form, the prompt that was made, the answer that was pasted and what became of it.
 * So a prompt or an answer is never lost, and a run can be opened, checked and saved again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_runs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->default('story');              // story (step 1) or translation (step 2, later)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('story_id')->nullable()->constrained('stories')->nullOnDelete();   // the draft this run made
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('topic', 300)->default('');
            $table->json('options')->nullable();                       // slides, notes, picture style, languages
            $table->longText('prompt')->nullable();                    // the prompt as it was made
            $table->longText('answer')->nullable();                    // the answer of the AI, as pasted
            $table->json('result')->nullable();                        // what the last check found
            $table->string('status', 20)->default('prompt');           // prompt, pasted, checked, saved
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_runs');
    }
};
