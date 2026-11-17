<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Site controls: the On/Off switches for the parts of the public site that make it look busy.
 * Each switch uses one Setting. Some already existed (card counts, Listen, PDF book, newsletter, comments) and keep their old key,
 * so there is only one place where each is stored. "Quiet look" turns the noisy ones off in one press; "Normal look" turns them on.
 */
class SiteControls
{
    /**
     * key => [label, help, group (noisy|extras), the Setting key, the value when nothing is saved yet]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}>
     */
    public static function all(): array
    {
        return [
            // The noisy parts: "Quiet look" switches these off.
            'reactions' => ['The like and dislike buttons', 'On every article and story.', 'noisy', 'ctl_reactions', true],
            'card_stats' => ['Likes, dislikes and views under the cards on the home page', 'The small row of numbers under each card.', 'noisy', 'card_stats_on', true],
            'share' => ['The share buttons', 'WhatsApp, Facebook, X, email and copy the link.', 'noisy', 'ctl_share', true],
            'social' => ['The social network buttons', 'The coloured buttons in the footer (set them in Social media).', 'noisy', 'ctl_social', true],
            'newsletter_popup' => ['The newsletter card that slides in at the bottom left', 'A small card that offers the newsletter a few seconds after a page opens. The Subscribe link in the footer always works.', 'noisy', 'ctl_newsletter_popup', true],
            'related' => ['"Related" articles under an article', 'The row of other articles from the same category.', 'noisy', 'ctl_related', true],
            // The useful extras: "Quiet look" leaves these as they are.
            'recent_menu' => ['The "Recent" tab on the right edge and "Continue where you left off" on the home page', 'A small tab stuck to the right edge of the window that opens a card with the last pages this visitor opened, and a row of them on the home page. The list is kept only in the visitor\'s own browser.', 'extras', 'ctl_recent_menu', true],
            'save' => ['Save for later (bookmarks)', 'For people who are logged in. They find their list in My account.', 'extras', 'ctl_save', true],
            'listen' => ['The Listen button', 'Reads an article or a story aloud. More choices in Sliders and the Listen settings.', 'extras', 'listen_on', true],
            'story_pdf' => ['The button "Download as a PDF book" on a story', 'The story as a printable book.', 'extras', 'story_pdf_on', true],
            'newsletter' => ['The Subscribe button and the newsletter page', 'Also in Site settings.', 'extras', 'newsletter_on', true],
            'comments' => ['Comments', 'Off: nobody can post a new comment. The ones already shown stay.', 'extras', 'comments_open', true],
            'grid_list' => ['The grid / list switch on the home and category pages', 'Lets a reader choose cards or a list.', 'extras', 'ctl_grid_list', true],
            'language' => ['The language button at the top', 'The list of languages with flags (set them in Languages).', 'extras', 'ctl_language', true],
            'theme' => ['The light / dark button', 'Lets a reader choose the colours.', 'extras', 'ctl_theme', true],
            'reviewed_by' => ['The line "Reviewed by …" on an article or a story', 'It shows only when a reviewer name is filled in.', 'extras', 'ctl_reviewed_by', true],
        ];
    }

    /** Is this part switched on? Nothing saved yet means the usual default (on). */
    public static function on(string $key): bool
    {
        $def = static::all()[$key] ?? null;
        if (! $def) {
            return true;
        }
        $v = Setting::get($def[3]);

        return $v === null || $v === '' ? $def[4] : (bool) filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return list<string> the keys of one group */
    public static function keys(string $group): array
    {
        return array_keys(array_filter(static::all(), fn ($d) => $d[2] === $group));
    }

    public static function set(string $key, bool $on): void
    {
        if ($def = static::all()[$key] ?? null) {
            Setting::put($def[3], $on);
        }
    }

    /** "Quiet look": the noisy parts off. "Normal look": on. The useful extras are never touched. */
    public static function look(bool $quiet): void
    {
        foreach (static::keys('noisy') as $key) {
            static::set($key, ! $quiet);
        }
    }

    /** Are all the noisy parts off? (To show which look is on.) */
    public static function isQuiet(): bool
    {
        foreach (static::keys('noisy') as $key) {
            if (static::on($key)) {
                return false;
            }
        }

        return true;
    }
}
