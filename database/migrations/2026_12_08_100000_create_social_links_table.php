<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The site's own accounts on social networks: one row each, shown as round buttons in the footer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30)->index();
            $table->string('label')->nullable();
            $table->string('url', 2048);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        // The five addresses that used to be single settings become rows (when they were filled in).
        $order = 0;
        foreach (['facebook', 'instagram', 'x', 'youtube', 'tiktok'] as $platform) {
            $value = DB::table('settings')->where('key', 'social_'.$platform)->value('value');
            $url = is_string($value) ? trim((string) json_decode($value, true) ?: $value, '"') : '';
            if (preg_match('~^https?://~i', $url)) {
                DB::table('social_links')->insert(['platform' => $platform, 'url' => $url, 'is_active' => true, 'sort_order' => ++$order, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('settings')->where('key', 'social_'.$platform)->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_links');
    }
};
