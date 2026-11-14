<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Story;
use App\Models\Setting;

/**
 * Every slide of an article in reading order, numbered from 1, each with the number of its chapter.
 * The cover comes first (the title, the short detail and the list of chapters). Needs the chapters and slides of the article.
 */
class StoryItems
{
    /** @return array<int, array<string, mixed>> */
    public static function build(Article|Story $article): array
    {
        $article->loadMissing('chapters.slides');

        $items = [];
        $n = 0;
        foreach ($article->chapters as $c => $chapter) {
            foreach ($chapter->slides as $slide) {
                $items[] = ['type' => 'slide', 'n' => ++$n, 'chapter' => $c, 'chapterTitle' => $chapter->title, 'slide' => $slide];
            }
        }
        if ($items === []) {
            return [];
        }

        if ((string) Setting::get('story_cover_on', '1') === '1') {
            $index = [];
            foreach ($items as $it) {
                if (! isset($index[$it['chapter']])) {
                    $index[$it['chapter']] = ['title' => $it['chapterTitle'], 'n' => $it['n'] + 1, 'slides' => []];
                }
                $index[$it['chapter']]['slides'][] = ['heading' => $it['slide']->heading, 'n' => $it['n'] + 1];
            }
            foreach ($items as &$it) {
                $it['n']++;
            }
            unset($it);
            array_unshift($items, ['type' => 'cover', 'n' => 1, 'chapter' => -1, 'chapterTitle' => $article->title, 'index' => array_values($index)]);
        }

        return $items;
    }
}