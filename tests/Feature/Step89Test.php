<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryMaker;
use App\Models\Setting;
use App\Models\StoryRun;
use App\Models\User;
use App\Support\StoryMaker\Prompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 89: a story text the person already has can be put into the shape of the Story maker. */
class Step89Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
    }

    public function test_a_text_the_person_already_has_gets_a_prompt_that_only_puts_it_into_shape(): void
    {
        $own = "The Enemy We Cannot See\n\nIblees refused to bow. Allah (SWT) said: \"Get out\" (Quran 7:13).";
        $p = Prompt::convert(['own_text' => $own, 'open' => true]);

        $this->assertStringContainsString('Iblees refused to bow.', $p);
        $this->assertStringContainsString('(Quran 7:13)', $p);
        $this->assertStringContainsString('KEEP EVERY FACT', $p);
        $this->assertStringContainsString('ADD NOTHING NEW', $p);
        $this->assertStringContainsString('lists of sources', $p);
        $this->assertStringContainsString('TYPE: story', $p);
        $this->assertStringContainsString('as many slides as the story needs', $p);
        $this->assertStringNotContainsString('{{', $p);
        $this->assertStringEndsWith('no other block, heading or comment is used.', trim($p));
    }

    public function test_a_fixed_number_of_slides_is_asked_for_when_open_flow_is_off(): void
    {
        $p = Prompt::convert(['own_text' => 'A story.', 'slides' => 7, 'open' => false]);

        $this->assertStringContainsString('exactly 7 slides in all', $p);
    }

    public function test_the_story_maker_uses_the_convert_prompt_when_a_text_is_pasted_and_remembers_it(): void
    {
        $page = Livewire::test(StoryMaker::class)->set('topic', 'Iblees');
        $this->assertStringContainsString('THE STORY TO WRITE', $page->instance()->prompt());

        $page->set('ownText', 'My own story about Iblees.')->assertSee('put my text into shape');
        $this->assertStringContainsString('THE TEXT TO PUT INTO SHAPE', $page->instance()->prompt());
        $this->assertStringContainsString('My own story about Iblees.', $page->instance()->prompt());

        $page->call('saveRun');
        $run = StoryRun::first();
        $this->assertSame('My own story about Iblees.', $run->options['own_text']);
        Livewire::test(StoryMaker::class)->call('openRun', $run->id)->assertSet('ownText', 'My own story about Iblees.')->call('newRun')->assertSet('ownText', '');
    }
}
