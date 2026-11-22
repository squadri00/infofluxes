<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For the members of the site:
 * - a signed-in member's challenge scores are kept with the account (a guest's score still has no name),
 * - what a member follows (a category, a contributor or a word),
 * - the notifications of a member (shown in the site and, for news, in a weekly email).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            $t->json('custom_data')->nullable();
        });

        Schema::create('follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 12);                       // category, person or keyword
            $t->unsignedBigInteger('target_id')->nullable();   // the category or the person
            $t->string('keyword', 60)->nullable();        // the word (small letters)
            $t->json('custom_data')->nullable();
            $t->timestamps();
            $t->index(['kind', 'target_id']);
            $t->index(['kind', 'keyword']);
            $t->unique(['user_id', 'kind', 'target_id', 'keyword']);
        });

        Schema::create('member_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 30);                       // comment_approved, comment_hidden, comment_reply, article_published, article_sent_back, new_work
            $t->string('title', 300);
            $t->string('url', 500)->nullable();
            $t->string('subject_type', 60)->nullable();   // the article or story this is about (so news is told once)
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('emailed_at')->nullable();      // news that went out in the weekly email
            $t->json('custom_data')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
            $t->index(['type', 'emailed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_notifications');
        Schema::dropIfExists('follows');
        Schema::table('quiz_attempts', function (Blueprint $t) {
            $t->dropColumn(['user_id', 'custom_data']);
        });
    }
};
