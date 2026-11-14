<?php

namespace Tests\Feature;

use App\Filament\Pages\MoveFromOldSite;
use App\Models\Story;
use App\Models\Category;
use App\Models\User;
use App\Support\OldStoryImport;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 12: moving the old slide stories, with their pictures, into this site. The old site is a small fake one here. */
class Step12Test extends TestCase
{
    use RefreshDatabase;

    private string $oldPath;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Storage::fake('public');
        $this->cat = Category::create(['name' => ['en' => 'About Islam'], 'slug' => 'about-islam']);

        $this->oldPath = sys_get_temp_dir().'/oldsite-'.uniqid();
        mkdir($this->oldPath.'/storage/app/public/stories', 0777, true);
        config(['infofluxes.old_site_path' => $this->oldPath, 'database.connections.old_site' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('old_site');
        $old = Schema::connection('old_site');
        $old->create('index_entries', fn (Blueprint $t) => $t->id());
        $old->create('topics', function (Blueprint $t) {
            $t->id(); $t->string('public_id'); $t->string('title'); $t->text('summary')->nullable(); $t->text('hook')->nullable(); $t->text('context')->nullable();
            $t->string('master_image')->nullable(); $t->string('master_image_alt')->nullable(); $t->string('master_image_credit')->nullable(); $t->string('master_image_licence')->nullable();
            $t->string('status')->default('draft'); $t->dateTime('published_at')->nullable(); $t->boolean('ai_assisted')->default(false); $t->string('ai_reviewed_by')->nullable(); $t->softDeletes();
        });
        $old->create('story_chapters', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('topic_id'); $t->string('title'); $t->unsignedInteger('sort_order')->default(0);
        });
        $old->create('story_slides', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('story_chapter_id'); $t->unsignedInteger('sort_order')->default(0); $t->string('image')->nullable(); $t->string('image_alt')->nullable();
            $t->string('image_credit')->nullable(); $t->string('image_licence')->nullable(); $t->string('heading'); $t->text('body')->nullable();
        });
    }

    protected function tearDown(): void
    {
        // remove the fake old files
        foreach (glob($this->oldPath.'/storage/app/public/stories/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach ([$this->oldPath.'/storage/app/public/stories', $this->oldPath.'/storage/app/public', $this->oldPath.'/storage/app', $this->oldPath.'/storage', $this->oldPath] as $d) {
            @rmdir($d);
        }
        parent::tearDown();
    }

    private function oldFile(string $name, string $content = 'binary-picture'): string
    {
        file_put_contents($this->oldPath.'/storage/app/public/'.$name, $content);

        return $name;
    }

    /** One old story: 2 chapters, 3 slides, a cover picture and two slide pictures. */
    private function oldStory(string $id = 'T-14', string $title = 'What happened before the Night Journey?', array $extra = []): int
    {
        $db = DB::connection('old_site');
        $topic = $db->table('topics')->insertGetId(array_merge([
            'public_id' => $id, 'title' => $title, 'summary' => 'A short description.', 'hook' => '<p>The hook words.</p>', 'context' => '<p>The context words.</p>',
            'master_image' => $this->oldFile("stories/cover-$id.webp"), 'master_image_alt' => 'A cover', 'master_image_credit' => 'Own work', 'master_image_licence' => 'CC BY',
            'status' => 'published', 'published_at' => '2026-10-05 12:00:00', 'ai_assisted' => 0,
        ], $extra));
        $c2 = $db->table('story_chapters')->insertGetId(['topic_id' => $topic, 'title' => 'Second chapter', 'sort_order' => 2]);
        $c1 = $db->table('story_chapters')->insertGetId(['topic_id' => $topic, 'title' => 'First chapter', 'sort_order' => 1]);
        $blank = ['image' => null, 'image_alt' => null, 'image_credit' => null, 'image_licence' => null, 'body' => null];
        $db->table('story_slides')->insert([
            array_merge($blank, ['story_chapter_id' => $c1, 'sort_order' => 2, 'heading' => 'Slide B', 'body' => '<p>Para one.</p><p>Para two &amp; more.</p>', 'image' => $this->oldFile("stories/b-$id.webp")]),
            array_merge($blank, ['story_chapter_id' => $c1, 'sort_order' => 1, 'heading' => 'Slide A', 'body' => '<p>Only one.</p>', 'image' => $this->oldFile("stories/a-$id.webp"), 'image_alt' => 'A map', 'image_credit' => 'Inforfluxes', 'image_licence' => 'Mine']),
            array_merge($blank, ['story_chapter_id' => $c2, 'sort_order' => 1, 'heading' => 'Slide C']),
        ]);

        return $topic;
    }

    private function run_(bool $move = true): array
    {
        $import = new OldStoryImport($this->cat->id);

        return $move ? $import->move(User::factory()->create(['role' => 'superuser', 'status' => 'active'])) : $import->check();
    }

    public function test_checking_counts_and_saves_nothing_and_copies_nothing(): void
    {
        $this->oldStory();

        $r = $this->run_(false);

        $this->assertTrue($r['dry']);
        $this->assertSame(['stories_new' => 1, 'stories_existing' => 0, 'stories_same_title' => 0, 'chapters' => 2, 'slides' => 3, 'images_copied' => 3, 'images_missing' => 0, 'published' => 1, 'drafts' => 0, 'audio_copied' => 0, 'audio_missing' => 0], $r['total']);
        $this->assertSame(0, Story::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_moving_makes_an_article_with_chapters_slides_and_pictures_in_the_right_order(): void
    {
        $this->oldStory();

        $this->run_();

        $a = Story::where('custom_data->old_id', 'T-14')->first();
        $this->assertSame('What happened before the Night Journey?', $a->mainTitle());
        $this->assertSame($this->cat->id, $a->category_id);
        $this->assertSame('A short description.', $a->getTranslation('short_detail', 'en'));
        $this->assertSame('<p>The hook words.</p><p>The context words.</p>', $a->getTranslation('long_detail', 'en'));
        $this->assertSame('Own work', $a->main_image_credit);
        $this->assertSame('CC BY', $a->main_image_licence);
        $this->assertSame('A cover', $a->getTranslation('main_image_alt', 'en'));
        $this->assertSame(['First chapter', 'Second chapter'], $a->chapters->map->getTranslation('title', 'en')->all());
        $this->assertSame(['Slide A', 'Slide B'], $a->chapters->first()->slides->map->getTranslation('heading', 'en')->all());
        $this->assertSame(['Slide C'], $a->chapters->last()->slides->map->getTranslation('heading', 'en')->all());
        $this->assertTrue($a->hasSlides());
    }

    public function test_pictures_are_copied_with_the_same_names_and_credits_are_kept(): void
    {
        $this->oldStory();
        $this->run_();

        $a = Story::where('custom_data->old_id', 'T-14')->first();
        $this->assertSame('stories/cover-T-14.webp', $a->main_image);
        Storage::disk('public')->assertExists('stories/cover-T-14.webp');
        Storage::disk('public')->assertExists('stories/a-T-14.webp');
        $this->assertSame('binary-picture', Storage::disk('public')->get('stories/a-T-14.webp'), 'copied byte for byte');
        $first = $a->chapters->first()->slides->first();
        $this->assertSame('stories/a-T-14.webp', $first->image);
        $this->assertSame('A map', $first->getTranslation('image_alt', 'en'));
        $this->assertSame('Inforfluxes', $first->image_credit);
        $this->assertSame('Mine', $first->image_licence);
        $this->assertNull($a->chapters->last()->slides->first()->image, 'a slide with no picture stays without');
    }

    public function test_old_html_paragraphs_become_plain_text_with_blank_lines(): void
    {
        $this->oldStory();
        $this->run_();

        $slide = Story::where('custom_data->old_id', 'T-14')->first()->chapters->first()->slides->last();
        $this->assertSame("Para one.\n\nPara two & more.", $slide->getTranslation('body', 'en'));
        $this->assertSame('', OldStoryImport::plain(''));
        $this->assertSame("a\nb", OldStoryImport::plain('a<br>b'));
    }

    public function test_a_published_story_is_published_unless_it_is_ai_text_nobody_has_read(): void
    {
        $this->oldStory('T-1', 'Read by a person', ['ai_assisted' => 1, 'ai_reviewed_by' => 'Shamim']);
        $this->oldStory('T-2', 'Never read', ['ai_assisted' => 1, 'ai_reviewed_by' => null]);
        $this->oldStory('T-3', 'Old draft', ['status' => 'draft']);
        $this->oldStory('T-4', 'Written by a person', ['ai_assisted' => 0]);

        $r = $this->run_();

        $this->assertSame('published', Story::where('custom_data->old_id', 'T-1')->first()->status);
        $this->assertSame('Shamim', Story::where('custom_data->old_id', 'T-1')->first()->reviewer_name);
        $this->assertSame('draft', Story::where('custom_data->old_id', 'T-2')->first()->status, 'AI text waits for a person');
        $this->assertTrue(Story::where('custom_data->old_id', 'T-2')->first()->ai_assisted);
        $this->assertSame('draft', Story::where('custom_data->old_id', 'T-3')->first()->status);
        $this->assertSame('published', Story::where('custom_data->old_id', 'T-4')->first()->status);
        $this->assertSame('2026-10-05 12:00:00', Story::where('custom_data->old_id', 'T-4')->first()->publish_date->format('Y-m-d H:i:s'));
        $this->assertSame([2, 2], [$r['total']['published'], $r['total']['drafts']]);
        $this->assertNull(Story::where('custom_data->old_id', 'T-3')->first()->publish_date);
    }

    public function test_a_story_whose_title_is_already_an_article_is_left_alone_and_reported(): void
    {
        $this->oldStory('T-16', 'What is Sidrat al-Muntaha?');
        \App\Models\Article::create(['title' => ['en' => 'what is sidrat al-muntaha?'], 'slug' => 'mine', 'category_id' => $this->cat->id]);

        $r = $this->run_();

        $this->assertSame(1, $r['total']['stories_same_title']);
        $this->assertSame(['What is Sidrat al-Muntaha?'], $r['same_title']);
        $this->assertSame(0, $r['total']['stories_new']);
        $this->assertSame(0, Story::count(), 'no story is made');
        $this->assertSame(1, \App\Models\Article::count(), 'the owner\'s own article is not touched');
    }

    public function test_moving_again_does_not_make_a_story_twice(): void
    {
        $this->oldStory();
        $this->run_();
        $articles = Story::count();
        $slides = \App\Models\StorySlide::count();

        $again = $this->run_();

        $this->assertSame(1, $again['total']['stories_existing']);
        $this->assertSame(0, $again['total']['stories_new']);
        $this->assertSame($articles, Story::count());
        $this->assertSame($slides, \App\Models\StorySlide::count());
    }

    public function test_a_missing_old_picture_is_counted_and_the_slide_is_still_made(): void
    {
        $topic = $this->oldStory();
        unlink($this->oldPath.'/storage/app/public/stories/a-T-14.webp');

        $r = $this->run_();

        $this->assertSame(1, $r['total']['images_missing']);
        $this->assertSame(2, $r['total']['images_copied']);
        $slide = Story::where('custom_data->old_id', 'T-14')->first()->chapters->first()->slides->first();
        $this->assertSame('Slide A', $slide->getTranslation('heading', 'en'));
        $this->assertNull($slide->image);
    }

    public function test_a_picture_path_that_leaves_the_pictures_folder_is_never_read(): void
    {
        $this->oldStory();
        file_put_contents($this->oldPath.'/secret.txt', 'secret');
        DB::connection('old_site')->table('story_slides')->where('heading', 'Slide B')->update(['image' => '../../../secret.txt']);
        DB::connection('old_site')->table('story_slides')->where('heading', 'Slide C')->update(['image' => '/etc/passwd']);

        $r = $this->run_();

        $this->assertSame(2, $r['total']['images_missing']);
        $this->assertSame([], array_filter(Storage::disk('public')->allFiles(), fn ($f) => str_contains($f, 'secret') || str_contains($f, 'passwd')));
        @unlink($this->oldPath.'/secret.txt');
    }

    public function test_only_stories_with_slides_are_moved_and_deleted_ones_are_not(): void
    {
        $this->oldStory('T-1', 'A story with slides');
        DB::connection('old_site')->table('topics')->insert(['public_id' => 'T-24', 'title' => 'A fact file with no slides', 'status' => 'published']);
        $gone = $this->oldStory('T-9', 'A deleted story');
        DB::connection('old_site')->table('topics')->where('id', $gone)->update(['deleted_at' => now()]);
        $empty = DB::connection('old_site')->table('topics')->insertGetId(['public_id' => 'T-30', 'title' => 'Chapter but no slide', 'status' => 'published']);
        DB::connection('old_site')->table('story_chapters')->insert(['topic_id' => $empty, 'title' => 'Empty', 'sort_order' => 1]);

        $r = $this->run_();

        $this->assertSame(['A story with slides'], Story::all()->map->mainTitle()->all());
        $this->assertSame(1, $r['total']['stories_new']);
    }

    public function test_if_anything_fails_no_article_is_left_half_made(): void
    {
        $this->oldStory('T-1', 'First story');
        $this->oldStory('T-2', 'Second story');
        $stop = true;
        \App\Models\StorySlide::creating(function ($slide) use (&$stop) {
            if ($stop && $slide->getTranslation('heading', 'en') === 'Slide C' && Story::count() >= 2) {
                throw new \RuntimeException('stop');
            }
        });

        try {
            $this->run_();
            $this->fail('the move should have stopped');
        } catch (\RuntimeException $e) {
            $this->assertSame('stop', $e->getMessage());
        } finally {
            $stop = false;
        }

        $this->assertSame(0, Story::count());
        $this->assertSame(0, \App\Models\StoryChapter::count());
        $this->assertSame(0, \App\Models\StorySlide::count());
    }

    public function test_a_moved_story_is_shown_with_the_viewer_on_the_public_site(): void
    {
        $this->oldStory();
        $this->run_();

        $html = $this->get(Story::where('custom_data->old_id', 'T-14')->first()->url())->assertOk()->getContent();

        $this->assertStringContainsString('data-story data-total="4"', $html);
        $this->assertStringContainsString('Picture: Inforfluxes', $html);
        $this->assertStringContainsString('Picture: AI Generated', $html, 'no credit: the old rule');
        $this->assertLessThan(strpos($html, 'class="article__body"'), strpos($html, 'data-story'));
    }

    // ---------- the page and the command ----------

    public function test_it_is_available_only_where_the_old_site_and_its_files_are(): void
    {
        $this->assertTrue(OldStoryImport::available());

        config(['infofluxes.old_site_path' => null]);
        $this->assertFalse(OldStoryImport::available());
    }

    public function test_the_page_checks_then_moves_the_stories_into_the_chosen_category(): void
    {
        $this->oldStory();
        $other = Category::create(['name' => ['en' => 'Elsewhere'], 'slug' => 'elsewhere']);
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        $page = Livewire::test(MoveFromOldSite::class)
            ->assertSet('storyCategory', $this->cat->id)
            ->assertSee('The old slide stories')
            ->set('storyCategory', $other->id)
            ->call('checkStories')
            ->assertSee('Would move')->assertSee('1 stories, 2 chapters, 3 slides')
            ->assertSee('Move the stories now');

        $this->assertSame(0, Story::count());

        $page->call('moveStories')->assertSee('Moved');
        $this->assertSame($other->id, Story::where('custom_data->old_id', 'T-14')->first()->category_id);
    }

    public function test_the_page_will_not_move_without_a_category(): void
    {
        $this->oldStory();
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(MoveFromOldSite::class)->set('storyCategory', null)->call('moveStories');

        $this->assertSame(0, Story::count());
    }

    public function test_the_command_checks_and_moves(): void
    {
        $this->oldStory();
        User::factory()->create(['role' => 'superuser', 'status' => 'active']);

        $this->artisan('site:move-stories-from-old', ['--check' => true])->expectsOutputToContain('Would move: 1 stories, 2 chapters, 3 slides')->assertSuccessful();
        $this->assertSame(0, Story::count());

        $this->artisan('site:move-stories-from-old')->expectsOutputToContain('Moved: 1 stories')->assertSuccessful();
        $this->assertSame(1, Story::count());

        $this->artisan('site:move-stories-from-old', ['--category' => 'C-999'])->expectsOutputToContain('The category was not found')->assertFailed();
    }
}
