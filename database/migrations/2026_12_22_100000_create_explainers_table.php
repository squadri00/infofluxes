<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A short note about a word, a person, a place or an event. It shows in a small card when a reader taps the word in an article.
        Schema::create('explainers', function (Blueprint $table) {
            $table->id();
            $table->json('term');                                        // the word, one text for each language
            $table->json('body');                                        // the note, one text for each language
            $table->string('kind', 12)->default('word');                 // word, person, place, event, idea
            $table->json('aliases')->nullable();                         // other spellings that mean the same word
            $table->string('source_line', 500)->nullable();
            $table->string('status', 12)->default('draft')->index();     // draft or published
            $table->boolean('ai_assisted')->default(false);
            $table->string('reviewer_name', 255)->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('custom_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explainers');
    }
};
