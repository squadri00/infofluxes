<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A "Did You Know?" fact can be featured on the home page, with an order number, like an article or a story. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('did_you_know_entries', function (Blueprint $t) {
            $t->boolean('is_featured')->default(false)->after('status');
            $t->unsignedInteger('featured_order')->default(0)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('did_you_know_entries', function (Blueprint $t) {
            $t->dropColumn(['is_featured', 'featured_order']);
        });
    }
};
