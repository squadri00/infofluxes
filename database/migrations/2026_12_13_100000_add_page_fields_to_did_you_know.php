<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** A fact gets a page of its own (an address) and, if you like, a picture for its card. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('did_you_know_entries', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('id');
            $table->string('image')->nullable()->after('source');
            $table->string('image_alt')->nullable()->after('image');
        });

        // The facts that exist already get an address made from their heading.
        $used = [];
        foreach (DB::table('did_you_know_entries')->orderBy('id')->get(['id', 'title']) as $row) {
            $title = json_decode((string) $row->title, true);
            $base = Str::slug(is_array($title) ? (string) (reset($title) ?: 'fact') : 'fact') ?: 'fact';
            $slug = $base;
            for ($n = 2; in_array($slug, $used, true); $n++) {
                $slug = $base.'-'.$n;
            }
            $used[] = $slug;
            DB::table('did_you_know_entries')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('did_you_know_entries', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'image', 'image_alt']);
        });
    }
};
