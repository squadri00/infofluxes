<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\ImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Step 30: every picture you upload is saved as a WebP that is no heavier than the set size (usually 300 KB). */
class Step30Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Storage::fake('public');
    }

    /** A picture full of detail, like a real photo: random colours at half the size, blown up smoothly. A lossless file of it is several megabytes. */
    private function photo(int $w, int $h, string $type = 'png'): UploadedFile
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

        return $this->file($img, $type);
    }

    /** A calm picture (a soft gradient), which is light even at a large size. */
    private function gradient(int $w, int $h, string $type = 'png'): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        for ($x = 0; $x < $w; $x += 4) {
            imagefilledrectangle($img, $x, 0, $x + 3, $h, imagecolorallocate($img, intdiv($x * 255, $w), 120, 255 - intdiv($x * 255, $w)));
        }

        return $this->file($img, $type);
    }

    private function file(\GdImage $img, string $type): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.'.$type;
        match ($type) { 'png' => imagepng($img, $path, 1), 'jpg' => imagejpeg($img, $path, 98), 'gif' => imagegif($img, $path), 'webp' => imagewebp($img, $path, 100) };

        return new UploadedFile($path, 'photo.'.$type, null, null, true);
    }

    private function info(string $path): array
    {
        return getimagesizefromstring(Storage::disk('public')->get($path));
    }

    // ---------------- every kind of picture becomes a light WebP ----------------

    public function test_a_heavy_photo_becomes_a_webp_of_300_kb_or_less(): void
    {
        $file = $this->photo(3200, 2000, 'png');
        $before = filesize($file->getRealPath());

        $path = ImageStore::store($file, 'categories');

        $this->assertStringStartsWith('categories/', $path);
        $this->assertStringEndsWith('.webp', $path);
        $info = $this->info($path);
        $this->assertSame('image/webp', $info['mime']);
        $this->assertLessThanOrEqual(300 * 1024, Storage::disk('public')->size($path), 'no heavier than 300 KB');
        $this->assertGreaterThan(1_000_000, $before, 'the original was several megabytes');
        $this->assertLessThanOrEqual(2400, $info[0]);
        $this->assertEqualsWithDelta(3200 / 2000, $info[0] / $info[1], 0.01, 'the same shape, never squeezed');
    }

    public function test_a_jpg_a_png_and_a_still_gif_all_become_webp(): void
    {
        foreach (['jpg', 'png', 'gif'] as $type) {
            $path = ImageStore::store($this->gradient(900, 600, $type), 'people');

            $this->assertStringEndsWith('.webp', $path, $type);
            $this->assertSame('image/webp', $this->info($path)['mime'], $type);
        }
    }

    public function test_a_calm_picture_keeps_its_width_because_it_fits_already(): void
    {
        $path = ImageStore::store($this->gradient(3200, 2000, 'png'), 'hero');

        $info = $this->info($path);
        $this->assertSame(2400, $info[0], 'only made narrower to the widest allowed');
        $this->assertSame(1500, $info[1]);
        $this->assertLessThanOrEqual(300 * 1024, Storage::disk('public')->size($path));
    }

    public function test_a_small_picture_is_never_made_bigger(): void
    {
        $info = $this->info(ImageStore::store($this->gradient(800, 500, 'png'), 'people'));

        $this->assertSame([800, 500], [$info[0], $info[1]]);
    }

    public function test_the_quality_goes_down_before_the_picture_is_made_narrower(): void
    {
        // A busy picture of 1200 px wide that is a little too heavy at the best quality: lower quality alone brings it under the limit.
        $file = $this->photo(1200, 800, 'png');
        $best = ImageStore::lighten((string) file_get_contents($file->getRealPath()), 10 * 1024 * 1024);
        $limit = (int) (strlen($best) * 0.8);

        $light = ImageStore::lighten((string) file_get_contents($file->getRealPath()), $limit);

        $this->assertLessThanOrEqual($limit, strlen($light));
        $this->assertSame(1200, getimagesizefromstring($light)[0], 'the width stayed: only the quality went down');
    }

    public function test_the_picture_is_made_narrower_only_when_quality_is_not_enough(): void
    {
        $file = $this->photo(1600, 1000, 'png');

        $light = ImageStore::lighten((string) file_get_contents($file->getRealPath()), 150 * 1024);

        $this->assertLessThan(1600, getimagesizefromstring($light)[0]);
        $this->assertLessThanOrEqual(150 * 1024, strlen($light));
        $this->assertEqualsWithDelta(1.6, getimagesizefromstring($light)[0] / getimagesizefromstring($light)[1], 0.01, 'the same shape');
    }

    public function test_when_even_the_narrowest_picture_cannot_fit_the_lightest_one_is_still_used(): void
    {
        $file = $this->photo(1600, 1000, 'png');

        $light = ImageStore::lighten((string) file_get_contents($file->getRealPath()), 1024);

        $this->assertNotNull($light);
        $this->assertSame('image/webp', getimagesizefromstring($light)['mime']);
        $this->assertGreaterThanOrEqual(640, getimagesizefromstring($light)[0], 'never made tiny');
    }

    public function test_the_size_limit_is_a_setting(): void
    {
        $this->assertSame(300 * 1024, ImageStore::maxBytes());

        Setting::put('image_max_kb', '100');
        $path = ImageStore::store($this->photo(1600, 1000, 'png'), 'facts');
        $this->assertLessThanOrEqual(100 * 1024, Storage::disk('public')->size($path));
    }

    public function test_the_limits_have_safe_defaults_and_edges(): void
    {
        $this->assertSame([12288, 307200, 2400, 85], [ImageStore::maxKb(), ImageStore::maxBytes(), ImageStore::maxWidth(), ImageStore::quality()]);

        Setting::put('upload_max_mb', '99');
        Setting::put('image_max_kb', '1');
        Setting::put('image_max_width', '10');
        Setting::put('image_quality', '5');
        $this->assertSame([12288, 307200, 2400, 85], [ImageStore::maxKb(), ImageStore::maxBytes(), ImageStore::maxWidth(), ImageStore::quality()], 'nonsense falls back to the usual numbers');

        Setting::put('upload_max_mb', '5');
        Setting::put('image_max_kb', '99999');
        Setting::put('image_max_width', '9000');
        $this->assertSame([5120, 1500 * 1024, 6000], [ImageStore::maxKb(), ImageStore::maxBytes(), ImageStore::maxWidth()]);
    }

    // ---------------- what is kept as it is ----------------

    public function test_a_webp_that_is_light_and_narrow_enough_is_kept_untouched(): void
    {
        $file = $this->gradient(600, 400, 'webp');
        $original = (string) file_get_contents($file->getRealPath());

        $this->assertNull(ImageStore::lighten($original), 'nothing to gain, and saving it again would lose quality');
        $path = ImageStore::store($file, 'site');
        $this->assertSame($original, Storage::disk('public')->get($path), 'byte for byte');
    }

    public function test_a_heavy_webp_is_made_lighter_too(): void
    {
        $heavy = $this->photo(2000, 1300, 'webp');
        $this->assertGreaterThan(300 * 1024, filesize($heavy->getRealPath()));

        $path = ImageStore::store($heavy, 'hero');

        $this->assertLessThanOrEqual(300 * 1024, Storage::disk('public')->size($path));
    }

    public function test_a_transparent_png_keeps_its_transparency(): void
    {
        $img = imagecreatetruecolor(300, 300);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagefilledellipse($img, 150, 150, 200, 200, imagecolorallocate($img, 200, 30, 30));
        $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($img, $path);

        $saved = ImageStore::store(new UploadedFile($path, 'logo.png', null, null, true), 'site');

        $webp = imagecreatefromstring(Storage::disk('public')->get($saved));
        $corner = imagecolorsforindex($webp, imagecolorat($webp, 2, 2));
        $this->assertGreaterThan(100, $corner['alpha'], 'the corner is still see-through');
    }

    public function test_an_animated_gif_is_kept_as_it_is(): void
    {
        $gif = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF"
            ."\x21\xF9\x04\x00\x0A\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00"
            ."\x21\xF9\x04\x00\x0A\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B";
        $path = tempnam(sys_get_temp_dir(), 'anim').'.gif';
        file_put_contents($path, $gif);

        $this->assertNull(ImageStore::lighten($gif), 'two frames: left alone');
        $saved = ImageStore::store(new UploadedFile($path, 'anim.gif', null, null, true), 'facts');
        $this->assertStringEndsWith('.gif', $saved);
        $this->assertSame($gif, Storage::disk('public')->get($saved), 'byte for byte');
    }

    public function test_something_that_is_not_a_picture_is_never_turned_into_one(): void
    {
        $this->assertNull(ImageStore::lighten('<?php echo 1; ?>'));
        $this->assertNull(ImageStore::lighten('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'));
    }

    // ---------------- the upload boxes ----------------

    public function test_every_picture_box_uses_the_same_helper_and_the_same_limit(): void
    {
        $files = [
            'app/Filament/Pages/SiteSettings.php' => "ImageUpload::make('logo', 'site')",
            'app/Filament/Pages/SlideViewerSettings.php' => "ImageUpload::make('image', 'hero')",
            'app/Filament/Resources/People/PersonResource.php' => "ImageUpload::make('photo', 'people')",
            'app/Filament/Resources/DidYouKnow/DidYouKnowResource.php' => "ImageUpload::make('image', 'facts')",
            'app/Filament/Resources/Categories/CategoryResource.php' => "ImageUpload::make('image', 'categories')",
            'app/Filament/Resources/Articles/ArticleResource.php' => "ImageUpload::make('image', 'articles')",
        ];
        foreach ($files as $file => $call) {
            $source = (string) file_get_contents(base_path($file));
            $this->assertStringContainsString($call, $source, $file);
            $this->assertStringNotContainsString('maxSize(3072)', $source, $file);
            $this->assertStringNotContainsString('maxSize(5120)', $source, $file);
        }

        $articles = (string) file_get_contents(base_path('app/Filament/Resources/Articles/ArticleResource.php'));
        $this->assertSame(2, substr_count($articles, 'ImageStore::maxKb()'), 'the main picture and the story slide picture share the limit');
        $this->assertStringNotContainsString('maxSize(10240)', $articles);
    }

    public function test_the_upload_boxes_accept_only_normal_picture_types(): void
    {
        $this->assertSame(['image/jpeg', 'image/png', 'image/webp', 'image/gif'], ImageStore::TYPES);
        $this->assertNotContains('image/svg+xml', ImageStore::TYPES);
    }

    public function test_the_helper_text_of_a_box_tells_the_size_rules(): void
    {
        $source = (string) file_get_contents(base_path('app/Filament/Support/ImageUpload.php'));

        $this->assertStringContainsString('ImageStore::maxBytes()', $source, 'the text names the real limit, not a number typed in by hand');
    }
}
