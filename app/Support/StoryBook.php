<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Setting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/** Makes an article with story slides into a PDF book: cover, contents, a page for each chapter, and a page for each slide. */
class StoryBook
{
    public static function pdf(Article $article): string
    {
        $article->unsetRelation('chapters');
        $article->load('chapters.slides');

        $chapters = $article->chapters->filter(fn ($c) => $c->slides->isNotEmpty())->values();
        $font = static::salutationFont();
        $html = view('story.book', [
            'font' => $font ? str_replace('\\', '/', $font) : null,
            'story' => $article,
            'chapters' => $chapters,
            'siteName' => Setting::get('site_name', config('app.name')),
            'siteUrl' => rtrim((string) config('app.url'), '/'),
            'logo' => static::data(Setting::get('logo')),
            'layout' => static::layout(),
            'coverPath' => $article->main_image,
            'cover' => static::data($article->main_image),
            'defaultCredit' => Setting::get('story_default_credit', 'AI Generated'),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', [base_path()]);
        $options->set('fontDir', storage_path('app/dompdf'));
        $options->set('fontCache', storage_path('app/dompdf'));
        @mkdir(storage_path('app/dompdf'), 0775, true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        // the page number, from the second page on
        $canvas = $dompdf->getCanvas();
        $numberFont = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_script(function ($number, $count, $c) use ($numberFont) {
            if ($number > 1 && $number < $count) {
                $c->text(790, 570, (string) $number, $numberFont, 9, [0.55, 0.55, 0.55]);
            }
        });

        return $dompdf->output();
    }

    /** The layout of the book: full (a picture fills the whole page, the text on a panel over it) or side (picture beside the text). */
    public static function layout(): string
    {
        return (string) Setting::get('story_book_layout', 'full') === 'side' ? 'side' : 'full';
    }

    public static function enabled(): bool
    {
        return (string) Setting::get('story_pdf_on', '1') === '1';
    }

    /** A picture from the public disk as a JPEG inside the page. With $ratio the picture is cut from the middle to that shape (a whole page: [842, 595]). */
    public static function data(?string $path, ?array $ratio = null, int $maxWidth = 1000): ?string
    {
        if (blank($path) || ! Storage::disk('public')->exists($path) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $im = @imagecreatefromstring(Storage::disk('public')->get($path));
        if ($im === false) {
            return null;
        }

        $w = imagesx($im);
        $h = imagesy($im);
        if ($ratio) {
            [$rw, $rh] = $ratio;
            if ($w * $rh > $h * $rw) {             // too wide: cut the sides
                $newW = (int) round($h * $rw / $rh);
                $crop = imagecrop($im, ['x' => (int) floor(($w - $newW) / 2), 'y' => 0, 'width' => $newW, 'height' => $h]);
            } else {                               // too tall: cut the top and the bottom
                $newH = (int) round($w * $rh / $rw);
                $crop = imagecrop($im, ['x' => 0, 'y' => (int) floor(($h - $newH) / 2), 'width' => $w, 'height' => $newH]);
            }
            if ($crop !== false) {
                $im = $crop;
                $w = imagesx($im);
            }
        }
        if ($w > $maxWidth) {
            $scaled = imagescale($im, $maxWidth);
            if ($scaled !== false) {
                $im = $scaled;
            }
        }

        // white behind transparent parts, then a JPEG: small and understood by the PDF maker
        $flat = imagecreatetruecolor(imagesx($im), imagesy($im));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));

        ob_start();
        imagejpeg($flat, null, 82);
        $jpeg = ob_get_clean();

        return $jpeg ? 'data:image/jpeg;base64,'.base64_encode($jpeg) : null;
    }

    /** The file of the font that has the sign for "peace be upon him" (the standard PDF fonts do not have it). */
    public static function salutationFont(): ?string
    {
        foreach (['Amiri-Regular.ttf', 'NotoNaskhArabic-Regular.ttf'] as $name) {
            $path = resource_path('fonts/'.$name);
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Safe text for the book. The sign for the Prophet (peace be upon him) uses its own font; without that font it is written in words. */
    public static function t(?string $text): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(static::sign(e((string) $text)));
    }

    private static function sign(string $html): string
    {
        if (! str_contains($html, "\u{FDFA}")) {
            return $html;
        }

        return static::salutationFont() ? str_replace("\u{FDFA}", '<span class="sal">'."\u{FDFA}".'</span>', $html) : str_replace("\u{FDFA}", '(peace be upon him)', $html);
    }

    /** The text of a slide (plain words, a blank line between paragraphs) as safe paragraphs. */
    public static function body(?string $text): string
    {
        return static::sign(Html::fromPlain($text));
    }
}
