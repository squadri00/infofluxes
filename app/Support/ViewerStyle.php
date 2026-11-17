<?php

namespace App\Support;

use App\Models\Setting;

/**
 * How the slide viewer (the gallery of a topic with slides) looks and behaves. Every choice is a Setting in the group
 * "Slide viewer", so nothing is fixed in the code. The defaults give the same look as before.
 * The viewer gets a few classes, a few CSS variables and a few data-attributes (read by public/js/story.js).
 */
class ViewerStyle
{
    public const GROUP = 'viewer';

    /** key => [label, type, default, options (for a choice)] */
    public const SETTINGS = [
        // ---- size and place
        'viewer_width' => ['Width of the viewer', 'select', 'full', ['full' => 'The whole width of the window', 'boxed' => 'A wide box in the middle (see "Widest box")', 'page' => 'As wide as the page text']],
        'viewer_box_width' => ['Widest box, in pixels (used when the width is "A wide box")', 'number', '1300', null],
        'viewer_height_mode' => ['Height of the viewer', 'select', 'screen', ['screen' => 'Follows the height of the screen', 'fixed' => 'A fixed number of pixels', 'auto' => 'As tall as the slide needs']],
        'viewer_height_offset' => ['Follows the screen: leave this many pixels free above and below (more pixels = a lower viewer)', 'number', '300', null],
        'viewer_height_min' => ['Lowest height, in pixels (follows the screen)', 'number', '460', null],
        'viewer_height_max' => ['Highest height, in pixels (follows the screen)', 'number', '820', null],
        'viewer_height_fixed' => ['Fixed height, in pixels', 'number', '600', null],
        // ---- the picture
        'viewer_picture_side' => ['Where the picture is', 'select', 'left', ['left' => 'On the left', 'right' => 'On the right', 'top' => 'On top, the text below', 'none' => 'No picture, only the text']],
        'viewer_picture_ratio' => ['Shape of the picture frame (with 16:9 or 4:3 the viewer is as tall as its picture needs, so a 16:9 picture fits as it is)', 'select', '16:9', ['16:9' => '16:9: a 1920 x 1080 picture fits as it is', '4:3' => '4:3', 'fill' => 'Fill the space (the edges may be cut)']],
        'viewer_picture_share' => ['How much of the viewer the picture takes, in percent (30 to 75)', 'number', '57', null],
        'viewer_picture_fit' => ['How the picture fills its box', 'select', 'cover', ['cover' => 'Fill the box (the edges may be cut)', 'contain' => 'Show the whole picture (empty space may show)']],
        'viewer_caption' => ['Show the picture description and credit on the picture', 'toggle', '1', null],
        'viewer_mark_size' => ['Logo mark on the picture: height in pixels (see also the watermark settings)', 'number', '64', null],
        'viewer_mark_opacity' => ['Logo mark on the picture: strength, in percent (10 to 100)', 'number', '80', null],
        // ---- the text
        'viewer_text_size' => ['Size of the slide text', 'select', '100', ['85' => 'Small', '100' => 'Normal', '115' => 'Large', '130' => 'Extra large', '150' => 'Huge']],
        'viewer_text_align' => ['How the slide text lines up', 'select', 'left', ['left' => 'To the left', 'justify' => 'Even on both sides', 'center' => 'In the middle']],
        'viewer_text_valign' => ['Where the text sits in its box', 'select', 'top', ['top' => 'At the top', 'center' => 'In the middle']],
        'viewer_padding' => ['Space around the text', 'select', 'normal', ['compact' => 'Small', 'normal' => 'Normal', 'roomy' => 'Large']],
        'viewer_cover_contents' => ['Show the contents list (chapters and slides) on the cover', 'toggle', '1', null],
        // ---- the frame
        'viewer_background' => ['Background of the viewer', 'select', 'surface', ['surface' => 'Like the cards of the site', 'dark' => 'Dark', 'light' => 'Light', 'transparent' => 'None (the page shows through)']],
        'viewer_border' => ['A thin line around the viewer', 'toggle', '1', null],
        'viewer_shadow' => ['A soft shadow under the viewer', 'toggle', '0', null],
        'viewer_radius' => ['Round corners, in pixels (0 = the look of the site)', 'number', '0', null],
        // ---- moving
        'viewer_transition' => ['How one slide gives way to the next', 'select', 'glide', ['glide' => 'Glides sideways', 'fade' => 'Fades', 'none' => 'Changes at once', 'book' => 'Turns like the page of an open book']],
        'viewer_speed' => ['Speed of the change, in milliseconds (100 to 2000)', 'number', '550', null],
        'viewer_autoplay' => ['Move to the next slide by itself every this many seconds (0 = never)', 'number', '0', null],
        'viewer_pause_hover' => ['Stop moving by itself while the mouse is over the viewer', 'toggle', '1', null],
        'viewer_loop' => ['After the last slide go back to the first', 'toggle', '0', null],
        'viewer_keys' => ['The left and right arrow keys change the slide', 'toggle', '1', null],
        'viewer_swipe' => ['A swipe on a phone changes the slide', 'toggle', '1', null],
        // ---- controls
        'viewer_arrows' => ['Show the round arrows at the sides', 'toggle', '1', null],
        'viewer_arrow_size' => ['Size of the arrows, in pixels', 'number', '48', null],
        'viewer_arrow_color' => ['Colour of the arrows', 'select', 'dark', ['dark' => 'Dark', 'accent' => 'The colour of the site', 'light' => 'Light']],
        'viewer_dots' => ['Show the dots', 'toggle', '1', null],
        'viewer_dots_place' => ['Where the dots are', 'select', 'below', ['below' => 'Under the viewer', 'inside' => 'On the bottom of the viewer']],
        'viewer_counter' => ['Show the number of the slide ("3 / 10")', 'toggle', '1', null],
        'viewer_home_button' => ['Show the Home button (back to the cover, the first page) before the chapter buttons', 'toggle', '1', null],
        'viewer_chapter_style' => ['Chapter buttons above the viewer', 'select', 'auto', ['auto' => 'Automatic: round buttons for a few short chapter names, one "Chapters" menu for many or long names', 'pills' => 'Always round buttons', 'menu' => 'Always one "Chapters" menu']],
        'viewer_chapter_pills_max' => ['Automatic: the most chapters that still get round buttons (more chapters make the menu)', 'number', '4', null],
        'viewer_chapter_title_max' => ['Automatic: the longest chapter name, in letters, that still gets a round button (a longer name makes the menu)', 'number', '25', null],
        'viewer_contents_numbers' => ['Number the chapters in the contents list on the cover (1, 2, 3...). Off: only the names show.', 'toggle', '0', null],
        'viewer_slide_chapter_label' => ['Show the chapter name in small letters above the heading of each slide', 'toggle', '1', null],
        'viewer_chapter_buttons' => ['Show the chapter buttons above the viewer', 'toggle', '1', null],
        // ---- phones
        'viewer_mobile_picture' => ['On a phone: the picture', 'select', 'top', ['top' => 'On top of the text', 'none' => 'Hidden, only the text']],
        'viewer_mobile_picture_height' => ['On a phone: height of the picture, in percent of the screen (15 to 70)', 'number', '38', null],
    ];

