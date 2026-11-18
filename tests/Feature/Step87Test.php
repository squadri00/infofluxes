<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryMaker;
use App\Models\Setting;
use App\Models\User;
use App\Support\StoryMaker\Prompt;
use App\Support\StoryMaker\Reader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 87: the usual length is Story, and the AI can decide the length and the number of slides (open flow). */
class Step87Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
    }

    public function test_the_usual_length_is_story_and_the_open_flow_is_off(): void
    {
        Livewire::test(StoryMaker::class)->assertSet('length', 'story')->assertSet('openFlow', true)->set('openFlow', false)->assertSee('exactly 8 slides in all');
    }

    public function test_open_flow_lets_the_ai_decide_and_is_remembered(): void
    {
        $page = Livewire::test(StoryMaker::class)->set('topic', 'The walk')->set('openFlow', true)
            ->assertSee('as many slides as the story needs')->assertSee('you decide how long each slide is')->assertDontSee('exactly 8 slides in all');
        $this->assertStringNotContainsString('{{', $page->instance()->prompt());
        $this->assertStringContainsString('Let the story decide, not a number.', $page->instance()->prompt());
        $this->assertStringNotContainsString('Use exactly that number', $page->instance()->prompt());

        $page->call('saveRun');
        $run = \App\Models\StoryRun::first();
        $this->assertTrue($run->options['open']);

        Livewire::test(StoryMaker::class)->call('openRun', $run->id)->assertSet('openFlow', true)->call('newRun')->assertSet('openFlow', true);
    }

    public function test_the_check_does_not_ask_for_a_number_in_open_flow(): void
    {
        $body = implode(' ', array_fill(0, 12, 'He walked up the quiet hill and looked at the sky.'));   // about 120 words
        $answer = "TYPE: story\nCATEGORY: Stories\nTITLE: Open\nSHORT: A walk.\nSTYLE: Warm\nCHAPTER: One\nSLIDE\nHEADING: A\nBODY: {$body}\nPICTURE: p\nALT: a\nCHAPTER: Closing and final remarks\nSLIDE\nHEADING: B\nBODY: {$body}\nPICTURE: p\nALT: a\nEND";

        $w = Reader::read($answer, null, 8, 'short', true)['warnings'];
        $this->assertEmpty(array_filter($w, fn ($x) => str_contains($x, 'You asked for') || str_contains($x, 'is long')));
        $w = Reader::read($answer, null, 8, 'short', false)['warnings'];
        $this->assertNotEmpty(array_filter($w, fn ($x) => str_contains($x, 'You asked for') || str_contains($x, 'is long')));
    }

    public function test_the_prompt_ends_by_asking_for_one_single_code_block(): void
    {
        $this->assertStringEndsWith('no other block, heading or comment is used.', trim(Prompt::english(['topic' => 'The walk'])));
    }

    public function test_pictures_already_on_the_slides_can_be_moved_without_uploading_again(): void
    {
        $cat = \App\Models\Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
        $story = \App\Models\Story::create(['title' => ['en' => 'The Walk'], 'slug' => 'the-walk', 'category_id' => $cat->id, 'status' => 'draft']);
        $ch = $story->chapters()->create(['title' => ['en' => 'One'], 'sort_order' => 1]);
        $s1 = $ch->slides()->create(['sort_order' => 1, 'heading' => ['en' => 'A'], 'body' => ['en' => 'a'], 'image' => 'stories/b.webp']);
        $s2 = $ch->slides()->create(['sort_order' => 2, 'heading' => ['en' => 'B'], 'body' => ['en' => 'b'], 'image' => 'stories/a.webp']);
        $run = \App\Models\StoryRun::create(['kind' => 'story', 'topic' => 'x', 'story_id' => $story->id, 'status' => 'saved', 'options' => []]);

        $page = Livewire::test(StoryMaker::class)->call('openPictures', $run->id)->assertSee('Pictures now on the slides');
        $page->call('moveSlidePicture', $s1->id, 1);
        $this->assertSame('stories/a.webp', $s1->fresh()->image);
        $this->assertSame('stories/b.webp', $s2->fresh()->image);

        $page->call('moveSlidePicture', $s1->id, -1);   // the first slide has nothing before it: nothing changes
        $this->assertSame('stories/a.webp', $s1->fresh()->image);
    }

    public function test_a_picture_can_be_dragged_to_another_slide_and_the_others_shift(): void
    {
        $cat = \App\Models\Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
        $story = \App\Models\Story::create(['title' => ['en' => 'The Walk'], 'slug' => 'the-walk', 'category_id' => $cat->id, 'status' => 'draft']);
        $ch = $story->chapters()->create(['title' => ['en' => 'One'], 'sort_order' => 1]);
        $ids = [];
        foreach (['a', 'b', 'c', 'd'] as $i => $k) {
            $ids[] = $ch->slides()->create(['sort_order' => $i + 1, 'heading' => ['en' => strtoupper($k)], 'body' => ['en' => $k], 'image' => "stories/{$k}.webp"])->id;
        }
        $run = \App\Models\StoryRun::create(['kind' => 'story', 'topic' => 'x', 'story_id' => $story->id, 'status' => 'saved', 'options' => []]);
        $page = Livewire::test(StoryMaker::class)->call('openPictures', $run->id)->assertSeeHtml('draggable="true"');
        $images = fn () => \App\Models\StorySlide::whereIn('id', $ids)->orderBy('sort_order')->pluck('image')->map(fn ($i) => basename($i, '.webp'))->all();

        $page->call('dragSlidePicture', 4, 1);                  // d goes to the first place
        $this->assertSame(['d', 'a', 'b', 'c'], $images());
        $page->call('dragSlidePicture', 1, 3);
        $this->assertSame(['a', 'b', 'd', 'c'], $images());
        $page->call('dragSlidePicture', 2, 2)->call('dragSlidePicture', 9, 1)->call('dragSlidePicture', 0, 1);   // nothing to do
        $this->assertSame(['a', 'b', 'd', 'c'], $images());
        $this->assertSame(['A', 'B', 'C', 'D'], \App\Models\StorySlide::whereIn('id', $ids)->orderBy('sort_order')->get()->map(fn ($s) => $s->getTranslation('heading', 'en'))->all(), 'the words stay');
    }
}
