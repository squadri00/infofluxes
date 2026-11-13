<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The tables of "Challenge yourself": the five tests and the scores. Nothing in them is about a person. */
return new class extends Migration
{
    public function up(): void
    {
        // The Arabic passages of "Which one is from the Quran?"
        Schema::create('quiz_passages', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->index();          // quran, hadith, bible, poetry
            $table->string('reference');
            $table->string('source_note')->nullable();
            $table->text('arabic');
            $table->text('english')->nullable();
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('words')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_checked')->default(false);
            $table->unsignedInteger('times_shown')->default(0);
            $table->unsignedInteger('times_picked')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        // One line per finished test: only the score.
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('quiz', 40)->default('quran-style');
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->json('by_kind')->nullable();
            $table->timestamps();
        });

        // "Which scripture?"
        Schema::create('scripture_passages', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30)->index();
            $table->string('reference');
            $table->text('text');
            $table->string('source_note')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_checked')->default(false);
            $table->unsignedInteger('times_shown')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        // "Fact or myth?"
        Schema::create('fact_statements', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 30)->default('scripture')->index();
            $table->text('statement');
            $table->boolean('is_true');
            $table->text('explanation');
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_checked')->default(false);
            $table->unsignedInteger('times_shown')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        // "Who said it?"
        Schema::create('quote_passages', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30)->index();
            $table->text('quote');
            $table->string('speaker');
            $table->string('speaker_note')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_attributed')->default(false);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_checked')->default(false);
            $table->unsignedInteger('times_shown')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        // The 114 chapters, for "Put the chapters in order".
        Schema::create('quran_chapters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number')->unique();
            $table->string('arabic_name', 60);
            $table->string('name', 80);
            $table->string('meaning', 120);
            $table->string('honorific', 20)->nullable();
            $table->unsignedSmallInteger('verses');
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['quran_chapters', 'quote_passages', 'fact_statements', 'scripture_passages', 'quiz_attempts', 'quiz_passages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
