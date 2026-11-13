<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Setting;
use App\Support\PictureOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Step 31: the tool that makes the pictures already on the site light. */
class Step31Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Storage::fake('public');
        Storage::fake('local');
    }

    /** A heavy picture put on the fake public disk. */
    private function heavy(string $path, int $w = 1800, int $h = 1100): string
    {
        $img = imagecreatetruecolor($w, $h);
        $sw = intdiv($w, 2);
        $sh = intdiv($h, 2);
        $seed = imagecreatetruecolor($sw, $sh);
        for ($x = 0; $x < $sw; $x++) {
            for ($y = 0; $y < $sh; $y++) {
                imagesetpixel($seed, $x, $y, (mt_rand(0, 255) << 16) | (mt_rand(0, 255) << 8) | mt_rand(0, 255));
            }
        }
        imagecopyresampled($img, $seed, 0, 0, 0, 0, $w, $h, $sw, $sh);
        ob_start();
        imagepng($img, null, 1);
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }

    public function test_a_trial_changes_nothing_and_says_what_would_happen(): void
    {
        $path = $this->heavy('categories/big.png');
        Category::create(['name' => ['en' => 'Faith'], 'slug' => 'faith', 'image' => $path]);
        $size = Storage::disk('public')->size($path);

        $result = PictureOptimizer::run(false);

        $this->assertFalse($result['applied']);
        $this->assertSame('would change', $result['files'][0]['status']);
        $this->assertLessThan($size, $result['files'][0]['after']);
        $this->assertGreaterThan(0, $result['saved']);
        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, Category::first()->image, 'the category still points at the old file');
        $this->assertSame([], Storage::disk('local')->allFiles('originals'));
    }

    public function test_apply_makes_a_light_webp_points_the_category_at_it_and_keeps_the_original_privately(): void
    {
        $path = $this->heavy('categories/big.png');
        $category = Category::create(['name' => ['en' => 'Faith'], 'slug' => 'faith', 'image' => $path]);
        $original = Storage::disk('public')->get($path);

        $result = PictureOptimizer::run(true);

        $new = $category->fresh()->image;
        $this->assertNotSame($path, $new);
        $this->assertStringStartsWith('categories/', $new);
        $this->assertStringEndsWith('.webp', $new);
        Storage::disk('public')->assertExists($new);
        $this->assertLessThanOrEqual(300 * 1024, Storage::disk('public')->size($new));
        Storage::disk('public')->assertMissing($path);
        $this->assertSame($original, Storage::disk('local')->get('originals/'.$path), 'the original is kept, outside the public folder');
        $this->assertSame('changed', $result['files'][0]['status']);
    }

    public function test_it_also_repoints_the_home_slider_and_the_logo(): void
    {
        $slide = HeroSlide::create(['image' => $this->heavy('hero/s1.png'), 'image_alt' => ['en' => 'x'], 'is_active' => true]);
        Setting::put('logo', $this->heavy('site/logo.png', 900, 400));

        PictureOptimizer::run(true);

        $this->assertStringEndsWith('.webp', $slide->fresh()->image);
        Setting::forget();
        $this->assertStringEndsWith('.webp', (string) Setting::get('logo'));
        Storage::disk('public')->assertExists($slide->fresh()->image);
    }

    public function test_two_things_that_use_one_picture_both_follow_it(): void
    {
        $path = $this->heavy('categories/shared.png');
        $a = Category::create(['name' => ['en' => 'A'], 'slug' => 'a', 'image' => $path]);
        $b = Category::create(['name' => ['en' => 'B'], 'slug' => 'b', 'image' => $path]);

        PictureOptimizer::run(true);

        $this->assertSame($a->fresh()->image, $b->fresh()->image);
        Storage::disk('public')->assertExists($a->fresh()->image);
    }

    public function test_the_main_picture_of_an_article_in_the_articles_folder_is_found_and_followed(): void
    {
        $path = $this->heavy('articles/main.png');
        $article = \App\Models\Article::create(['title' => ['en' => 'An article'], 'slug' => 'an-article', 'status' => 'published', 'publish_date' => now()->subDay(), 'main_image' => $path]);

        $result = PictureOptimizer::run(true);

        $this->assertSame([], $result['unused'], 'it is used, so it is not "unused"');
        $this->assertStringEndsWith('.webp', $article->fresh()->main_image);
        Storage::disk('public')->assertExists($article->fresh()->main_image);
    }

    public function test_a_picture_that_is_light_already_and_one_that_nothing_uses_are_left_alone(): void
    {
        $used = 'categories/light.webp';
        $img = imagecreatetruecolor(400, 300);
        ob_start();
        imagewebp($img, null, 80);
        Storage::disk('public')->put($used, ob_get_clean());
        Category::create(['name' => ['en' => 'Light'], 'slug' => 'light', 'image' => $used]);
        $orphan = $this->heavy('categories/orphan.png');

        $result = PictureOptimizer::run(true);

        $this->assertSame('kept', $result['files'][0]['status']);
        $this->assertSame($used, Category::first()->image);
        Storage::disk('public')->assertExists($orphan);
        $this->assertSame([$orphan], $result['unused'], 'counted, never touched');
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        Category::create(['name' => ['en' => 'Faith'], 'slug' => 'faith', 'image' => $this->heavy('categories/big.png')]);

        PictureOptimizer::run(true);
        $after = Category::first()->image;
        $second = PictureOptimizer::run(true);

        $this->assertSame($after, Category::first()->image);
        $this->assertSame('kept', $second['files'][0]['status']);
        $this->assertSame(0, $second['saved']);
    }

    public function test_the_stories_folder_is_never_touched(): void
    {
        $story = $this->heavy('stories/s.png');

        PictureOptimizer::run(true);

        Storage::disk('public')->assertExists($story);
    }

    public function test_the_command_shows_a_trial_and_applies_only_when_asked(): void
    {
        $path = $this->heavy('categories/big.png');
        Category::create(['name' => ['en' => 'Faith'], 'slug' => 'faith', 'image' => $path]);

        $this->artisan('pictures:optimize')->expectsOutputToContain('a trial: nothing is changed')->expectsOutputToContain('Run it again with --apply')->assertSuccessful();
        $this->assertSame($path, Category::first()->image);

        $this->artisan('pictures:optimize', ['--apply' => true])->expectsOutputToContain('Saved')->assertSuccessful();
        $this->assertNotSame($path, Category::first()->image);
    }
}
