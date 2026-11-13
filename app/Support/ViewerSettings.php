<?php

namespace App\Support;

/**
 * Every control of the story slider, in one list, for the admin screen "Slide viewer".
 * The viewer itself reads them through ViewerStyle (and StoryImage, Listen, StoryBook); nothing about how it looks is fixed in code.
 * A setting that was never saved has its usual value (the third item of each line).
 */
class ViewerSettings
{
    /** key => [label, type (toggle|number|select|text|textarea), usual value, options for a select]. Pictures, marks and buttons. */
    public const STORY = [
        'story_image_ratio' => ['Shape of the pictures that are uploaded (16:9 is the shape of a 1920 x 1080 picture)', 'select', '16:9', ['16:9' => '16:9 (wide, like a screen)', '4:3' => '4:3 (a little taller)']],
        'story_image_width' => ['Widest picture in pixels (400 to 2400)', 'number', '1200', null],
        'story_image_quality' => ['First picture quality (40 to 95). It is lowered by itself if the picture is too big.', 'number', '80', null],
        'story_image_kb' => ['The size a picture should stay under, in KB', 'number', '200', null],
        'story_cover_on' => ['The first slide is a cover (title, short description, list of chapters)', 'toggle', '1', null],
        'story_watermark_on' => ['Show the site logo (Site settings > Logo) in the top left of each picture', 'toggle', '1', null],
        'story_watermark_name' => ['Show the site name next to the logo on each picture', 'toggle', '1', null],
        'story_protect_images' => ['Make pictures hard to save (no right click, no dragging, no long press). It cannot stop a screenshot.', 'toggle', '1', null],
        'story_default_credit' => ['Picture credit shown when a slide has none', 'text', 'AI Generated', null],
        'story_show_buttons' => ['Show the buttons Previous, Next and Next chapter under the slide (off: the arrows at the sides and the dots are enough)', 'toggle', '0', null],
        'story_pdf_on' => ['Show the button "Download as a PDF book"', 'toggle', '1', null],
        'story_book_layout' => ['PDF book: how a page with a picture looks', 'select', 'full', ['full' => 'The picture fills the page, the words under it', 'side' => 'The picture on the left, the words on the right']],
    ];

    /** The viewer settings, in groups (the headings are what you see on the screen). */
    public const VIEWER_GROUPS = [
        'Size and place' => ['viewer_width', 'viewer_box_width', 'viewer_height_mode', 'viewer_height_offset', 'viewer_height_min', 'viewer_height_max', 'viewer_height_fixed'],
        'The picture' => ['viewer_picture_side', 'viewer_picture_ratio', 'viewer_picture_share', 'viewer_picture_fit', 'viewer_caption', 'viewer_mark_size', 'viewer_mark_opacity'],
        'The text' => ['viewer_text_size', 'viewer_text_align', 'viewer_text_valign', 'viewer_padding', 'viewer_cover_contents'],
        'The frame' => ['viewer_background', 'viewer_border', 'viewer_shadow', 'viewer_radius'],
        'Moving' => ['viewer_transition', 'viewer_speed', 'viewer_autoplay', 'viewer_pause_hover', 'viewer_loop', 'viewer_keys', 'viewer_swipe'],
        'Controls' => ['viewer_arrows', 'viewer_arrow_size', 'viewer_arrow_color', 'viewer_dots', 'viewer_dots_place', 'viewer_counter', 'viewer_home_button', 'viewer_chapter_buttons', 'viewer_chapter_style', 'viewer_chapter_pills_max', 'viewer_chapter_title_max', 'viewer_contents_numbers', 'viewer_slide_chapter_label'],
        'On a phone' => ['viewer_mobile_picture', 'viewer_mobile_picture_height'],
    ];

    /** @return array<string, array<string, array{0: string, 1: string, 2: string, 3: ?array}>> heading => [key => definition] */
    public static function groups(): array
    {
        $groups = ['Pictures, marks and buttons' => self::STORY];
        foreach (self::VIEWER_GROUPS as $heading => $keys) {
            foreach ($keys as $key) {
                $groups['The slide viewer: '.$heading][$key] = ViewerStyle::SETTINGS[$key];
            }
        }
        if (class_exists(Listen::class)) {
            $groups['Listen'] = Listen::SETTINGS;
        }

        return $groups;
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: ?array}> key => definition, for every control */
    public static function definitions(): array
    {
        $all = [];
        foreach (static::groups() as $items) {
            $all += $items;
        }

        return $all;
    }
}
