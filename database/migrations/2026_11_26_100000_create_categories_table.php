<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Step 2: categories, sub-categories and sub-sub-categories (one table, one tree, like ECT). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();                                              // the permanent number; the code shown is C-<id>
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->json('name');                                      // one text for each language, e.g. {"en":"Shows"}
            $table->string('working_name')->nullable();                // only for the admin, to tell similar names apart
            $table->string('slug')->unique();                          // the web address
            $table->string('image')->nullable();
            $table->json('image_alt')->nullable();
            $table->json('description')->nullable();
            $table->json('header_text')->nullable();                   // text shown above the list on the category page
            $table->json('page_title')->nullable();                    // optional, for search engines
            $table->json('meta_description')->nullable();              // optional, for search engines
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->string('tree_path')->default('')->index();         // keeps the tree in order in a list (rebuilt on every save)
            $table->unsignedTinyInteger('depth')->default(0);
            $table->json('custom_data')->nullable();                   // extra items the owner wants to keep
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
