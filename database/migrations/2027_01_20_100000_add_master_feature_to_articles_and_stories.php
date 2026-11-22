<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A second star for articles and stories: featured at the top of their master (top-level) category, with its own order. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['articles', 'stories'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('is_featured_master')->default(false)->after('featured_order');
                $t->unsignedInteger('featured_master_order')->default(0)->after('is_featured_master');
            });
        }
    }

    public function down(): void
    {
        foreach (['articles', 'stories'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['is_featured_master', 'featured_master_order']);
            });
        }
    }
};
