<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Index view of a category lists the subjects still to be written ("details coming").
 * Only an article marked as an index line may show there before it is published, so a person's article that waits for a
 * moderator, or a private draft, is never listed by its title.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_index_line')->default(false)->after('ai_assisted');
        });

        // The lines moved from the old index (working name such as QR-1.1.001) are index lines.
        if (DB::getDriverName() === 'mysql') {
            DB::table('articles')->where('working_name', 'REGEXP', '^[A-Z]{2}-[0-9]+(\\.[0-9]+)*$')->update(['is_index_line' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('is_index_line');
        });
    }
};
