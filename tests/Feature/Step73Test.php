<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryMaker;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Models\StoryRun;
use App\Models\User;
use App\Support\StoryMaker\Pictures;
use App\Support\StoryMaker\Reader;
use App\Support\StoryMaker\Saver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 73: the Story maker, bulk pictures for a story (cover and slides, in order). */
class Step73Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Storage::fake('public');
        $this->cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
    }

    private function answer(int $slides): string
    {
        $out = "TYPE: story\nTITLE: The Walk to the Hill\nSHORT: A short story.\nSTYLE: Warm illustration\nCHAPTER: The opening\n";
        for ($n = 1; $n <= $slides - 1; $n++) {
            $out .= "SLIDE\nHEADING: Part {$n}\nBODY: A man walked to the hill. He was tired but happy. The sun was low in the sky. He sat down and looked at the town.\nPICTURE: Picture line {$n}\nALT: Alt {$n}\n";
        }
        $out .= "CHAPTER: Closing and final remarks\nSLIDE\nHEADING: The end\nBODY: The man went home. He was glad. The day was done and the night was calm and quiet.\nPICTURE: Picture line end\nALT: Alt end\nEND";

        return $out;
    }

    private function story(int $slides = 4): Story
    {
        $run = StoryRun::create(['kind' => 'story']);
        $story = Saver::save(Reader::read($this->answer($slides))['story'], $this->cat->id, $run, null);
        $run->update(['story_id' => $story->id]);

        return $story;
    }

    private function img(string $name, int $w = 1600, int $h = 900): UploadedFile
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    // ---------- the plan ----------

    public function test_the_places_are_the_cover_and_then_every_slide_with_its_picture_line(): void
    {
        $slots = Pictures::slots($this->story(4));

        $this->assertCount(5, $slots);
        $this->assertSame('cover', $slots[0]['type']);
        $this->assertSame('Slide 1', $slots[1]['label']);
        $this->assertSame('Picture line 1', $slots[1]['picture']);
        $this->assertSame('Slide 4', $slots[4]['label']);
        $this->assertFalse($slots[1]['has']);
    }

    public function test_files_are_put_in_order_by_name_with_numbers_read_as_numbers(): void
    {
        $files = [$this->img('10.jpg'), $this->img('2.jpg'), $this->img('1.jpg'), $this->img('Image_b.jpg'), $this->img('image_a.jpg')];
        $names = fn (array $f) => array_map(fn ($x) => $x->getClientOriginalName(), $f);

        $this->assertSame(['1.jpg', '2.jpg', '10.jpg', 'image_a.jpg', 'Image_b.jpg'], $names(Pictures::order($files, 'name')));
        $this->assertSame(['10.jpg', '2.jpg', '1.jpg', 'Image_b.jpg', 'image_a.jpg'], $names(Pictures::order($files, 'chosen')));
    }

    public function test_the_first_file_is_the_cover_and_the_rest_fill_the_slides_in_order(): void
    {
        $plan = Pictures::plan($this->story(4), 5, true, false);

        $this->assertSame([0, 1, 2, 3, 4], array_column($plan['rows'], 'file'));
        $this->assertSame([], $plan['extra']);
        $this->assertSame([], $plan['empty']);
    }

    public function test_too_few_files_leave_the_last_slides_empty_and_too_many_are_left_over(): void
    {
        $story = $this->story(4);

        $few = Pictures::plan($story, 3, true, false);
        $this->assertSame(['Slide 3', 'Slide 4'], $few['empty']);

        $many = Pictures::plan($story, 7, true, false);
        $this->assertSame([5, 6], $many['extra']);
    }

    public function test_without_a_cover_the_first_file_goes_to_the_first_slide(): void
    {
        $plan = Pictures::plan($this->story(4), 4, false, false);
        $this->assertSame([null, 0, 1, 2, 3], array_column($plan['rows'], 'file'));
        $this->assertSame('skip', $plan['rows'][0]['state']);
    }

    public function test_pictures_go_to_the_slides_that_have_none_unless_replace_is_asked_for(): void
    {
        $story = $this->story(4);
        $story->slides()->orderBy('story_slides.id')->first()->update(['image' => 'stories/old.webp']);   // slide 1 has a picture

        $keep = Pictures::plan($story->fresh(), 3, false, false);
        $this->assertSame('kept', $keep['rows'][1]['state']);
        $this->assertSame([null, null, 0, 1, 2], array_column($keep['rows'], 'file'), 'the three files go to slides 2, 3 and 4');

        $replace = Pictures::plan($story->fresh(), 4, false, true);
        $this->assertSame('replace', $replace['rows'][1]['state']);
        $this->assertSame([null, 0, 1, 2, 3], array_column($replace['rows'], 'file'));
    }

    // ---------- putting the pictures on ----------

    public function test_the_pictures_are_saved_light_in_the_story_shape_on_the_cover_and_the_slides(): void
    {
        $story = $this->story(4);
        $report = Pictures::apply($story, [$this->img('1.jpg'), $this->img('2.jpg', 800, 800), $this->img('3.png'), $this->img('4.jpg'), $this->img('5.jpg')], true, false);

        $this->assertTrue($report['cover']);
        $this->assertSame(4, $report['slides']);
        $this->assertSame([], $report['problems']);

        $story->refresh();
        $this->assertStringStartsWith('stories/', $story->main_image);
        $this->assertStringEndsWith('.webp', $story->main_image);
        Storage::disk('public')->assertExists($story->main_image);
        $this->assertSame('The Walk to the Hill', $story->getTranslation('main_image_alt', 'en'), 'the cover gets the title as its description');
        foreach ($story->slides()->get() as $slide) {
            $this->assertStringEndsWith('.webp', $slide->image);
            Storage::disk('public')->assertExists($slide->image);
            [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($slide->image));
            $this->assertEqualsWithDelta(16 / 9, $w / $h, 0.02, 'cut to the story shape, even a square picture');
        }
    }

    public function test_a_replaced_picture_is_taken_off_the_disk_and_a_picture_that_cannot_be_read_is_skipped_with_a_message(): void
    {
        $story = $this->story(3);
        Storage::disk('public')->put('stories/old.webp', 'x');
        $first = $story->slides()->orderBy('story_slides.id')->first();
        $first->update(['image' => 'stories/old.webp']);

        $report = Pictures::apply($story->fresh(), [UploadedFile::fake()->createWithContent('bad.jpg', 'this is not a picture'), $this->img('2.jpg')], false, true);

        $this->assertCount(1, $report['problems']);
        $this->assertStringContainsString('bad.jpg', $report['problems'][0]);
        $this->assertSame(1, $report['slides'], 'the good picture is still saved');
        Storage::disk('public')->assertExists('stories/old.webp');   // the bad file did not replace slide 1, so its picture stays
        $this->assertSame('stories/old.webp', $first->fresh()->image);

        $second = Pictures::apply($story->fresh(), [$this->img('3.jpg')], false, true);
        $this->assertSame(1, $second['replaced']);
        Storage::disk('public')->assertMissing('stories/old.webp');
    }

    public function test_the_picture_lines_come_with_the_look_line_first(): void
    {
        $text = Pictures::lines($this->story(3), 'Warm illustration');
        $this->assertStringStartsWith('Look for all the pictures: Warm illustration', $text);
        $this->assertStringContainsString('1. Picture line 1', $text);
        $this->assertStringContainsString('3. Picture line end', $text);
    }

    // ---------- the screen ----------

    public function test_saving_a_story_opens_its_pictures_and_the_pictures_can_be_added_there(): void
    {
        $this->superuser();
        $page = Livewire::test(StoryMaker::class)
            ->set('topic', 'The walk')->set('categoryId', $this->cat->id)->set('slides', 4)->set('paste', $this->answer(4))
            ->call('saveStory');

        $story = Story::first();
        $page->assertSet('pictureStoryId', $story->id)->assertSet('firstIsCover', true)->assertSee('Step 4. Pictures')->assertSee('Picture line 1');

        $page->set('pictures', [$this->img('03.jpg'), $this->img('01.jpg'), $this->img('02.jpg'), $this->img('04.jpg'), $this->img('05.jpg')])
            ->call('applyPictures')->assertHasNoErrors();

        $story->refresh();
        $this->assertNotNull($story->main_image);
        $this->assertSame(4, $story->slides()->whereNotNull('image')->count());
        $this->assertSame([], $page->get('pictures'));
        $this->assertSame(4, $page->get('pictureReport')['slides']);
        $page->assertSet('firstIsCover', false);
    }

    public function test_files_that_are_not_pictures_are_refused_and_nothing_is_put_on(): void
    {
        $this->superuser();
        $story = $this->story(3);
        $page = Livewire::test(StoryMaker::class);
        $run = StoryRun::first();
        $page->call('openPictures', $run->id)->assertSet('pictureStoryId', $story->id);

        $page->set('pictures', [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->call('applyPictures');
        $this->assertSame(0, $story->fresh()->slides()->whereNotNull('image')->count());
        $this->assertNull($page->get('pictureReport'));
    }

    public function test_the_order_can_be_changed_with_the_arrows_and_a_picture_can_be_taken_out(): void
    {
        $this->superuser();
        $story = $this->story(3);
        $page = Livewire::test(StoryMaker::class)->call('openPictures', StoryRun::first()->id)
            ->set('pictures', [$this->img('a.jpg'), $this->img('b.jpg'), $this->img('c.jpg')]);
        $names = fn () => array_map(fn ($f) => $f->getClientOriginalName(), $page->instance()->orderedPictures());

        $this->assertSame(['a.jpg', 'b.jpg', 'c.jpg'], $names());
        $page->call('movePicture', 0, 1);
        $this->assertSame(['b.jpg', 'a.jpg', 'c.jpg'], $names());
        $page->call('removePicture', 1);
        $this->assertSame(['b.jpg', 'c.jpg'], $names());
        $page->assertSet('pictureOrder', 'chosen');
        $page->call('clearPictures')->assertSet('pictures', []);
        $this->assertSame(0, $story->slides()->whereNotNull('image')->count());
    }

    public function test_the_pictures_step_can_be_closed_and_a_run_without_a_story_has_no_pictures_button(): void
    {
        $this->superuser();
        $this->story(3);
        $lone = StoryRun::create(['kind' => 'story', 'topic' => 'Only a prompt']);

        $page = Livewire::test(StoryMaker::class)->call('openPictures', $lone->id)->assertSet('pictureStoryId', null);
        $page->call('openPictures', StoryRun::whereNotNull('story_id')->first()->id)->assertSet('pictureStoryId', Story::first()->id);
        $page->call('closePictures')->assertSet('pictureStoryId', null);
    }

    public function test_the_picture_and_story_maker_files_stay_separate_from_the_article_tools(): void
    {
        $code = file_get_contents(app_path('Support/StoryMaker/Pictures.php')).file_get_contents(app_path('Filament/Pages/StoryMaker.php'));
        $this->assertStringNotContainsString('AiImport', $code);
        $this->assertStringNotContainsString('AiPrompt', $code);
    }
}
