<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A fact can point to an article that tells the whole story ("Read the full article"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('did_you_know_entries', function (Blueprint $table) {
            $table->foreignId('article_id')->nullable()->after('category_id')->constrained('articles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('did_you_know_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('article_id');
        });
    }
};
