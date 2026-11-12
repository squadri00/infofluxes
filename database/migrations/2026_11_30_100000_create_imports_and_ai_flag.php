<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Step 6: a log of every AI paste that was saved, and a flag on articles that were written with AI help. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20);                    // article, fact, categories (or mixed)
            $table->longText('raw_text');                  // exactly what was pasted
            $table->json('result')->nullable();            // what was made: article ids, categories made, items skipped
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('ai_assisted')->default(false)->after('reviewer_name');   // written with AI help: a person must put their name in "Reviewer" before it is published
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('ai_assisted');
        });
        Schema::dropIfExists('imports');
    }
};
