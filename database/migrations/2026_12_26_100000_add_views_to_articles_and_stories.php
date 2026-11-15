<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How many times the page was opened (one count per visitor per visit; staff and robots are not counted).
        foreach (['articles', 'stories'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedInteger('views')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['articles', 'stories'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('views'));
        }
    }
};
