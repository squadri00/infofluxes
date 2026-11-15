<?php

namespace Tests\Feature;

use App\Filament\Pages\MoveFromOldSite;
use App\Filament\Pages\SlideViewerSettings;
use App\Models\Category;
use App\Models\HeroMessage;
use App\Models\HeroSlide;
use App\Models\Setting;
use App\Models\User;
use App\Support\Hero;
use App\Support\OldHeroImport;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 14: the home page slider (the banner) and the one admin screen "Sliders" that manages every slider of the site. */
class Step14Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
        Storage::fake('public');
    }

    private function message(string $title, array $extra = []): HeroMessage
    {
        return HeroMessage::create(array_merge(['title' => ['en' => $title], 'is_active' => true, 'sort_order' => 1], $extra));
    }

    private function slide(string $heading, array $extra = []): HeroSlide
    {
        return HeroSlide::create(array_merge(['image' => 'hero/'.str($heading)->slug().'.webp', 'image_alt' => ['en' => 'Alt of '.$heading], 'heading' => ['en' => $heading], 'is_active' => true, 'sort_order' => 1], $extra));
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    // ---------- the home page ----------

    public function test_a_home_page_with_no_banner_is_as_before(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('class="hero', $html);
        $this->assertStringNotContainsString('js/hero.js', $html);
        $this->assertStringContainsString('<h1 class="visually-hidden">InfoFluxes</h1>', $html);
    }

    public function test_the_banner_shows_one_set_of_words_that_is_in_use(): void
    {
        $this->message('Words in use', ['subtitle' => ['en' => 'The line under'], 'description' => ['en' => 'The longer description.']]);
        $this->message('Words switched off', ['is_active' => false]);

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('class="hero hero--text-only"', $html, 'no pictures yet');
        $this->assertStringContainsString('<h1 class="hero__title">Words in use</h1>', $html);
        $this->assertStringContainsString('The line under', $html);
        $this->assertStringContainsString('The longer description.', $html);
        $this->assertStringNotContainsString('Words switched off', $html);
        $this->assertStringNotContainsString('<h1 class="visually-hidden">', $html, 'the banner has the one h1');
    }

    public function test_each_visit_can_show_a_different_set_of_words(): void
    {
        foreach (['Aaa words', 'Bbb words', 'Ccc words'] as $t) {
            $this->message($t);
        }

        $seen = [];
        for ($i = 0; $i < 40; $i++) {
            preg_match('/hero__title">([^<]*)</', $this->get('/')->getContent(), $m);
            $seen[$m[1]] = true;
        }

        $this->assertGreaterThan(1, count($seen), 'picked at random');
        $this->assertEmpty(array_diff(array_keys($seen), ['Aaa words', 'Bbb words', 'Ccc words']));
    }

    public function test_the_slider_shows_the_active_pictures_in_order_with_their_words_and_button(): void
    {
        $this->slide('Second picture', ['sort_order' => 2, 'text' => ['en' => 'A short line'], 'button_label' => ['en' => 'Read it'], 'button_url' => '/some-page', 'new_tab' => true]);
        $this->slide('First picture', ['sort_order' => 1]);
        $this->slide('Hidden picture', ['sort_order' => 3, 'is_active' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="hero hero--media-only"', $html, 'no words yet');
        $this->assertLessThan(strpos($html, 'Second picture'), strpos($html, 'First picture'));
        $this->assertStringNotContainsString('Hidden picture', $html);
        $this->assertStringContainsString('hero__slide is-active', $html);
        $this->assertStringContainsString('storage/hero/first-picture.webp', $html);
        $this->assertStringContainsString('alt="Alt of First picture"', $html);
        $this->assertStringContainsString('A short line', $html);
        $this->assertMatchesRegularExpression('#<a class="hero__button" href="[^"]*/some-page"\s+target="_blank"\s+rel="noopener noreferrer"[^>]*>Read it</a>#', $html);
        $this->assertStringContainsString('Picture 1 of 2', $html);
        $this->assertStringContainsString('js/hero.js', $html, 'more than one picture: the slider script');
        $this->assertStringContainsString('data-hero-seconds="6"', $html);
    }

    public function test_one_picture_does_not_move_and_needs_no_script(): void
    {
        $this->slide('Only picture');

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('data-hero-seconds="0"', $html);
        $this->assertStringNotContainsString('js/hero.js', $html);
        $this->assertStringNotContainsString('data-hero-controls', $html);
    }

    public function test_the_seconds_come_from_the_setting_and_zero_means_it_never_moves(): void
    {
        $this->slide('One');
        $this->slide('Two', ['sort_order' => 2]);

        Setting::put('hero_slide_seconds', '12');
        $this->assertStringContainsString('data-hero-seconds="12"', $this->get('/')->getContent());

        Setting::put('hero_slide_seconds', '0');
        $this->assertStringContainsString('data-hero-seconds="0"', $this->get('/')->getContent());

        $this->assertSame(60, (function () { Setting::put('hero_slide_seconds', '999'); return Hero::seconds(); })(), 'never more than a minute');
    }

    public function test_a_button_needs_its_words_and_a_safe_address(): void
    {
        $this->slide('No label', ['button_url' => '/somewhere']);
        $this->slide('Bad link', ['sort_order' => 2, 'button_label' => ['en' => 'Click'], 'button_url' => 'javascript:alert(1)']);
        $this->slide('Good link', ['sort_order' => 3, 'button_label' => ['en' => 'Open'], 'button_url' => 'https://example.org/x']);
        $this->slide('Odd link', ['sort_order' => 4, 'button_label' => ['en' => 'Odd'], 'button_url' => '//evil.example']);

        $html = $this->get('/')->getContent();

        $this->assertSame(1, substr_count($html, 'class="hero__button"'), 'only the good one');
        $this->assertStringContainsString('href="https://example.org/x"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertNull(Hero::safeUrl('javascript:alert(1)'));
        $this->assertNull(Hero::safeUrl('//evil'));
        $this->assertSame('mailto:a@b.org', Hero::safeUrl('mailto:a@b.org'));
        $this->assertSame(url('/page'), Hero::link('/page'));
    }

    public function test_the_banner_can_be_switched_off(): void
    {
        $this->message('Some words');
        $this->slide('A picture');
        Setting::put('hero_on', '0');

        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('class="hero', $html);
        $this->assertStringContainsString('<h1 class="visually-hidden">', $html);
    }

    public function test_the_home_words_show_under_the_banner_and_the_headline_when_there_is_no_banner(): void
    {
        Setting::put('home_headline', ['en' => 'The big headline']);
        Setting::put('home_intro', ['en' => "An introduction.\n\nSecond paragraph."]);
        Setting::put('home_message', ['en' => 'A message <b>safe</b>']);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('<h1 class="home__headline">The big headline</h1>', $html);
        $this->assertStringContainsString('<p>An introduction.</p><p>Second paragraph.</p>', $html);
        $this->assertStringContainsString('A message &lt;b&gt;safe&lt;/b&gt;', $html);

        $this->message('Banner words');
        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('home__headline', $html, 'with a banner, the banner has the title');
        $this->assertStringContainsString('An introduction.', $html);
    }

    // ---------- the one admin screen ----------

    public function test_the_screen_is_called_sliders_it_is_in_the_settings_menu_and_only_the_super_user_opens_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->get('/admin/sliders')->assertForbidden();

        $this->superuser();
        $this->get('/admin/sliders')->assertOk()->assertSee('Sliders')->assertSee('Home page slider')->assertSee('Story slider');
        $this->assertSame('Sliders', SlideViewerSettings::getNavigationLabel());
        $this->assertSame('Settings', SlideViewerSettings::getNavigationGroup());
    }

    public function test_the_screen_shows_what_is_there_now_for_both_sliders(): void
    {
        $this->superuser();
        $this->message('Existing words', ['subtitle' => ['en' => 'A sub']]);
        $this->slide('Existing picture', ['button_label' => ['en' => 'Go'], 'button_url' => '/go']);
        Setting::put('viewer_width', 'boxed');
        Setting::put('hero_slide_seconds', '9');

        $page = Livewire::test(SlideViewerSettings::class)->assertOk();

        $page->assertFormSet(fn (array $state) => $state['hero_on'] === true
            && $state['hero_slide_seconds'] === '9'
            && $state['messages'][0]['title']['en'] === 'Existing words'
            && array_values($state['slides'])[0]['button_url'] === '/go'
            && array_values(array_values($state['slides'])[0]['image'])[0] === 'hero/existing-picture.webp'
            && $state['viewer_width'] === 'boxed'
            && $state['story_cover_on'] === true);
    }

    public function test_the_words_can_be_added_changed_reordered_and_removed_on_the_screen(): void
    {
        $this->superuser();
        $a = $this->message('Alpha');
        $b = $this->message('Beta', ['sort_order' => 2]);

        // change one, add one, put the new one first, and take Beta off
        Livewire::test(SlideViewerSettings::class)
            ->fillForm(['messages' => [
                ['title' => ['en' => 'Brand new'], 'subtitle' => ['en' => 'New line'], 'is_active' => true],
                ['id' => $a->id, 'title' => ['en' => 'Alpha changed'], 'is_active' => false],
            ]])
            ->call('save')->assertHasNoFormErrors();

        $rows = HeroMessage::orderBy('sort_order')->get();
        $this->assertSame(['Brand new', 'Alpha changed'], $rows->map(fn ($m) => $m->getTranslation('title', 'en'))->all());
        $this->assertSame([1, 2], $rows->pluck('sort_order')->all());
        $this->assertFalse($rows[1]->is_active);
        $this->assertSame($a->id, $rows[1]->id, 'the same row, changed');
        $this->assertNull(HeroMessage::find($b->id), 'taken off the list: removed');
        $this->assertSame('New line', $rows[0]->getTranslation('subtitle', 'en'));
    }

    public function test_a_title_is_needed_for_each_set_of_words(): void
    {
        $this->superuser();

        Livewire::test(SlideViewerSettings::class)->fillForm(['messages' => [['title' => ['en' => ''], 'is_active' => true]]])->call('save')->assertHasFormErrors();

        $this->assertSame(0, HeroMessage::count());
    }

    public function test_the_pictures_can_be_changed_reordered_and_removed_on_the_screen(): void
    {
        $this->superuser();
        $one = $this->slide('One');
        $two = $this->slide('Two', ['sort_order' => 2]);
        $three = $this->slide('Three', ['sort_order' => 3]);

        Livewire::test(SlideViewerSettings::class)
            ->fillForm(['slides' => [
                ['id' => $three->id, 'image' => [$three->image], 'image_alt' => ['en' => 'Alt three'], 'heading' => ['en' => 'Three now first'], 'is_active' => true, 'new_tab' => false],
                ['id' => $one->id, 'image' => [$one->image], 'image_alt' => ['en' => 'Alt one'], 'heading' => ['en' => 'One'], 'button_label' => ['en' => 'Go'], 'button_url' => 'https://example.org', 'new_tab' => true, 'is_active' => false],
            ]])
            ->call('save')->assertHasNoFormErrors();

        $rows = HeroSlide::orderBy('sort_order')->get();
        $this->assertSame([$three->id, $one->id], $rows->pluck('id')->all());
        $this->assertSame('Three now first', $rows[0]->getTranslation('heading', 'en'));
        $this->assertFalse($rows[1]->is_active);
        $this->assertTrue($rows[1]->new_tab);
        $this->assertSame('https://example.org', $rows[1]->button_url);
        $this->assertNull(HeroSlide::find($two->id), 'removed from the list');
    }

    public function test_a_picture_needs_its_file_and_its_description_and_a_button_needs_an_address(): void
    {
        $this->superuser();
        $one = $this->slide('One');

        Livewire::test(SlideViewerSettings::class)->fillForm(['slides' => [['id' => $one->id, 'image' => null, 'image_alt' => ['en' => 'x'], 'is_active' => true]]])->call('save')->assertHasFormErrors();
        Livewire::test(SlideViewerSettings::class)->fillForm(['slides' => [['id' => $one->id, 'image' => [$one->image], 'image_alt' => ['en' => ''], 'is_active' => true]]])->call('save')->assertHasFormErrors();
        Livewire::test(SlideViewerSettings::class)->fillForm(['slides' => [['id' => $one->id, 'image' => [$one->image], 'image_alt' => ['en' => 'Alt'], 'button_label' => ['en' => 'Go'], 'button_url' => '', 'is_active' => true]]])->call('save')->assertHasFormErrors();
        Livewire::test(SlideViewerSettings::class)->fillForm(['slides' => [['id' => $one->id, 'image' => [$one->image], 'image_alt' => ['en' => 'Alt'], 'button_label' => ['en' => 'Go'], 'button_url' => 'javascript:alert(1)', 'is_active' => true]]])->call('save')->assertHasFormErrors();

        $this->assertSame('One', HeroSlide::first()->getTranslation('heading', 'en'), 'nothing was changed by the refused saves');
    }

    public function test_the_banner_settings_and_home_words_are_saved_and_the_usual_ones_are_not_kept(): void
    {
        $this->superuser();

        Livewire::test(SlideViewerSettings::class)
            ->fillForm(['hero_on' => false, 'hero_slide_seconds' => '10', 'home_headline' => ['en' => 'Our headline'], 'home_intro' => ['en' => 'Our intro'], 'home_message' => ['en' => '']])
            ->call('save')->assertHasNoFormErrors();

        Setting::forget();
        $this->assertSame('0', Setting::get('hero_on'));
        $this->assertSame('10', Setting::get('hero_slide_seconds'));
        $this->assertSame('Our headline', Setting::get('home_headline'));
        $this->assertSame('Our intro', Setting::get('home_intro'));
        $this->assertNull(Setting::get('home_message'), 'an empty box shows nothing');

        Livewire::test(SlideViewerSettings::class)->fillForm(['hero_on' => true, 'hero_slide_seconds' => '6'])->call('save');
        Setting::forget();
        $this->assertNull(Setting::get('hero_on'), 'the usual value is not kept');
        $this->assertNull(Setting::get('hero_slide_seconds'));
        $this->assertSame(6, Hero::seconds());
    }

    public function test_one_save_keeps_the_home_slider_and_the_story_slider_apart(): void
    {
        $this->superuser();
        $this->message('Keep me');
        Setting::put('listen_rate', '120');

        Livewire::test(SlideViewerSettings::class)->fillForm(['viewer_width' => 'boxed'])->call('save')->assertHasNoFormErrors();

        $this->assertSame(1, HeroMessage::count(), 'saving a story control leaves the home words');
        Setting::forget();
        $this->assertSame('boxed', Setting::get('viewer_width'));
        $this->assertSame('120', Setting::get('listen_rate'));
    }

    public function test_back_to_the_usual_story_settings_leaves_the_home_slider_alone(): void
    {
        $this->superuser();
        $this->message('Keep me');
        $this->slide('Keep me too');
        Setting::put('viewer_width', 'boxed');
        Setting::put('hero_slide_seconds', '9');

        Livewire::test(SlideViewerSettings::class)->callAction('reset');

        Setting::forget();
        $this->assertNull(Setting::get('viewer_width'));
        $this->assertSame('9', Setting::get('hero_slide_seconds'));
        $this->assertSame(1, HeroMessage::count());
        $this->assertSame(1, HeroSlide::count());
    }

    public function test_the_screen_has_links_to_see_the_home_page_and_a_story(): void
    {
        $this->superuser();

        Livewire::test(SlideViewerSettings::class)->assertActionVisible('seeHome')->assertActionHidden('see');
    }

    // ---------- the old home slider ----------

    private function fakeOldHero(): string
    {
        $path = sys_get_temp_dir().'/oldhero-'.uniqid();
        mkdir($path.'/storage/app/public/hero', 0777, true);
        config(['infofluxes.old_site_path' => $path, 'database.connections.old_site' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('old_site');
        $old = Schema::connection('old_site');
        $old->create('index_entries', fn (Blueprint $t) => $t->id());
        $old->create('settings', function (Blueprint $t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); });
        $old->create('hero_messages', function (Blueprint $t) {
            $t->id(); $t->string('title'); $t->string('subtitle')->nullable(); $t->text('description')->nullable(); $t->boolean('is_active')->default(true); $t->unsignedInteger('sort_order')->default(0);
        });
        $old->create('hero_slides', function (Blueprint $t) {
            $t->id(); $t->string('image'); $t->string('image_alt')->nullable(); $t->string('heading')->nullable(); $t->string('text')->nullable(); $t->string('button_label')->nullable();
            $t->string('button_url')->nullable(); $t->boolean('new_tab')->default(false); $t->boolean('is_active')->default(true); $t->unsignedInteger('sort_order')->default(0);
        });
        file_put_contents($path.'/storage/app/public/hero/one.webp', 'picture-one');
        $db = DB::connection('old_site');
        $db->table('hero_messages')->insert([
            ['title' => 'Old words one', 'subtitle' => 'Sub one', 'description' => 'Desc one', 'is_active' => 1, 'sort_order' => 1],
            ['title' => 'Old words two', 'subtitle' => null, 'description' => null, 'is_active' => 0, 'sort_order' => 2],
        ]);
        $db->table('hero_slides')->insert([
            ['image' => 'hero/one.webp', 'image_alt' => 'Old alt', 'heading' => 'Old heading', 'text' => 'Old line', 'button_label' => 'Go', 'button_url' => '/old-page', 'new_tab' => 1, 'is_active' => 1, 'sort_order' => 1],
            ['image' => 'hero/missing.webp', 'image_alt' => 'No file', 'heading' => null, 'text' => null, 'button_label' => null, 'button_url' => null, 'new_tab' => 0, 'is_active' => 1, 'sort_order' => 2],
        ]);
        $db->table('settings')->insert([
            ['key' => 'hero_on', 'value' => '0'], ['key' => 'hero_slide_seconds', 'value' => '9'], ['key' => 'home_headline', 'value' => 'Old headline'], ['key' => 'home_intro', 'value' => ''],
        ]);

        return $path;
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir.'/storage/app/public/hero/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach ([$dir.'/storage/app/public/hero', $dir.'/storage/app/public', $dir.'/storage/app', $dir.'/storage', $dir] as $d) {
            @rmdir($d);
        }
    }

    public function test_the_old_banner_is_checked_then_brought_across(): void
    {
        $dir = $this->fakeOldHero();
        try {
            $r = (new OldHeroImport)->check();
            $this->assertSame(['messages_new' => 2, 'messages_existing' => 0, 'slides_new' => 1, 'slides_existing' => 0, 'images_copied' => 1, 'images_missing' => 1, 'settings_changed' => 3], $r['total']);
            $this->assertSame(0, HeroMessage::count(), 'checking changes nothing');
            Storage::disk('public')->assertMissing('hero/one.webp');

            (new OldHeroImport)->bring();

            $m = HeroMessage::orderBy('sort_order')->get();
            $this->assertSame(['Old words one', 'Old words two'], $m->map(fn ($x) => $x->getTranslation('title', 'en'))->all());
            $this->assertSame('Sub one', $m[0]->getTranslation('subtitle', 'en'));
            $this->assertFalse($m[1]->is_active);
            $s = HeroSlide::first();
            $this->assertSame('hero/one.webp', $s->image);
            $this->assertSame('/old-page', $s->button_url);
            $this->assertTrue($s->new_tab);
            $this->assertSame('Old heading', $s->getTranslation('heading', 'en'));
            $this->assertSame(1, HeroSlide::count(), 'a picture whose file is missing is not made');
            Storage::disk('public')->assertExists('hero/one.webp');
            $this->assertSame('picture-one', Storage::disk('public')->get('hero/one.webp'));
            Setting::forget();
            $this->assertSame('0', Setting::get('hero_on'));
            $this->assertSame('9', Setting::get('hero_slide_seconds'));
            $this->assertSame('Old headline', Setting::get('home_headline'));
            $this->assertNull(Setting::get('home_intro'), 'an empty old box brings nothing');
        } finally {
            $this->removeDir($dir);
        }
    }

    public function test_bringing_the_old_banner_again_makes_nothing_twice_and_keeps_your_own_words(): void
    {
        $dir = $this->fakeOldHero();
        try {
            Setting::put('home_headline', ['en' => 'My own headline']);
            (new OldHeroImport)->bring();
            $messages = HeroMessage::count();

            $again = (new OldHeroImport)->bring();

            $this->assertSame($messages, HeroMessage::count());
            $this->assertSame(1, HeroSlide::count());
            $this->assertSame(['messages_new' => 0, 'messages_existing' => 2, 'slides_new' => 0, 'slides_existing' => 1, 'images_copied' => 0, 'images_missing' => 1, 'settings_changed' => 0], $again['total']);
            Setting::forget();
            $this->assertSame('My own headline', Setting::get('home_headline'), 'your own words are never replaced');
        } finally {
            $this->removeDir($dir);
        }
    }

    public function test_the_move_page_brings_the_home_slider_in_two_steps(): void
    {
        $dir = $this->fakeOldHero();
        try {
            $this->superuser();

            Livewire::test(MoveFromOldSite::class)
                ->assertSee('Your home page slider')
                ->call('checkHero')->assertSee('Would bring')->assertSee('Bring the home slider')
                ->call('bringHero')->assertSee('Brought');

            $this->assertSame(2, HeroMessage::count());
        } finally {
            $this->removeDir($dir);
        }
    }

    public function test_a_category_page_and_other_pages_are_not_changed_by_the_banner(): void
    {
        $this->message('Words');
        Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);

        $this->get('/category/shows')->assertOk()->assertDontSee('class="hero', false);
    }
}
