<?php

namespace Tests\Feature;

use App\Models\HeroMessage;
use App\Models\HeroSlide;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 16: the home banner can be a full-size gallery. */
class Step16Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        HeroMessage::create(['title' => ['en' => 'Hello'], 'is_active' => true]);
        HeroSlide::create(['image' => 'hero/a.webp', 'image_alt' => ['en' => 'A'], 'is_active' => true]);
    }

    public function test_usual_style_is_the_full_size_gallery(): void
    {
        $this->get('/')->assertOk()->assertSee('hero--full', false);
    }

    public function test_split_style_can_be_chosen(): void
    {
        Setting::put('hero_layout', 'split');
        $this->get('/')->assertOk()->assertDontSee('hero--full');
    }

    public function test_full_style_without_pictures_stays_plain(): void
    {
        HeroSlide::query()->delete();
        $this->get('/')->assertOk()->assertDontSee('hero--full');
    }
}
