<?php

namespace Tests\Feature;

use App\Models\HeroSlide;
use App\Support\Hero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 17: any picture is cut to the gallery shape by the site, at full quality. */
class Step17Test extends TestCase
{
    use RefreshDatabase;

    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $f) {
            @unlink($f);
        }
        array_map('unlink', glob(storage_path('app/public/hero/banner/9999*')) ?: []);
        parent::tearDown();
    }

    private function slide(int $w, int $h, array $extra = []): HeroSlide
    {
        @mkdir(storage_path('app/public/hero'), 0775, true);
        $name = 'hero/test-'.$w.'x'.$h.'.png';
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
        imagepng($img, storage_path('app/public/'.$name));
        $this->made[] = storage_path('app/public/'.$name);

        return HeroSlide::create(['id' => 9999, 'image' => $name, 'image_alt' => ['en' => 'x'], 'is_active' => true] + $extra);
    }

    public function test_a_16_9_picture_is_cut_to_2000_by_920_without_squeezing(): void
    {
        $url = Hero::bannerUrl($this->slide(1672, 941));
        $file = storage_path('app/public/'.explode('/storage/', $url)[1]);
        [$w, $h] = getimagesize($file);

        $this->assertSame(1672, $w, 'never made bigger than the picture');
        $this->assertEqualsWithDelta(2000 / 920, $w / $h, 0.01);
    }

    public function test_a_big_picture_comes_out_at_2000_by_920(): void
    {
        $url = Hero::bannerUrl($this->slide(3000, 2000));
        [$w, $h] = getimagesize(storage_path('app/public/'.explode('/storage/', $url)[1]));

        $this->assertSame([2000, 920], [$w, $h]);
    }

    public function test_a_missing_picture_falls_back_to_the_original(): void
    {
        $slide = HeroSlide::create(['image' => 'hero/nothing.png', 'image_alt' => ['en' => 'x'], 'is_active' => true]);

        $this->assertStringEndsWith('storage/hero/nothing.png', Hero::bannerUrl($slide));
    }

    public function test_the_focus_changes_the_copy(): void
    {
        $a = Hero::bannerUrl($this->slide(2000, 2000, ['custom_data' => ['focus' => 'top']]));
        $this->assertStringContainsString('-top.webp', $a);
    }
}
