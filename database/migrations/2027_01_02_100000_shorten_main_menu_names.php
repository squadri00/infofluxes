<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Shorter main-menu names. The main menu shows the names of the top categories, so those are renamed
 * (English only; other languages keep their own wording). Web addresses do not change. Safe to run again.
 * "Did You Know?" stays as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'About Islam' => 'Islam',
            'History and Civilizations' => 'History',
            'History and Civilisations' => 'History',
            'Science and Knowledge' => 'Science',
            'Science and Faith' => 'Science',
            'Comparative Religions' => 'Religions',
            'Comparative Religion' => 'Religions',
            'Society and World Affairs' => 'World Affairs',
            'Stories in pictures' => 'Visual Stories',
        ];

        foreach (DB::table('categories')->whereNull('parent_id')->get(['id', 'name']) as $row) {
            $names = json_decode((string) $row->name, true);
            if (! is_array($names) || ! isset($names['en'], $renames[$names['en']])) {
                continue;
            }
            $names['en'] = $renames[$names['en']];
            DB::table('categories')->where('id', $row->id)->update(['name' => json_encode($names, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
    }
};