    private static function v(string $key): string
    {
        return (string) Setting::get($key, static::SETTINGS[$key][2]);
    }

    private static function n(string $key, int $min, int $max): int
    {
        return min($max, max($min, (int) static::v($key)));
    }

    private static function on(string $key): bool
    {
        return static::v($key) === '1';
    }

    /** The classes of the viewer. */
    public static function classes(): string
    {
        $c = ['viewer', 'viewer--pic-'.static::choice('viewer_picture_side'), 'viewer--'.static::choice('viewer_transition'), 'viewer--h-'.static::choice('viewer_height_mode'),
            'viewer--text-'.static::choice('viewer_text_align'), 'viewer--valign-'.static::choice('viewer_text_valign'), 'viewer--dots-'.static::choice('viewer_dots_place'), 'viewer--mpic-'.static::choice('viewer_mobile_picture')];
        if (static::on('viewer_contents_numbers')) {
            $c[] = 'viewer--numbers';
        }
        $c[] = 'viewer--ratio-'.str_replace(':', '-', static::choice('viewer_picture_ratio'));
        foreach (['viewer_arrows' => 'arrows', 'viewer_dots' => 'dots', 'viewer_counter' => 'counter', 'viewer_chapter_buttons' => 'chips', 'viewer_home_button' => 'home', 'viewer_caption' => 'caption', 'viewer_slide_chapter_label' => 'chlabel'] as $key => $name) {
            if (! static::on($key)) {
                $c[] = 'viewer--no-'.$name;
            }
        }

        return implode(' ', $c);
    }

    /** A choice from a list; anything not in the list falls back to the default. */
    private static function choice(string $key): string
    {
        $value = static::v($key);

        return array_key_exists($value, static::SETTINGS[$key][3] ?? []) ? $value : (string) static::SETTINGS[$key][2];
    }

