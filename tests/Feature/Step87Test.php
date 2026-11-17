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
        Livewire::test(StoryMaker::class)->assertSet('length', 'story')->assertSet('openFlow', false)->assertSee('exactly 8 slides in all');
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

        Livewire::test(StoryMaker::class)->call('openRun', $run->id)->assertSet('openFlow', true)->call('newRun')->assertSet('openFlow', false);
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
}
