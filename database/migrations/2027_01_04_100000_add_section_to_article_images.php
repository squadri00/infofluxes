<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('article_images', 'section')) {
            Schema::table('article_images', function (Blueprint $table) {
                // Under which main heading the picture goes (1 = the first one). Empty = automatic, in the order the pictures were added.
                $table->unsignedSmallInteger('section')->nullable()->after('sort_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('article_images', 'section')) {
            Schema::table('article_images', function (Blueprint $table) {
                $table->dropColumn('section');
            });
        }
    }
};
