<?php

namespace Tests\Feature;

use App\Filament\Pages\MoveFromOldSite;
use App\Filament\Pages\SlideViewerSettings;
use App\Filament\Resources\Stories\Pages\EditStory;
use App\Models\Article;
use App\Models\Story;
use App\Models\ArticleChapter;
use App\Models\ArticleSlide;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Support\Listen;
use App\Support\OldSettingsImport;
use App\Support\OldStoryImport;
use App\Support\StoryBook;
use App\Support\StoryImage;
use App\Support\StoryItems;
use App\Support\ViewerSettings;
use App\Support\ViewerStyle;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 13: the controls of the story slider (admin screen), the PDF book, Listen, audio files, and the old choices brought across. */
class Step13Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
        Storage::fake('public');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function story(array $extra = []): Story
    {
        // a real small picture on the fake disk, so the book and the viewer have something to show
        $im = imagecreatetruecolor(800, 450);
        imagefilledrectangle($im, 0, 0, 800, 450, imagecolorallocate($im, 40, 90, 160));
        ob_start();
        imagewebp($im, null, 70);
        Storage::disk('public')->put('stories/p.webp', ob_get_clean());

        $a = Story::create(array_merge([
            'title' => ['en' => 'A story with ﷺ in it'], 'slug' => 'a-story', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'short_detail' => ['en' => 'Short words.'], 'long_detail' => ['en' => '<p>The text.</p>'], 'main_image' => 'stories/p.webp', 'main_image_alt' => ['en' => 'Cover'],
        ], $extra));
        $c1 = $a->chapters()->create(['title' => ['en' => 'First'], 'sort_order' => 1]);
        $c1->slides()->create(['heading' => ['en' => 'Slide one'], 'body' => ['en' => "Para one.\n\nPara two."], 'image' => 'stories/p.webp', 'image_credit' => 'Me', 'sort_order' => 1]);
        $c1->slides()->create(['heading' => ['en' => 'Slide two'], 'body' => ['en' => 'More words.'], 'sort_order' => 2]);
        $c2 = $a->chapters()->create(['title' => ['en' => 'Second'], 'sort_order' => 2]);
        $c2->slides()->create(['heading' => ['en' => 'Slide three'], 'body' => ['en' => 'End words.'], 'sort_order' => 1]);

        return $a;
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    // ---------- the admin screen ----------

    public function test_the_screen_is_for_the_super_user_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(SlideViewerSettings::canAccess());
        $this->get('/admin/sliders')->assertForbidden();

        $this->superuser();
        $this->assertTrue(SlideViewerSettings::canAccess());
        $this->get('/admin/sliders')->assertOk();
    }

    public function test_the_screen_has_every_control_of_the_old_site_in_groups(): void
    {
        $this->superuser();
        $groups = ViewerSettings::groups();

        $this->assertSame(['Pictures, marks and buttons', 'The slide viewer: Size and place', 'The slide viewer: The picture', 'The slide viewer: The text', 'The slide viewer: The frame',
            'The slide viewer: Moving', 'The slide viewer: Controls', 'The slide viewer: On a phone', 'Listen'], array_keys($groups));
        // every control of ViewerStyle is on the screen, none is missing
        $this->assertSame([], array_values(array_diff(array_keys(ViewerStyle::SETTINGS), array_keys(ViewerSettings::definitions()))));
        $this->assertSame([], array_values(array_diff(array_keys(Listen::SETTINGS), array_keys(ViewerSettings::definitions()))));
        $this->assertGreaterThanOrEqual(60, count(ViewerSettings::definitions()));

        $page = Livewire::test(SlideViewerSettings::class)->assertOk();
        foreach (array_keys($groups) as $heading) {
            $page->assertSee($heading);
        }
        foreach (['viewer_width', 'viewer_text_size', 'story_default_credit', 'listen_rate', 'story_pdf_on'] as $key) {
            $page->assertFormFieldExists($key);
        }
    }

    public function test_the_screen_shows_the_usual_values_until_something_is_saved(): void
    {
        $this->superuser();

        Livewire::test(SlideViewerSettings::class)
            ->assertFormSet(['viewer_width' => 'full', 'viewer_text_size' => '100', 'story_cover_on' => true, 'story_show_buttons' => false, 'story_default_credit' => 'AI Generated', 'listen_rate' => '95', 'story_image_ratio' => '16:9']);
    }

    public function test_saving_changes_the_viewer_and_keeps_only_what_is_not_usual(): void
    {
        $this->superuser();

        Livewire::test(SlideViewerSettings::class)
            ->fillForm(['viewer_width' => 'boxed', 'viewer_text_size' => '85', 'viewer_dots_place' => 'inside', 'story_default_credit' => 'Our own pictures', 'story_cover_on' => false])
            ->call('save')->assertHasNoFormErrors();

        Setting::forget();
        $this->assertSame('boxed', Setting::get('viewer_width'));
        $this->assertSame('85', Setting::get('viewer_text_size'));
        $this->assertSame('0', Setting::get('story_cover_on'));
        $this->assertNull(Setting::get('viewer_speed'), 'a control left at its usual value is not kept');
        $this->assertEqualsCanonicalizing(['viewer_width', 'viewer_text_size', 'viewer_dots_place', 'story_default_credit', 'story_cover_on'], Setting::whereIn('key', array_keys(ViewerSettings::definitions()))->pluck('key')->all());

        $this->assertStringContainsString('story--boxed', ViewerStyle::widthClass(true));
        $this->assertStringContainsString('--v-font: 0.85', ViewerStyle::style());
        $this->assertStringContainsString('viewer--dots-inside', ViewerStyle::classes());

        // and going back to the usual value removes it again
        Livewire::test(SlideViewerSettings::class)->fillForm(['viewer_width' => 'full'])->call('save');
        Setting::forget();
        $this->assertNull(Setting::get('viewer_width'));
    }

    public function test_a_number_must_be_a_number_and_a_choice_must_be_one_of_the_choices(): void
    {
        $this->superuser();

        Livewire::test(SlideViewerSettings::class)->fillForm(['viewer_speed' => 'fast'])->call('save')->assertHasFormErrors(['viewer_speed']);
        Livewire::test(SlideViewerSettings::class)->fillForm(['viewer_width' => 'sideways'])->call('save')->assertHasFormErrors(['viewer_width']);
        Livewire::test(SlideViewerSettings::class)->fillForm(['viewer_speed' => ''])->call('save')->assertHasFormErrors(['viewer_speed']);
        $this->assertNull(Setting::where('key', 'viewer_speed')->first());
    }

    public function test_back_to_the_usual_settings_clears_every_control_but_nothing_else(): void
    {
        $this->superuser();
        Setting::put('viewer_width', 'boxed');
        Setting::put('listen_rate', '120');
        Setting::put('site_name', ['en' => 'My own name']);
        Setting::put('home_latest_count', 5);

        Livewire::test(SlideViewerSettings::class)->callAction('reset')->assertFormSet(['viewer_width' => 'full', 'listen_rate' => '95']);

        Setting::forget();
        $this->assertNull(Setting::get('viewer_width'));
        $this->assertSame(['en' => 'My own name'], Setting::all_()['site_name'], 'the other settings stay');
        $this->assertSame(5, Setting::get('home_latest_count'));
    }

    public function test_a_link_shows_the_slider_on_a_real_story(): void
    {
        $this->superuser();
        $this->story();

        Livewire::test(SlideViewerSettings::class)->assertActionVisible('see');
        Story::query()->delete();
        Livewire::test(SlideViewerSettings::class)->assertActionHidden('see');
    }

    // ---------- the settings do what they say ----------

    public function test_the_cover_can_be_switched_off(): void
    {
        $a = $this->story();
        $this->assertSame('cover', StoryItems::build($a->fresh())[0]['type']);

        Setting::put('story_cover_on', '0');
        $items = StoryItems::build($a->fresh());
        $this->assertSame(['slide', 'slide', 'slide'], array_column($items, 'type'));
        $this->assertSame([1, 2, 3], array_column($items, 'n'));
    }

    public function test_the_default_credit_the_mark_and_the_protection_follow_the_settings(): void
    {
        $this->story();

        $html = $this->get('/stories/a-story')->getContent();
        $this->assertStringContainsString('Picture: AI Generated', $html);
        $this->assertStringContainsString('slide__shield', $html);
        $this->assertStringContainsString('slide__mark-name', $html);

        Setting::put('story_default_credit', 'Our own pictures');
        Setting::put('story_protect_images', '0');
        Setting::put('story_watermark_on', '0');
        $html = $this->get('/stories/a-story')->getContent();
        $this->assertStringContainsString('Picture: Our own pictures', $html);
        $this->assertStringNotContainsString('slide__shield', $html);
        $this->assertStringNotContainsString('slide__mark', $html);
        $this->assertStringNotContainsString('is-protected', $html);
    }

    public function test_the_previous_next_buttons_show_only_when_switched_on(): void
    {
        $this->story();
        $this->assertStringNotContainsString('data-story-prev', $this->get('/stories/a-story')->getContent());

        Setting::put('story_show_buttons', '1');
        $html = $this->get('/stories/a-story')->getContent();
        $this->assertStringContainsString('data-story-prev', $html);
        $this->assertStringContainsString('data-story-next-chapter', $html, 'there is more than one chapter');
        $this->assertStringContainsString('Tip: use the left and right arrow keys', $html);
    }

    public function test_the_picture_shape_is_a_setting(): void
    {
        $this->assertSame([16, 9], StoryImage::ratio());
        Setting::put('story_image_ratio', '4:3');
        $this->assertSame([4, 3], StoryImage::ratio());
        $this->assertSame(900, StoryImage::heightFor(1200));
    }

    // ---------- the PDF book ----------

    public function test_a_story_can_be_downloaded_as_a_pdf_book(): void
    {
        $this->story();

        $r = $this->get('/stories/a-story/book')->assertOk();

        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="a-story.pdf"', $r->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $this->assertGreaterThan(5000, strlen($r->getContent()));
        $this->assertSame('noindex', $r->headers->get('X-Robots-Tag'));
        // cover + contents + 2 chapter pages + 3 slides + the end page = 8 pages
        $this->assertMatchesRegularExpression('/\/Count 8\b/', $r->getContent());
    }

    public function test_the_book_button_is_on_the_story_and_follows_the_setting(): void
    {
        $this->story();
        $this->get('/stories/a-story')->assertSee('Download as a PDF book')->assertSee(route('story.book', 'a-story'), false);

        Setting::put('story_pdf_on', '0');
        $this->get('/stories/a-story')->assertDontSee('Download as a PDF book');
        $this->get('/stories/a-story/book')->assertNotFound();
    }

    public function test_only_a_visible_story_with_slides_has_a_book(): void
    {
        $this->story(['status' => 'draft']);
        $this->get('/stories/a-story/book')->assertNotFound();

        $plain = Article::create(['title' => ['en' => 'No slides'], 'slug' => 'no-slides', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
        $this->get('/no-slides/book')->assertNotFound();
        $this->get('/does-not-exist/book')->assertNotFound();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->get('/stories/a-story/book')->assertOk();
    }

    public function test_the_book_layout_is_a_setting_and_the_salutation_sign_is_handled(): void
    {
        $this->assertSame('full', StoryBook::layout());
        Setting::put('story_book_layout', 'side');
        $this->assertSame('side', StoryBook::layout());
        $this->get('/stories/a-story/book')->assertNotFound();   // no story yet: just proves the layout setting does not break routing

        // without a font for the sign it is written in words; with the font it keeps the sign
        $text = (string) StoryBook::t('Prophet Muhammad ﷺ <b>');
        $this->assertStringContainsString('&lt;b&gt;', $text);
        $this->assertTrue(str_contains($text, '(peace be upon him)') || str_contains($text, 'class="sal"'));
        $this->assertSame('<p>One</p><p>Two</p>', StoryBook::body("One\n\nTwo"));
    }

    public function test_a_picture_that_is_missing_does_not_break_the_book(): void
    {
        $this->story();
        Storage::disk('public')->delete('stories/p.webp');

        $this->assertNull(StoryBook::data('stories/p.webp'));
        $this->assertNull(StoryBook::data(null));
        $this->get('/stories/a-story/book')->assertOk();
    }

    // ---------- Listen ----------

    public function test_the_listen_bar_is_on_a_story_and_on_an_article_without_slides(): void
    {
        $this->story();
        Article::create(['title' => ['en' => 'Plain one'], 'slug' => 'plain-one', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => '<p>Words.</p>']]);

        foreach (['/stories/a-story' => 'Listen to the story', '/plain-one' => 'Listen to the article'] as $url => $words) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('data-listen=', $html, $url);
            $this->assertStringContainsString($words, $html, $url);
            $this->assertStringContainsString('js/listen.js', $html, $url);
        }
        $this->assertStringContainsString('.article__body', (string) file_get_contents(public_path('js/listen.js')), 'it also reads the long text of an article without slides');
    }

    public function test_listen_can_be_switched_off(): void
    {
        $this->story();
        Setting::put('listen_on', '0');

        $html = $this->get('/stories/a-story')->getContent();
        $this->assertStringNotContainsString('data-listen=', $html);
        $this->assertStringNotContainsString('js/listen.js', $html);
    }

    public function test_the_listen_settings_reach_the_script(): void
    {
        $this->story();
        Setting::put('listen_rate', '110');
        Setting::put('listen_pause', '1500');
        Setting::put('listen_follow', '0');
        Setting::put('listen_replacements', "ﷺ => peace be upon him\nBCE => before the common era\nnot a rule");

        $json = json_decode(Listen::json(), true);

        $this->assertSame(1.1, $json['rate']);
        $this->assertSame(1500, $json['pause']);
        $this->assertFalse($json['follow']);
        $this->assertTrue($json['chapters']);
        $this->assertSame([['ﷺ', 'peace be upon him'], ['BCE', 'before the common era']], $json['replace']);
        $this->assertSame('Chapter', $json['labels']['chapter']);
        $this->assertContains('Natural', $json['voices']);

        Setting::put('listen_rate', '999');
        $this->assertSame(1.4, json_decode(Listen::json(), true)['rate'], 'never faster than 140 percent');
    }

    public function test_only_approved_audio_is_played_and_the_places_are_by_slide_number(): void
    {
        $a = $this->story();
        $a->update(['cover_audio' => 'audio/covers/c.mp3', 'cover_audio_state' => 'approved']);
        $slides = $a->slides()->orderBy('story_slides.id')->get();
        $slides[0]->update(['audio' => 'audio/slides/one.mp3', 'audio_state' => 'approved']);
        $slides[1]->update(['audio' => 'audio/slides/two.mp3']);                              // a new file: still draft

        $tracks = Listen::tracks($a->fresh(), StoryItems::build($a->fresh()));

        $this->assertStringEndsWith('audio/covers/c.mp3', $tracks['cover']);
        $this->assertSame([1], array_keys($tracks['slides']), 'slide one is item 1 (the cover is item 0)');
        $this->assertStringEndsWith('audio/slides/one.mp3', $tracks['slides'][1]);
        $this->assertNull($tracks['topic']);
    }

    public function test_the_voice_choice_decides_whether_audio_files_are_offered(): void
    {
        $a = $this->story();
        $a->slides()->first()->update(['audio' => 'audio/slides/one.mp3', 'audio_state' => 'approved']);
        $items = StoryItems::build($a->fresh());

        $this->assertNotEmpty(Listen::tracks($a->fresh(), $items)['slides']);

        Setting::put('listen_source', 'device');
        $this->assertSame([], Listen::tracks($a->fresh(), $items)['slides'], 'always the voice of the device');
        $this->assertTrue(Listen::visible(Listen::tracks($a->fresh(), $items)));

        Setting::put('listen_source', 'audio');
        $plain = Article::create(['title' => ['en' => 'No audio'], 'slug' => 'no-audio', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
        $this->assertFalse(Listen::visible(Listen::tracks($plain, [])), 'only audio files, and there is none: no bar');
        $this->assertTrue(Listen::visible(Listen::tracks($a->fresh(), $items)));
        $this->get('/no-audio')->assertDontSee('data-listen=', false);
    }

    // ---------- audio files in the admin ----------

    public function test_a_new_or_changed_audio_file_goes_back_to_draft_until_it_is_approved_again(): void
    {
        $a = $this->story();
        $slide = $a->slides()->first();
        $slide->update(['audio' => 'audio/slides/one.mp3', 'audio_state' => 'approved']);
        $this->assertTrue($slide->fresh()->audioIsLive());

        $slide->update(['audio' => 'audio/slides/other.mp3']);
        $this->assertSame('draft', $slide->fresh()->audio_state);
        $this->assertFalse($slide->fresh()->audioIsLive());

        $a->update(['cover_audio' => 'audio/covers/c.mp3', 'cover_audio_state' => 'approved']);
        $this->assertTrue($a->fresh()->coverAudioIsLive());
        $a->update(['cover_audio' => 'audio/covers/d.mp3']);
        $this->assertFalse($a->fresh()->coverAudioIsLive());
        $a->update(['audio' => 'audio/articles/w.mp3']);
        $this->assertSame('draft', $a->fresh()->audio_state);
    }

    public function test_a_file_with_no_state_given_is_a_draft_and_a_missing_file_never_plays(): void
    {
        $a = $this->story();
        $slide = $a->slides()->first();
        $slide->update(['audio_state' => 'approved']);
        $this->assertFalse($slide->fresh()->audioIsLive(), 'approved but no file');
        $this->assertNull($slide->fresh()->audioUrl());
    }

    public function test_the_audio_boxes_are_on_the_article_form_and_only_a_moderator_can_approve(): void
    {
        $a = $this->story();
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));

        $sections = [];
        $page = Livewire::test(EditStory::class, ['record' => $a->getRouteKey()]);
        foreach ($page->instance()->form->getFlatComponents() as $c) {
            if ($c instanceof \Filament\Schemas\Components\Section) {
                $sections[] = (string) $c->getHeading();
            }
        }
        $this->assertContains('Audio for the Listen button', $sections);
        $page->assertFormFieldExists('cover_audio')->assertFormFieldExists('audio_state');
        $page->assertFormFieldIsEnabled('cover_audio_state');

        $author = User::factory()->create(['role' => 'author', 'status' => 'active']);
        $this->actingAs($author);
        $mine = $this->story(['slug' => 'mine', 'author_id' => $author->id, 'status' => 'draft']);
        Livewire::test(EditStory::class, ['record' => $mine->getRouteKey()])->assertFormFieldIsDisabled('cover_audio_state');
    }

    // ---------- the old choices brought across ----------

    private function fakeOldSettings(array $values): void
    {
        config(['database.connections.old_site' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('old_site');
        $old = Schema::connection('old_site');
        $old->create('index_entries', fn (Blueprint $t) => $t->id());
        $old->create('settings', function (Blueprint $t) {
            $t->id(); $t->string('key'); $t->text('value')->nullable();
        });
        foreach ($values as $key => $value) {
            DB::connection('old_site')->table('settings')->insert(['key' => $key, 'value' => $value]);
        }
    }

    public function test_the_choices_you_changed_in_the_old_site_are_listed_and_brought_across(): void
    {
        $this->fakeOldSettings([
            'viewer_width' => 'boxed', 'viewer_text_size' => '85', 'viewer_dots_place' => 'inside', 'viewer_text_valign' => 'center', 'story_image_ratio' => '4:3',
            'viewer_speed' => '550',                  // the same as the usual value: nothing to bring
            'story_demo_made' => '1', 'viewer_not_a_control' => 'x',     // not controls of this site
            'viewer_transition' => 'teleport',        // not one of the choices: ignored
        ]);

        $changes = (new OldSettingsImport)->check();

        $this->assertSame(['viewer_width', 'viewer_text_size', 'viewer_dots_place', 'viewer_text_valign', 'story_image_ratio'], array_keys($changes));
        $this->assertSame(['old' => 'boxed', 'now' => 'full'], array_intersect_key($changes['viewer_width'], array_flip(['old', 'now'])));
        $this->assertNull(Setting::get('viewer_width'), 'checking changes nothing');

        $this->assertSame(5, (new OldSettingsImport)->bring());

        Setting::forget();
        $this->assertSame('boxed', Setting::get('viewer_width'));
        $this->assertSame('4:3', Setting::get('story_image_ratio'));
        $this->assertSame([4, 3], StoryImage::ratio());
        $this->assertStringContainsString('--v-font: 0.85', ViewerStyle::style());
        $this->assertSame([], (new OldSettingsImport)->check(), 'bringing them again changes nothing');
    }

    public function test_an_old_value_that_is_the_usual_one_removes_the_choice_here(): void
    {
        $this->fakeOldSettings(['viewer_width' => 'full']);
        Setting::put('viewer_width', 'boxed');

        $this->assertSame(1, (new OldSettingsImport)->bring());

        Setting::forget();
        $this->assertNull(Setting::get('viewer_width'));
    }

    public function test_the_page_brings_the_old_choices_across_in_two_steps(): void
    {
        $this->fakeOldSettings(['viewer_width' => 'boxed', 'viewer_picture_share' => '60']);
        $this->superuser();

        Livewire::test(MoveFromOldSite::class)
            ->assertSee('Your slide viewer choices')
            ->call('checkViewer')->assertSee('Bring them across')->assertSee('boxed')
            ->call('bringViewer')->assertSee('Done. The slider now follows your old choices.');

        Setting::forget();
        $this->assertSame('60', Setting::get('viewer_picture_share'));
    }

    // ---------- the old audio ----------

    private function fakeOldStoryWithAudio(): string
    {
        $path = sys_get_temp_dir().'/oldaudio-'.uniqid();
        mkdir($path.'/storage/app/public/audio', 0777, true);
        config(['infofluxes.old_site_path' => $path, 'database.connections.old_site' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('old_site');
        $old = Schema::connection('old_site');
        $old->create('index_entries', fn (Blueprint $t) => $t->id());
        $old->create('settings', function (Blueprint $t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); });
        $old->create('topics', function (Blueprint $t) {
            $t->id(); $t->string('public_id'); $t->string('title'); $t->text('summary')->nullable(); $t->text('hook')->nullable(); $t->text('context')->nullable();
            $t->string('master_image')->nullable(); $t->string('master_image_alt')->nullable(); $t->string('master_image_credit')->nullable(); $t->string('master_image_licence')->nullable();
            $t->string('status')->default('draft'); $t->dateTime('published_at')->nullable(); $t->boolean('ai_assisted')->default(false); $t->string('ai_reviewed_by')->nullable();
            $t->string('audio')->nullable(); $t->string('audio_state')->nullable(); $t->string('cover_audio')->nullable(); $t->string('cover_audio_state')->nullable(); $t->softDeletes();
        });
        $old->create('story_chapters', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('topic_id'); $t->string('title'); $t->unsignedInteger('sort_order')->default(0); });
        $old->create('story_slides', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('story_chapter_id'); $t->unsignedInteger('sort_order')->default(0); $t->string('image')->nullable(); $t->string('image_alt')->nullable();
            $t->string('image_credit')->nullable(); $t->string('image_licence')->nullable(); $t->string('heading'); $t->text('body')->nullable(); $t->string('audio')->nullable(); $t->string('audio_state')->nullable();
        });
        foreach (['cover.mp3', 'one.mp3', 'two.mp3'] as $f) {
            file_put_contents($path.'/storage/app/public/audio/'.$f, 'mp3-bytes-'.$f);
        }
        $db = DB::connection('old_site');
        $topic = $db->table('topics')->insertGetId(['public_id' => 'T-14', 'title' => 'A story with ﷺ in it', 'status' => 'published', 'cover_audio' => 'audio/cover.mp3', 'cover_audio_state' => 'draft']);
        $c = $db->table('story_chapters')->insertGetId(['topic_id' => $topic, 'title' => 'First', 'sort_order' => 1]);
        $blank = ['image' => null, 'image_alt' => null, 'image_credit' => null, 'image_licence' => null, 'body' => null, 'audio' => null, 'audio_state' => null];
        $db->table('story_slides')->insert([
            array_merge($blank, ['story_chapter_id' => $c, 'sort_order' => 1, 'heading' => 'Slide one', 'audio' => 'audio/one.mp3', 'audio_state' => 'approved']),
            array_merge($blank, ['story_chapter_id' => $c, 'sort_order' => 2, 'heading' => 'Slide two', 'audio' => 'audio/two.mp3', 'audio_state' => 'draft']),
            array_merge($blank, ['story_chapter_id' => $c, 'sort_order' => 3, 'heading' => 'Slide three']),
        ]);

        return $path;
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir.'/storage/app/public/audio/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach ([$dir.'/storage/app/public/audio', $dir.'/storage/app/public', $dir.'/storage/app', $dir.'/storage', $dir] as $d) {
            @rmdir($d);
        }
    }

    public function test_a_story_that_is_moved_brings_its_audio_with_its_state(): void
    {
        $dir = $this->fakeOldStoryWithAudio();
        try {
            $cat = Category::create(['name' => ['en' => 'About Islam'], 'slug' => 'about-islam']);

            $r = (new OldStoryImport($cat->id))->move();

            $this->assertSame(3, $r['total']['audio_copied']);
            $a = Story::where('custom_data->old_id', 'T-14')->first();
            $this->assertSame('audio/cover.mp3', $a->cover_audio);
            $this->assertSame('draft', $a->cover_audio_state);
            $slides = $a->slides()->orderBy('story_slides.sort_order')->get();
            $this->assertSame(['approved', 'draft', 'draft'], $slides->map->audio_state->all());
            $this->assertSame('audio/one.mp3', $slides[0]->audio);
            $this->assertNull($slides[2]->audio);
            Storage::disk('public')->assertExists('audio/one.mp3');
            $this->assertSame('mp3-bytes-one.mp3', Storage::disk('public')->get('audio/one.mp3'));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function test_audio_can_be_brought_to_a_story_that_was_moved_before_and_only_where_the_slide_matches(): void
    {
        $dir = $this->fakeOldStoryWithAudio();
        try {
            $cat = Category::create(['name' => ['en' => 'About Islam'], 'slug' => 'about-islam']);
            // the story was moved earlier, without audio; one slide was renamed since
            $a = Story::create(['title' => ['en' => 'A story'], 'slug' => 'a-story-moved', 'category_id' => $cat->id, 'custom_data' => ['old_id' => 'T-14'], 'status' => 'published']);
            $ch = $a->chapters()->create(['title' => ['en' => 'First'], 'sort_order' => 1]);
            $ch->slides()->create(['heading' => ['en' => 'Slide one'], 'sort_order' => 1]);
            $ch->slides()->create(['heading' => ['en' => 'A new name'], 'sort_order' => 2]);
            $ch->slides()->create(['heading' => ['en' => 'Slide three'], 'sort_order' => 3]);

            $check = (new OldStoryImport)->checkAudio();
            $this->assertSame(['audio_copied' => 2, 'audio_missing' => 0, 'stories_matched' => 1, 'stories_not_moved' => 0, 'slides_matched' => 1, 'slides_unmatched' => 1], $check['total']);
            $this->assertNull($a->fresh()->cover_audio, 'checking changes nothing');
            Storage::disk('public')->assertMissing('audio/one.mp3');

            $r = (new OldStoryImport)->moveAudio();
            $this->assertSame(2, $r['total']['audio_copied'], 'the cover and slide one');
            $this->assertSame('audio/cover.mp3', $a->fresh()->cover_audio);
            $slides = $a->slides()->orderBy('story_slides.sort_order')->get();
            $this->assertSame('audio/one.mp3', $slides[0]->audio);
            $this->assertSame('approved', $slides[0]->audio_state);
            $this->assertNull($slides[1]->audio, 'a slide that does not match is left alone');

            // again: nothing more to copy, and a slide that has audio already is never overwritten
            $slides[0]->update(['audio' => 'audio/mine.mp3', 'audio_state' => 'draft']);
            (new OldStoryImport)->moveAudio();
            $this->assertSame('audio/mine.mp3', $slides[0]->fresh()->audio);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function test_a_story_that_is_not_here_is_reported_not_made(): void
    {
        $dir = $this->fakeOldStoryWithAudio();
        try {
            $r = (new OldStoryImport)->checkAudio();

            $this->assertSame(1, $r['total']['stories_not_moved']);
            $this->assertSame(0, $r['total']['stories_matched']);
            $this->assertSame(0, Story::count());
        } finally {
            $this->removeDir($dir);
        }
    }

    public function test_the_audio_command_and_the_page_buttons_work(): void
    {
        $dir = $this->fakeOldStoryWithAudio();
        try {
            $cat = Category::create(['name' => ['en' => 'About Islam'], 'slug' => 'about-islam']);
            $a = Story::create(['title' => ['en' => 'A story'], 'slug' => 'a-story-moved', 'category_id' => $cat->id, 'custom_data' => ['old_id' => 'T-14'], 'status' => 'published']);
            $a->chapters()->create(['title' => ['en' => 'First'], 'sort_order' => 1])->slides()->create(['heading' => ['en' => 'Slide one'], 'sort_order' => 1]);

            $this->artisan('site:move-audio-from-old', ['--check' => true])->expectsOutputToContain('Would copy: 2 audio files')->assertSuccessful();
            $this->assertNull($a->fresh()->cover_audio);

            $this->superuser();
            Livewire::test(MoveFromOldSite::class)
                ->call('checkAudio')->assertSee('Would copy')->assertSee('Bring the audio')
                ->call('moveAudio')->assertSee('Copied');
            $this->assertSame('audio/cover.mp3', $a->fresh()->cover_audio);
        } finally {
            $this->removeDir($dir);
        }
    }
}
