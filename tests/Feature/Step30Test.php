<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\ImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Step 30: every picture you upload is saved lighter (a WebP, no wider than the set width), up to a clear size. */
class Step30Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Storage::fake('public');
    }

    /** A picture with noise, so a PNG of it is big (like a real photo). */
    private function photo(int $w, int $h, string $type = 'png'): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        // Like a real photo: detail everywhere (random colours at half the size, blown up smoothly), so a lossless file of it is several megabytes.
        $sw = intdiv($w, 2);
        $sh = intdiv($h, 2);
        $seed = imagecreatetruecolor($sw, $sh);
        for ($x = 0; $x < $sw; $x++) {
            for ($y = 0; $y < $sh; $y++) {
                imagesetpixel($seed, $x, $y, (mt_rand(0, 255) << 16) | (mt_rand(0, 255) << 8) | mt_rand(0, 255));
            }
        }
        imagecopyresampled($img, $seed, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imagedestroy($seed);
        $path = tempnam(sys_get_temp_dir(), 'img').'.'.$type;
        match ($type) { 'png' => imagepng($img, $path, 1), 'jpg' => imagejpeg($img, $path, 98), 'gif' => imagegif($img, $path) };
        imagedestroy($img);

        return new UploadedFile($path, 'photo.'.$type, null, null, true);
    }

    public function test_a_big_picture_is_saved_as_a_much_lighter_webp_no_wider_than_the_limit(): void
    {
        $file = $this->photo(3200, 2000, 'png');
        $before = filesize($file->getRealPath());

        $path = ImageStore::store($file, 'categories');

        $this->assertStringStartsWith('categories/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        $info = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame('image/webp', $info['mime']);
        $this->assertSame(2400, $info[0], 'made narrower');
        $this->assertSame(1500, $info[1], 'in the same shape: 3200 x 2000 became 2400 x 1500');
        $this->assertLessThan($before / 2, Storage::disk('public')->size($path), 'much lighter than the original');
    }

    public function test_a_small_picture_is_never_made_bigger(): void
    {
        $path = ImageStore::store($this->photo(800, 500, 'png'), 'people');

        $info = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([800, 500], [$info[0], $info[1]]);
    }

    public function test_the_widest_saved_picture_is_a_setting(): void
    {
        Setting::put('image_max_width', '1000');

        $path = ImageStore::store($this->photo(2000, 1000, 'jpg'), 'hero');

        $this->assertSame(1000, getimagesizefromstring(Storage::disk('public')->get($path))[0]);
    }

    public function test_the_limits_have_safe_defaults_and_edges(): void
    {
        $this->assertSame([12288, 2400, 85], [ImageStore::maxKb(), ImageStore::maxWidth(), ImageStore::quality()]);

        Setting::put('upload_max_mb', '99');
        Setting::put('image_max_width', '10');
        Setting::put('image_quality', '5');
        $this->assertSame([12288, 2400, 85], [ImageStore::maxKb(), ImageStore::maxWidth(), ImageStore::quality()], 'nonsense falls back to the usual numbers');

        Setting::put('upload_max_mb', '5');
        Setting::put('image_max_width', '9000');
        $this->assertSame([5120, 6000], [ImageStore::maxKb(), ImageStore::maxWidth()]);
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

    public function test_a_picture_that_would_not_get_lighter_is_kept_as_it_is(): void
    {
        // A WebP that was already saved very light: saving it again at the usual quality would only make it heavier.
        $img = imagecreatetruecolor(64, 64);
        for ($i = 0; $i < 200; $i++) {
            imagefilledrectangle($img, random_int(0, 64), random_int(0, 64), random_int(0, 64), random_int(0, 64), imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagewebp($img, null, 1);
        $webp = (string) ob_get_clean();

        $this->assertNull(ImageStore::lighten($webp), 'not smaller: the original stays');
    }

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

    public function test_the_upload_box_helper_accepts_only_normal_picture_types(): void
    {
        $this->assertSame(['image/jpeg', 'image/png', 'image/webp', 'image/gif'], ImageStore::TYPES);
        $this->assertNotContains('image/svg+xml', ImageStore::TYPES);
    }
}
