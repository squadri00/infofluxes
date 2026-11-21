<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryMaker;
use App\Filament\Resources\Stories\Pages\EditStory;
use App\Filament\Resources\Stories\Pages\ListStories;
use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 101: stories go only in last-level categories or in the tabs of a tab section; the tab section is for stories only; drafts have a Preview. */
class Step101Test extends TestCase
{
    use RefreshDatabase;

    private Category $pictures;
    private Category $tab;
    private Category $middle;
    private Category $leaf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pictures = Category::create(['name' => ['en' => 'Stories in pictures'], 'slug' => 'stories-in-pictures', 'custom_data' => ['topic_tabs' => true]]);
        $this->tab = Category::create(['name' => ['en' => 'Quranic Stories'], 'slug' => 'quranic-stories', 'parent_id' => $this->pictures->id]);
        $this->middle = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);
        $this->leaf = Category::create(['name' => ['en' => 'Conquests'], 'slug' => 'conquests', 'parent_id' => $this->middle->id]);
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'superuser', 'status' => 'active']);
    }

    public function test_story_choices_are_last_level_categories_and_the_tabs_only(): void
    {
        $ids = array_keys(Category::storyChoices());

        $this->assertContains($this->leaf->id, $ids);
        $this->assertContains($this->tab->id, $ids);
        $this->assertNotContains($this->pictures->id, $ids);
        $this->assertNotContains($this->middle->id, $ids);
    }

    public function test_the_tab_section_is_for_stories_only_so_articles_cannot_go_there(): void
    {
        $ids = array_keys(Category::leafChoices());

        $this->assertContains($this->leaf->id, $ids);
        $this->assertNotContains($this->tab->id, $ids);
        $this->assertNotContains($this->pictures->id, $ids);
    }

    public function test_the_story_maker_only_offers_those_categories(): void
    {
        $this->actingAs($this->staff());
        $offered = array_keys(Livewire::test(StoryMaker::class)->instance()->categoryChoices());

        $this->assertContains($this->tab->id, $offered);
        $this->assertNotContains($this->pictures->id, $offered);
        $this->assertNotContains($this->middle->id, $offered);
    }

    public function test_a_saved_draft_has_a_preview_in_the_story_maker_the_list_and_the_edit_page(): void
    {
        $this->actingAs($this->staff());
        $story = Story::create(['title' => ['en' => 'A story draft'], 'slug' => 'a-story-draft', 'category_id' => $this->tab->id, 'status' => 'draft']);
        $url = route('story.show', 'a-story-draft');

        Livewire::test(ListStories::class)->assertTableActionHasUrl('view', $url, $story)->assertTableActionHasLabel('view', 'Preview', $story);
        Livewire::test(EditStory::class, ['record' => $story->id])->assertActionHasUrl('preview', $url);
        $this->get('/stories/a-story-draft')->assertOk();
    }
}
