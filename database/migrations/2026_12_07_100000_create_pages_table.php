<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The website pages (About, Contact, Privacy and so on). Not articles: no category, no author, just words. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('body')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('status', 20)->default('draft')->index();     // draft, published
            $table->boolean('in_footer')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('no_index')->default(false);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