    /** The CSS variables of the viewer. */
    public static function style(): string
    {
        $height = match (static::choice('viewer_height_mode')) {
            'fixed' => static::n('viewer_height_fixed', 240, 2000).'px',
            'auto' => 'auto',
            default => 'clamp('.static::n('viewer_height_min', 240, 1200).'px, calc(100vh - '.static::n('viewer_height_offset', 0, 900).'px), '.max(static::n('viewer_height_min', 240, 1200), static::n('viewer_height_max', 300, 2000)).'px)',
        };
        $padding = ['compact' => 'clamp(12px, 1.6vw, 22px)', 'normal' => 'clamp(18px, 2.6vw, 40px)', 'roomy' => 'clamp(26px, 3.6vw, 60px)'][static::choice('viewer_padding')];
        $background = ['surface' => 'var(--surface)', 'dark' => '#14181f', 'light' => '#ffffff', 'transparent' => 'transparent'][static::choice('viewer_background')];
        $foreground = ['dark' => '#f1f3f6', 'light' => '#1e2733'][static::choice('viewer_background')] ?? 'inherit';
        $radius = static::n('viewer_radius', 0, 60);
        $arrow = [
            'dark' => ['rgba(20, 24, 32, .62)', '#ffffff'], 'accent' => ['var(--accent)', 'var(--accent-text, #ffffff)'], 'light' => ['rgba(255, 255, 255, .88)', '#1e2733'],
        ][static::choice('viewer_arrow_color')];

        $vars = [
            '--v-h' => $height,
            '--v-share' => static::n('viewer_picture_share', 30, 75).'%',
            '--v-fit' => static::choice('viewer_picture_fit'),
            '--v-font' => (string) (static::n('viewer_text_size', 70, 200) / 100),
            '--v-pad' => $padding,
            '--v-bg' => $background,
            '--v-fg' => $foreground,
            '--v-radius' => $radius > 0 ? $radius.'px' : 'var(--radius)',
            '--v-border' => static::on('viewer_border') ? '1px solid var(--line)' : '0',
            '--v-shadow' => static::on('viewer_shadow') ? '0 10px 30px rgba(0, 0, 0, .28)' : 'none',
            '--v-speed' => static::n('viewer_speed', 100, 2000).'ms',
            '--v-arrow' => static::n('viewer_arrow_size', 28, 96).'px',
            '--v-arrow-bg' => $arrow[0],
            '--v-arrow-fg' => $arrow[1],
            '--v-mark' => static::n('viewer_mark_size', 16, 200).'px',
            '--v-mark-opacity' => (string) (static::n('viewer_mark_opacity', 10, 100) / 100),
            '--v-mpic' => static::n('viewer_mobile_picture_height', 15, 70).'vh',
        ];

        return implode('; ', array_map(fn ($k, $v) => $k.': '.$v, array_keys($vars), $vars));
    }

    /** The data-attributes that story.js reads. */
    public static function data(): string
    {
        $pairs = [
            'data-autoplay' => static::n('viewer_autoplay', 0, 120),
            'data-pause-hover' => static::on('viewer_pause_hover') ? 1 : 0,
            'data-loop' => static::on('viewer_loop') ? 1 : 0,
            'data-keys' => static::on('viewer_keys') ? 1 : 0,
            'data-swipe' => static::on('viewer_swipe') ? 1 : 0,
            'data-transition' => static::choice('viewer_transition'),
        ];

        return implode(' ', array_map(fn ($k, $v) => $k.'="'.$v.'"', array_keys($pairs), $pairs));
    }

    /** The class for the outer element: how wide the viewer is. */
    public static function widthClass(bool $embedded): string
    {
        return match (static::choice('viewer_width')) {
            'boxed' => $embedded ? 'story--embedded story--boxed' : 'story--boxed',
            'page' => 'story--page',
            default => $embedded ? 'story--wide story--embedded' : 'story--wide',
        };
    }

    public static function boxWidth(): int
    {
        return static::n('viewer_box_width', 600, 2400);
    }

    /** One "Chapters" menu instead of round buttons? Decided by the Setting, and in automatic mode by how many chapters there are and how long their names are. */
    public static function chaptersAsMenu(\Illuminate\Support\Collection $chapters): bool
    {
        $style = static::choice('viewer_chapter_style');
        if ($style !== 'auto') {
            return $style === 'menu';
        }

        return $chapters->count() > static::n('viewer_chapter_pills_max', 1, 30)
            || $chapters->contains(fn ($c) => mb_strlen((string) $c->title) > static::n('viewer_chapter_title_max', 8, 120));
    }

    public static function coverContents(): bool
    {
        return static::on('viewer_cover_contents');
    }
}
