<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Support\StoryMaker\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 76: the gallery "Stories in" row and filter, and the right-to-left rules of the slide viewer. */
class Step76Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function story(string $slug, string $title, ?string $lang = null): Story
    {
        $cat = Category::firstOrCreate(['slug' => 'stories'], ['name' => ['en' => 'Stories']]);
        $story = Story::create(['title' => ['en' => $title], 'slug' => $slug, 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'Short.']]);
        if ($lang) {
            $story->setTranslation('title', $lang, "[{$lang}] ".$title);
            $story->save();
            Translations::saved($story->fresh(), $lang, null);
            Translations::set($story->fresh(), $lang, 'on');
        }

        return $story->fresh();
    }

    public function test_no_language_row_when_no_story_has_a_language_on(): void
    {
        $this->story('one', 'One');
        $this->get('/stories')->assertOk()->assertDontSee('story-langs', false);
    }

    public function test_the_gallery_lists_only_languages_that_are_on_and_filters_by_them(): void
    {
        $this->story('plain', 'Plain Story');
        $this->story('urdu', 'Urdu Story', 'ur');

        $all = $this->get('/stories')->assertOk()->getContent();
        $this->assertStringContainsString('class="story-langs"', $all);
        $this->assertStringContainsString('?lang=ur', $all);
        $this->assertStringNotContainsString('?lang=fr', $all);
        $this->assertStringContainsString('Plain Story', $all);

        $ur = $this->get('/stories?lang=ur')->assertOk()->getContent();
        $this->assertStringContainsString('[ur] Urdu Story', $ur);
        $this->assertStringNotContainsString('Plain Story', $ur);
        $this->assertStringContainsString(url('/ur/stories/urdu'), $ur, 'cards link to the story in that language');
        $this->assertStringContainsString('dir="rtl"', $ur);
        $this->assertStringContainsString('<html lang="en" dir="ltr">', $ur);
    }

    public function test_a_language_nobody_has_switched_on_is_ignored(): void
    {
        $this->story('urdu', 'Urdu Story', 'ur');
        $this->get('/stories?lang=fr')->assertOk()->assertSee('Urdu Story');
    }

    public function test_the_slide_viewer_keeps_its_sliding_direction_in_right_to_left(): void
    {
        $css = file_get_contents(public_path('css/site.css'));
        $this->assertStringContainsString('.topic[dir="rtl"] .story__track { direction: ltr; }', $css);
        $this->assertStringContainsString('.topic[dir="rtl"] .slide__text', $css);
    }

    public function test_every_language_has_the_stories_in_words(): void
    {
        foreach (['en', 'ur', 'ar', 'fr', 'es', 'hi'] as $code) {
            $this->assertNotSame('', (string) (require base_path("lang/{$code}/site.php"))['stories_in'], $code);
        }
    }
    public function test_the_story_fonts_are_on_the_site_and_in_the_style_sheet(): void
    {
        $css = file_get_contents(public_path("css/site.css"));
        foreach (["noto-nastaliq-urdu", "amiri-400", "amiri-700", "noto-sans-devanagari"] as $name) {
            $this->assertFileExists(public_path("fonts/story/{$name}.woff2"));
            $this->assertStringContainsString("fonts/story/{$name}.woff2", $css);
        }
        $this->assertFileExists(public_path("fonts/story/LICENSE.txt"));
    }
}
