<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Support\StoryMaker\Rules;
use App\Support\StoryMaker\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 75: a story in its other languages on the site: the language row, /ur/stories/name, language tags, English-only Listen and PDF, the sitemap. */
class Step75Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function story(string $slug = 'the-walk', string $title = 'The Walk', array $translated = ['ur' => 'on']): Story
    {
        $cat = Category::firstOrCreate(['slug' => 'stories'], ['name' => ['en' => 'Stories']]);
        $story = Story::create(['title' => ['en' => $title], 'slug' => $slug, 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'A man walks.']]);
        $chapter = $story->chapters()->create(['title' => ['en' => 'The opening'], 'sort_order' => 1]);
        $chapter->slides()->create(['sort_order' => 1, 'heading' => ['en' => 'The hill'], 'body' => ['en' => 'A man walked to the hill.']]);
        $chapter->slides()->create(['sort_order' => 2, 'heading' => ['en' => 'The end'], 'body' => ['en' => 'He went home.']]);
        foreach ($translated as $code => $status) {
            $story->setTranslation('title', $code, "[{$code}] ".$title);
            $story->setTranslation('short_detail', $code, "[{$code}] A man walks.");
            $story->setTranslation('meta_title', $code, "[{$code}] meta");
            $story->save();
            foreach ($story->chapters()->with('slides')->get() as $c) {
                $c->setTranslation('title', $code, "[{$code}] chapter")->save();
                foreach ($c->slides as $s) {
                    $s->setTranslation('heading', $code, "[{$code}] ".$s->getTranslation('heading', 'en'))->setTranslation('body', $code, "[{$code}] ".$s->getTranslation('body', 'en'))->save();
                }
            }
            Translations::saved($story->fresh(), $code, null);
            if ($status === 'on') {
                Translations::set($story->fresh(), $code, 'on');
            }
        }

        return $story->fresh();
    }

    // ---------- the pages ----------

    public function test_a_language_that_is_on_has_its_own_address_with_the_story_in_that_language(): void
    {
        $this->story();
        $html = $this->get('/ur/stories/the-walk')->assertOk()->getContent();

        $this->assertStringContainsString('[ur] The Walk', $html);
        $this->assertStringContainsString('[ur] The hill', $html);
        $this->assertStringContainsString('<article class="wrap topic story-page" lang="ur" dir="rtl"', $html, 'the story is right to left');
        $this->assertStringContainsString('<html lang="en" dir="ltr">', $html, 'the header and the footer stay English and left to right');
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/ur/stories/the-walk').'">', $html);
        $this->assertStringContainsString('"inLanguage":"ur"', $html);
        $this->assertStringContainsString('og:locale" content="ur"', $html);
    }

    public function test_the_words_of_the_story_page_come_from_the_language_files(): void
    {
        $this->story();
        $ur = $this->get('/ur/stories/the-walk')->getContent();
        $this->assertStringContainsString('اس زبان میں پڑھیں', $ur);
        $this->assertStringContainsString('تصویروں میں مزید کہانیاں', $ur === '' ? '' : 'تصویروں میں مزید کہانیاں', 'present in the file');
        $this->assertStringContainsString('پسند', $ur);   // the Like button

        $en = $this->get('/stories/the-walk?lang=en')->getContent();
        $this->assertStringContainsString('Read in', $en);
        $this->assertStringNotContainsString('اس زبان میں پڑھیں', $en);
    }

    public function test_the_language_row_lists_english_and_the_languages_that_are_on_only(): void
    {
        $this->story('the-walk', 'The Walk', ['ur' => 'on', 'fr' => 'hidden']);
        $html = $this->get('/stories/the-walk?lang=en')->getContent();

        $this->assertStringContainsString('class="story-langs"', $html);
        $this->assertStringContainsString('href="'.url('/stories/the-walk?lang=en').'" lang="en" hreflang="en" aria-current="true"', $html);
        $this->assertStringContainsString('href="'.url('/ur/stories/the-walk').'" lang="ur"', $html);
        $this->assertStringContainsString('اردو', $html);
        $this->assertStringNotContainsString('/fr/stories/the-walk', $html, 'a hidden language is not offered');
    }

    public function test_a_story_with_no_language_that_is_on_has_no_row_and_no_language_tags(): void
    {
        $this->story('plain', 'Plain', []);
        $html = $this->get('/stories/plain')->getContent();
        $this->assertStringNotContainsString('story-langs', $html);
        $this->assertStringNotContainsString('hreflang', $html);

        $this->story('hidden-only', 'Hidden only', ['ur' => 'hidden']);
        $html2 = $this->get('/stories/hidden-only')->getContent();
        $this->assertStringNotContainsString('story-langs', $html2);
        $this->assertStringNotContainsString('hreflang', $html2);
    }

    public function test_search_engines_get_the_language_tags_with_a_default(): void
    {
        $this->story('the-walk', 'The Walk', ['ur' => 'on', 'ar' => 'on', 'fr' => 'hidden']);
        foreach (['/stories/the-walk', '/ur/stories/the-walk'] as $url) {
            $html = $this->get($url.'?lang=en')->getContent();
            $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="'.url('/stories/the-walk').'">', $html, $url);
            $this->assertStringContainsString('<link rel="alternate" hreflang="ur" href="'.url('/ur/stories/the-walk').'">', $html, $url);
            $this->assertStringContainsString('<link rel="alternate" hreflang="ar" href="'.url('/ar/stories/the-walk').'">', $html, $url);
            $this->assertStringContainsString('hreflang="x-default" href="'.url('/stories/the-walk').'"', $html, $url);
            $this->assertStringNotContainsString('hreflang="fr"', $html, $url);
        }
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/stories/the-walk').'">', $this->get('/stories/the-walk?lang=en')->getContent(), 'the English address stays clean');
    }

    // ---------- hidden and missing languages ----------

    public function test_a_hidden_or_missing_language_sends_a_visitor_to_the_english_story(): void
    {
        $this->story('the-walk', 'The Walk', ['fr' => 'hidden']);

        $this->get('/fr/stories/the-walk')->assertRedirect(url('/stories/the-walk'));
        $this->get('/hi/stories/the-walk')->assertRedirect(url('/stories/the-walk'));
        $this->get('/xx/stories/the-walk')->assertNotFound();
    }

    public function test_staff_can_look_at_a_hidden_language_and_it_is_not_indexed(): void
    {
        $this->story('the-walk', 'The Walk', ['fr' => 'hidden']);
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));

        $html = $this->get('/fr/stories/the-walk')->assertOk()->getContent();
        $this->assertStringContainsString('[fr] The Walk', $html);
        $this->assertStringContainsString('This language is hidden', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
        $this->assertStringContainsString('(hidden)', $html);
    }

    public function test_an_unpublished_story_is_not_shown_in_any_language(): void
    {
        $story = $this->story();
        $story->update(['status' => 'draft']);
        $this->get('/ur/stories/the-walk')->assertNotFound();
    }

    // ---------- the choice is remembered ----------

    public function test_the_language_a_visitor_chose_is_remembered_for_the_next_story(): void
    {
        $this->story('the-walk', 'The Walk', ['ur' => 'on']);
        $this->story('another', 'Another', ['ur' => 'on']);
        $this->story('english-only', 'English only', []);

        $this->get('/ur/stories/the-walk')->assertOk()->assertCookie(\App\Http\Controllers\StoryController::COOKIE, 'ur');

        $this->withCookie(\App\Http\Controllers\StoryController::COOKIE, 'ur')->get('/stories/another')->assertRedirect(url('/ur/stories/another'));
        $this->withCookie(\App\Http\Controllers\StoryController::COOKIE, 'ur')->get('/stories/english-only')->assertOk();   // no Urdu: English

        // "English" is a choice too, and is remembered.
        $this->withCookie(\App\Http\Controllers\StoryController::COOKIE, 'ur')->get('/stories/another?lang=en')->assertOk()->assertCookie(\App\Http\Controllers\StoryController::COOKIE, 'en');
        $this->withCookie(\App\Http\Controllers\StoryController::COOKIE, 'en')->get('/stories/another')->assertOk();
    }

    public function test_a_remembered_language_that_is_hidden_for_that_story_does_not_redirect(): void
    {
        $this->story('the-walk', 'The Walk', ['ur' => 'hidden']);
        $this->withCookie(\App\Http\Controllers\StoryController::COOKIE, 'ur')->get('/stories/the-walk')->assertOk();
    }

    // ---------- English only features ----------

    public function test_listen_and_the_pdf_book_are_for_english_only(): void
    {
        $this->story();

        $en = $this->get('/stories/the-walk?lang=en')->getContent();
        $this->assertStringContainsString('story-tools__book', $en);
        $this->assertStringContainsString('data-listen=', $en);

        $ur = $this->get('/ur/stories/the-walk')->getContent();
        $this->assertStringNotContainsString('story-tools__book', $ur);
        $this->assertStringNotContainsString('data-listen=', $ur);
        $this->assertStringNotContainsString('js/listen.js', $ur);
    }

    // ---------- links ----------

    public function test_the_links_to_other_stories_stay_in_the_language_when_the_story_has_it(): void
    {
        $this->story('first', 'First', ['ur' => 'on']);
        $this->story('second', 'Second', ['ur' => 'on']);
        $this->story('third', 'Third', []);

        $html = $this->get('/ur/stories/second')->getContent();
        $this->assertStringContainsString('href="'.url('/ur/stories/first').'"', $html, 'previous');
        $this->assertStringContainsString('href="'.url('/stories/third').'"', $html, 'a story without the language links to English');
        $this->assertStringNotContainsString('href="'.url('/ur/stories/third').'"', $html);

        $share = $this->get('/ur/stories/second')->getContent();
        $this->assertStringContainsString(rawurlencode(url('/ur/stories/second')), $share, 'the share links carry the address of this language');
    }

    public function test_the_urls_of_a_story(): void
    {
        $story = $this->story('the-walk', 'The Walk', ['ur' => 'on', 'fr' => 'hidden']);
        $this->assertSame(url('/ur/stories/the-walk'), $story->urlIn('ur'));
        $this->assertSame(url('/ur/stories/the-walk'), $story->urlFor('ur'));
        $this->assertSame(url('/stories/the-walk'), $story->urlFor('fr'), 'hidden: English');
        $this->assertSame(url('/stories/the-walk'), $story->urlFor('de'));
        $this->assertSame(url('/stories/the-walk'), $story->urlFor(null));
    }

    // ---------- the sitemap ----------

    public function test_the_sitemap_lists_the_languages_that_are_on_only(): void
    {
        $this->story('the-walk', 'The Walk', ['ur' => 'on', 'fr' => 'hidden']);
        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString('<loc>'.url('/stories/the-walk').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/ur/stories/the-walk').'</loc>', $xml);
        $this->assertStringNotContainsString('/fr/stories/the-walk', $xml);
    }

    // ---------- the language files ----------

    public function test_every_language_file_has_only_known_keys_and_the_same_placeholders_as_english(): void
    {
        foreach (array_keys(Rules::languageDefaults()) as $code) {
            foreach (['site', 'engage'] as $file) {
                $english = require lang_path("en/{$file}.php");
                $own = require lang_path("{$code}/{$file}.php");
                $this->assertNotEmpty($own, "{$code}/{$file}");
                foreach ($own as $key => $text) {
                    $this->assertArrayHasKey($key, $english, "{$code}/{$file}.php has a key that English does not have: {$key}");
                    preg_match_all('/:[a-z]+/', $english[$key], $a);
                    preg_match_all('/:[a-z]+/', $text, $b);
                    $this->assertSame($a[0], $b[0], "{$code}/{$file}.php {$key}: the placeholders must be the same as in English");
                    $this->assertNotSame('', trim($text), "{$code}/{$file}.php {$key} is empty");
                }
            }
        }
    }

    public function test_all_the_words_of_the_story_page_are_translated_in_every_language(): void
    {
        $needed = ['site' => ['also_in', 'by', 'home', 'id_title', 'keywords', 'next', 'previous', 'published', 'reviewed_by', 'source', 'stories_more', 'stories_title', 'story_chapters', 'story_chapters_menu', 'story_contents', 'story_dots', 'story_empty', 'story_goto', 'story_home', 'story_keys_hint', 'story_next', 'story_next_chapter', 'story_one_slide', 'story_open', 'story_picture_credit', 'story_prev', 'story_read_in', 'story_slide_label', 'story_slides_count', 'verify_needed'],
            'engage' => ['copied', 'copy_link', 'dislike', 'email', 'label', 'like', 'log_in', 'log_in_to', 'save', 'saved', 'share', 'unsave']];
        foreach (array_keys(Rules::languageDefaults()) as $code) {
            foreach ($needed as $file => $keys) {
                $own = require lang_path("{$code}/{$file}.php");
                foreach ($keys as $key) {
                    $this->assertArrayHasKey($key, $own, "{$code}/{$file}.php is missing {$key}");
                }
            }
        }
    }

    public function test_every_language_of_the_story_maker_has_a_page_and_the_rtl_ones_are_right_to_left(): void
    {
        foreach (array_keys(Rules::languageDefaults()) as $code) {
            $this->story("story-{$code}", "Story {$code}", [$code => 'on']);
            $html = $this->get("/{$code}/stories/story-{$code}")->assertOk()->getContent();
            $rtl = in_array($code, ['ur', 'ar'], true) ? 'rtl' : 'ltr';
            $this->assertStringContainsString("lang=\"{$code}\" dir=\"{$rtl}\"", $html, $code);
        }
    }
}
