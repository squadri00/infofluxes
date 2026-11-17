<?php

namespace Tests\Feature;

use App\Filament\Pages\SlideViewerSettings;
use App\Models\HeroMessage;
use App\Models\HeroSlide;
use App\Models\Setting;
use App\Models\User;
use App\Support\Hero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 71: video clips in the home page banner (no controls, fill the banner, words on top; pictures still work). */
class Step71Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        HeroMessage::create(['title' => ['en' => 'Words on top'], 'is_active' => true]);
    }

    private function clip(array $extra = []): HeroSlide
    {
        return HeroSlide::create(array_merge(['media_type' => 'video', 'video' => 'hero/videos/a.mp4', 'image' => null, 'is_active' => true, 'sort_order' => HeroSlide::count() + 1], $extra));
    }

    private function picture(array $extra = []): HeroSlide
    {
        return HeroSlide::create(array_merge(['media_type' => 'image', 'image' => 'hero/p.webp', 'image_alt' => ['en' => 'A picture'], 'is_active' => true, 'sort_order' => HeroSlide::count() + 1], $extra));
    }

    public function test_a_clip_is_a_plain_video_that_plays_by_itself_without_controls(): void
    {
        $this->clip();
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('~<video class="hero__video".*?</video>~s', $html, $m);

        $this->assertNotEmpty($m, 'the clip is in the banner');
        $video = $m[0];
        foreach (['data-hero-video', ' muted', ' playsinline', ' autoplay', 'disablepictureinpicture'] as $needle) {
            $this->assertStringContainsString($needle, $video, $needle);
        }
        $this->assertStringNotContainsString(' loop', $video, 'a clip plays to its end; only a banner with one slide loops it (hero.js)');
        $js = (string) file_get_contents(public_path('js/hero.js'));
        $this->assertStringContainsString('v.loop = single', $js);
        $this->assertStringContainsString("addEventListener('ended'", $js);
        $this->assertStringNotContainsString('controls', $video);
        $this->assertStringContainsString('<source src="'.asset('storage/hero/videos/a.mp4').'" type="video/mp4">', $video);
        $this->assertStringContainsString('Words on top', $html, 'the words stay on the banner');
    }

    public function test_only_the_first_clip_starts_at_once_the_others_wait(): void
    {
        $this->clip(['video' => 'hero/videos/a.mp4']);
        $this->clip(['video' => 'hero/videos/b.webm']);
        $html = $this->get('/')->getContent();
        preg_match_all('~<video class="hero__video".*?</video>~s', $html, $m);

        $this->assertCount(2, $m[0]);
        $this->assertStringContainsString(' autoplay', $m[0][0]);
        $this->assertStringContainsString('preload="auto"', $m[0][0]);
        $this->assertStringNotContainsString(' autoplay', $m[0][1]);
        $this->assertStringContainsString('preload="none"', $m[0][1]);
        $this->assertStringContainsString('type="video/webm"', $m[0][1]);
    }

    public function test_pictures_still_work_and_clips_and_pictures_can_be_mixed(): void
    {
        $this->clip();
        $this->picture();
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('<video class="hero__video"', $html);
        $this->assertStringContainsString('alt="A picture"', $html);
        $this->assertSame(2, substr_count($html, 'data-hero-slide'));
    }

    public function test_a_still_picture_is_the_poster_of_the_clip(): void
    {
        $this->clip(['image' => 'hero/still.webp']);
        $html = $this->get('/')->getContent();
        preg_match('~<video class="hero__video".*?</video>~s', $html, $m);
        $this->assertStringContainsString('poster="', $m[0]);
        $this->assertStringContainsString('hero/still.webp', $m[0]);
    }

    public function test_a_video_slide_without_its_file_and_an_empty_slide_are_left_out(): void
    {
        $this->clip(['video' => null]);
        $this->picture(['image' => null]);
        $this->assertCount(0, Hero::slides());

        $this->clip();
        $this->assertCount(1, Hero::slides());
        $this->assertTrue(Hero::slides()->first()->isVideo());
    }

    public function test_one_clip_alone_still_gets_the_script_and_a_pause_button_container(): void
    {
        $this->clip();
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('js/hero.js', $html);
        $this->assertStringContainsString('data-hero-controls', $html);
    }

    public function test_the_script_respects_less_motion_data_savers_pause_and_hidden_tabs(): void
    {
        $js = file_get_contents(public_path('js/hero.js'));
        foreach (['prefers-reduced-motion', 'saveData', 'syncVideos', 'document.hidden', 'visibilitychange', "removeAttribute('autoplay')"] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle);
        }
        $css = file_get_contents(public_path('css/site.css'));
        $this->assertStringContainsString('.hero__slide video { display: block; width: 100%; height: 100%; object-fit: cover;', $css);
    }

    public function test_the_sliders_screen_saves_a_clip_and_takes_a_removed_clip_off_the_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('hero/videos/old.mp4', 'x');
        Storage::disk('public')->put('hero/videos/new.mp4', 'y');
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $old = $this->clip(['video' => 'hero/videos/old.mp4']);

        Livewire::test(SlideViewerSettings::class)
            ->fillForm(['slides' => [['id' => null, 'media_type' => 'video', 'video' => ['hero/videos/new.mp4'], 'image' => [], 'image_alt' => ['en' => ''], 'is_active' => true]]])
            ->call('save')->assertHasNoFormErrors();

        $rows = HeroSlide::all();
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]->isVideo());
        $this->assertSame('hero/videos/new.mp4', $rows[0]->video);
        $this->assertNull($rows[0]->image);
        Storage::disk('public')->assertMissing('hero/videos/old.mp4');
        Storage::disk('public')->assertExists('hero/videos/new.mp4');
        $this->assertNull(HeroSlide::find($old->id));
    }

    public function test_a_clip_slide_needs_its_file_and_a_picture_slide_still_needs_its_picture_and_description(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(SlideViewerSettings::class)
            ->fillForm(['slides' => [['id' => null, 'media_type' => 'video', 'video' => [], 'image' => [], 'is_active' => true]]])
            ->call('save')->assertHasFormErrors();
        Livewire::test(SlideViewerSettings::class)
            ->fillForm(['slides' => [['id' => null, 'media_type' => 'image', 'image' => [], 'image_alt' => ['en' => 'x'], 'is_active' => true]]])
            ->call('save')->assertHasFormErrors();
        Livewire::test(SlideViewerSettings::class)
            ->fillForm(['slides' => [['id' => null, 'media_type' => 'image', 'image' => ['hero/p.webp'], 'image_alt' => ['en' => ''], 'is_active' => true]]])
            ->call('save')->assertHasFormErrors();
    }
}
