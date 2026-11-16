<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryTranslationEdit;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Support\StoryMaker\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 77: editing one language of a story by hand (English never changes, a quotation never changes, the status stays). */
class Step77Test extends TestCase
{
    use RefreshDatabase;

    private const VERSE = 'قُلْ هُوَ اللَّهُ أَحَدٌ';

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function story(string $status = 'hidden'): Story
    {
        $cat = Category::create(['name' => ['en' => 'Stories'], 'slug' => 'stories']);
        $story = Story::create(['title' => ['en' => 'The Walk'], 'slug' => 'the-walk', 'category_id' => $cat->id, 'status' => 'draft', 'short_detail' => ['en' => 'A man walks.']]);
        $ch = $story->chapters()->create(['title' => ['en' => 'The opening'], 'sort_order' => 1]);
        $ch->slides()->create(['sort_order' => 1, 'heading' => ['en' => 'The hill'], 'body' => ['en' => "He remembered a verse.\n".self::VERSE."\nHe felt peace."], 'image_alt' => ['en' => 'A hill']]);

        $story->setTranslation('title', 'ur', 'چہل قدمی');
        $story->save();
        $ch->setTranslation('title', 'ur', 'آغاز')->save();
        $ch->slides->first()->setTranslation('heading', 'ur', 'پہاڑی')->setTranslation('body', 'ur', "اسے ایک آیت یاد آئی۔\n".self::VERSE."\nاسے سکون ملا۔")->save();
        Translations::saved($story->fresh(), 'ur', 7, ['walk']);
        if ($status === 'on') {
            Translations::set($story->fresh(), 'ur', 'on');
        }
        User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs(User::where('role', 'superuser')->first());

        return $story->fresh();
    }

    public function test_the_page_opens_with_the_saved_text_and_the_english_beside_it(): void
    {
        $story = $this->story();
        Livewire::withQueryParams(['story' => $story->id, 'lang' => 'ur'])->test(StoryTranslationEdit::class)
            ->assertSet('form.title', 'چہل قدمی')
            ->assertSet('form.chapters.0.title', 'آغاز')
            ->assertSet('form.chapters.0.slides.0.heading', 'پہاڑی')
            ->assertSee('The Walk');
    }

    public function test_a_language_the_story_does_not_have_is_not_found(): void
    {
        $story = $this->story();
        Livewire::withQueryParams(['story' => $story->id, 'lang' => 'fr'])->test(StoryTranslationEdit::class)->assertStatus(404);
    }

    public function test_saving_changes_only_that_language_and_keeps_the_status_and_the_run(): void
    {
        $story = $this->story('on');
        Livewire::withQueryParams(['story' => $story->id, 'lang' => 'ur'])->test(StoryTranslationEdit::class)
            ->set('form.title', 'نئی چہل قدمی')
            ->set('form.chapters.0.slides.0.heading', 'نئی پہاڑی')
            ->call('save');

        $fresh = $story->fresh();
        $this->assertSame('نئی چہل قدمی', $fresh->getTranslation('title', 'ur'));
        $this->assertSame('The Walk', $fresh->getTranslation('title', 'en'), 'English is not touched');
        $this->assertSame('نئی پہاڑی', $fresh->chapters->first()->slides->first()->getTranslation('heading', 'ur'));
        $this->assertSame('The hill', $fresh->chapters->first()->slides->first()->getTranslation('heading', 'en'));
        $row = Translations::all($fresh)['ur'];
        $this->assertSame('on', $row['status'], 'shown stays shown');
        $this->assertSame(7, $row['run']);
        $this->assertSame(['walk'], $row['keywords']);
    }

    public function test_a_changed_quotation_is_not_saved(): void
    {
        $story = $this->story();
        Livewire::withQueryParams(['story' => $story->id, 'lang' => 'ur'])->test(StoryTranslationEdit::class)
            ->set('form.title', 'ایک اور عنوان')
            ->set('form.chapters.0.slides.0.body', "اسے ایک آیت یاد آئی۔\nقُلْ هُوَ اللَّهُ\nاسے سکون ملا۔")
            ->call('save');

        $this->assertSame('چہل قدمی', $story->fresh()->getTranslation('title', 'ur'), 'nothing was saved');
        $this->assertStringContainsString(self::VERSE, $story->fresh()->chapters->first()->slides->first()->getTranslation('body', 'ur'));
    }

    public function test_empty_title_or_slide_text_is_not_saved(): void
    {
        $story = $this->story();
        Livewire::withQueryParams(['story' => $story->id, 'lang' => 'ur'])->test(StoryTranslationEdit::class)
            ->set('form.title', '')->call('save');
        $this->assertSame('چہل قدمی', $story->fresh()->getTranslation('title', 'ur'));
    }

    public function test_the_translations_screen_links_to_the_editor(): void
    {
        $story = $this->story();
        $this->get('/admin/story-translations?story='.$story->id)->assertOk()->assertSee('story-translation-edit?story='.$story->id.'&amp;lang=ur', false);
    }
}
