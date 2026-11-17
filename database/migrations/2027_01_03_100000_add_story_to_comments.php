<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Step 80: people can comment on a story too ("Audience"). A comment belongs to an article OR to a story. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('story_id')->nullable()->after('article_id')->constrained('stories')->cascadeOnDelete();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('article_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('story_id');
        });
    }
};
