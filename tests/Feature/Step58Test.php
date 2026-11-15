<?php

namespace Tests\Feature;

use App\Filament\Resources\Languages\LanguageResource;
use App\Models\Language;
use App\Models\User;
use App\Support\Languages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Languages with flags: English only for now, ready for more. */
class Step58Test extends TestCase
{
    use RefreshDatabase;

    public function test_english_is_installed_as_the_main_language(): void
    {
        $en = Language::where('code', 'en')->first();
        $this->assertNotNull($en);
        $this->assertTrue($en->is_default);
        $this->assertSame('active', $en->status);
        $this->assertSame('gb', $en->flag);
    }

    public function test_every_flag_and_catalog_entry_has_a_picture(): void
    {
        $data = Languages::data();
        foreach (array_keys($data['flags']) as $flag) {
            $this->assertFileExists(public_path("flags/{$flag}.svg"), $flag);
        }
        foreach ($data['catalog'] as $code => $row) {
            $this->assertArrayHasKey($row[2], $data['flags'], "catalog {$code} uses an unknown flag");
        }
    }

    public function test_the_dropdown_shows_the_flag_even_with_one_language(): void
    {
        $this->get('/')->assertSee('lang-menu', false)->assertSee('flags/gb.svg', false)->assertSee('English');
    }

    public function test_coming_soon_is_shown_but_cannot_be_chosen(): void
    {
        Language::create(['code' => 'ur', 'name' => 'Urdu', 'native_name' => 'اردو', 'flag' => 'pk', 'is_rtl' => true, 'status' => 'coming_soon']);

        $this->get('/')->assertSee('اردو')->assertSee('flags/pk.svg', false)->assertSee('Soon')->assertDontSee('language/ur', false);
        $this->get('/language/ur');
        $this->get('/')->assertSee('lang="en"', false)->assertSee('dir="ltr"', false);
    }

    public function test_an_active_language_can_be_chosen_and_sets_the_direction(): void
    {
        Language::create(['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'flag' => 'sa', 'is_rtl' => true, 'status' => 'active']);

        $this->get('/')->assertSee('language/ar', false);
        $this->get('/language/ar')->assertRedirect();
        $this->get('/')->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false);
    }

    public function test_the_main_language_stays_on_and_cannot_be_deleted(): void
    {
        $en = Language::where('code', 'en')->first();
        $en->update(['status' => 'off']);
        $this->assertSame('active', $en->fresh()->status);

        $en->delete();
        $this->assertNotNull(Language::where('code', 'en')->first());
    }

    public function test_editors_get_a_box_for_coming_soon_languages_but_visitors_do_not(): void
    {
        $this->assertSame(['en'], array_keys(Languages::forEditors()));
        Language::create(['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'flag' => 'fr', 'status' => 'coming_soon']);
        $this->assertSame(['en', 'fr'], array_keys(Languages::forEditors()));
        $this->assertSame(['en'], array_keys(Languages::active()));

        Language::where('code', 'fr')->update(['status' => 'off']);
        Languages::forget();
        $this->assertSame(['en'], array_keys(Languages::forEditors()));
    }

    public function test_only_the_super_user_manages_languages(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->assertTrue(LanguageResource::canViewAny());

        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(LanguageResource::canViewAny());
    }
}
