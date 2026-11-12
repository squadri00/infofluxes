<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The home page can feature articles: a tick box and an order (1 comes first). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_index_line');
            $table->unsignedInteger('featured_order')->default(0)->after('is_featured');
            $table->index(['is_featured', 'featured_order']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'featured_order']);
            $table->dropColumn(['is_featured', 'featured_order']);
        });
    }
};
