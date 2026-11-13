<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Setting;

/**
 * The "Listen" button of an article: it reads the story aloud, start to finish, with an approved audio file when there is one,
 * and otherwise with the voice of the visitor's own device (the browser's speech engine: free, no account).
 * Every choice is a setting, on the admin screen "Slide viewer".
 */
class Listen
{
    /** key => [label, type, usual value, options] */
    public const SETTINGS = [
        'listen_on' => ['Show the Listen button on articles (it reads the article aloud with the voice of the visitor\'s device, or with an approved audio file)', 'toggle', '1', null],
        'listen_source' => ['Which voice to use', 'select', 'best', ['best' => 'The best there is: an approved audio file first, then the voice of the device', 'device' => 'Always the voice of the visitor\'s device', 'audio' => 'Only approved audio files (no Listen button where there is none)']],
        'listen_rate' => ['Speaking speed, in percent (60 to 140). 100 is normal; a little slower sounds like a story.', 'number', '95', null],
        'listen_follow' => ['While listening, move the slide viewer to the slide that is being read', 'toggle', '1', null],
        'listen_chapters' => ['Say the name of each new chapter before its first slide', 'toggle', '1', null],
        'listen_pause' => ['Pause between slides, in milliseconds (0 to 3000)', 'number', '700', null],
        'listen_voices' => ['Voices to prefer, best first, separated by commas. Each is a word from the name of a voice. The first one the device has is used.', 'text', 'Natural, Neural, Online, Google, Enhanced, Premium', null],
        'listen_replacements' => ['How to say signs and short forms (one on each line: written => said). A voice cannot say signs like the one for the Prophet.', 'textarea', "ﷺ => peace be upon him\n(AS) => peace be upon him\n(RA) => may Allah be pleased with him\n(SWT) => glory be to Him, the Most High", null],
    ];

    public static function on(): bool
    {
        return (string) Setting::get('listen_on', '1') === '1';
    }

    /** Which voice is in use: best | device | audio. */
    public static function source(): string
    {
        $v = (string) Setting::get('listen_source', 'best');

        return in_array($v, ['best', 'device', 'audio'], true) ? $v : 'best';
    }

    /**
     * The audio files visitors may hear: one for the whole story, one for the cover, and one for each slide (by its place in the viewer).
     *
     * @return array{topic: ?string, cover: ?string, slides: array<int, string>, full: list<int>, coverInFirst: bool}
     */
    public static function tracks(?Article $article, array $items = []): array
    {
        $tracks = ['topic' => null, 'cover' => null, 'slides' => [], 'full' => [], 'coverInFirst' => false];
        if (! $article || static::source() === 'device') {
            return $tracks;
        }
        if ($article->audioIsLive()) {
            $tracks['topic'] = $article->audioUrl();
        }
        if ($article->coverAudioIsLive()) {
            $tracks['cover'] = $article->coverAudioUrl();
        }
        foreach (array_values($items) as $i => $item) {
            if (($item['type'] ?? null) === 'slide' && $item['slide']->audioIsLive()) {
                $tracks['slides'][$i] = $item['slide']->audioUrl();
            }
        }

        return $tracks;
    }

    /** Does the Listen bar show at all? (With "only audio files" it needs at least one.) */
    public static function visible(array $tracks): bool
    {
        return static::on() && (static::source() !== 'audio' || $tracks['topic'] !== null || $tracks['cover'] !== null || $tracks['slides'] !== []);
    }

    /** The settings and the words for listen.js, as JSON for a data-attribute. */
    public static function json(array $tracks = ['topic' => null, 'cover' => null, 'slides' => [], 'full' => [], 'coverInFirst' => false]): string
    {
        $replace = [];
        foreach (preg_split('/\R/u', (string) Setting::get('listen_replacements', static::SETTINGS['listen_replacements'][2])) ?: [] as $line) {
            if (str_contains($line, '=>')) {
                [$from, $to] = array_map('trim', explode('=>', $line, 2));
                if ($from !== '') {
                    $replace[] = [$from, $to];
                }
            }
        }

        $data = [
            'lang' => str_replace('_', '-', app()->getLocale()),
            'rate' => min(1.4, max(0.6, ((int) Setting::get('listen_rate', '95')) / 100)),
            'follow' => (string) Setting::get('listen_follow', '1') === '1',
            'chapters' => (string) Setting::get('listen_chapters', '1') === '1',
            'pause' => min(3000, max(0, (int) Setting::get('listen_pause', '700'))),
            'voices' => array_values(array_filter(array_map('trim', explode(',', (string) Setting::get('listen_voices', static::SETTINGS['listen_voices'][2]))))),
            'replace' => $replace,
            'source' => static::source(),
            'audio' => ['topic' => $tracks['topic'], 'cover' => $tracks['cover'] ?? null, 'slides' => (object) $tracks['slides'], 'full' => array_values($tracks['full'] ?? []), 'coverInFirst' => (bool) ($tracks['coverInFirst'] ?? false)],
            'labels' => [
                'chapter' => __('site.listen_chapter'),
                'reading' => __('site.listen_reading'),
                'done' => __('site.listen_done'),
                'unsupported' => __('site.listen_unsupported'),
            ],
        ];

        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
