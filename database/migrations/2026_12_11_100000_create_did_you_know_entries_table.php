<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Did You Know?": short facts listed like a FAQ (a heading, a short answer, where to check it). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('did_you_know_entries', function (Blueprint $table) {
            $table->id();
            $table->json('title');                                   // the heading or question
            $table->json('body')->nullable();                        // the short answer
            $table->string('source', 500)->nullable();               // where to check it (a name, or a web address)
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index(); // draft, published
            $table->string('reviewer_name')->nullable();             // the person who checked it
            $table->boolean('ai_assisted')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('did_you_know_entries');
    }
};
