<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Filament\Pages\CreateContent;
use App\Filament\Pages\StoryMaker;
use App\Filament\Pages\TopicsToWrite;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\People\PersonResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 103: one Create screen and a "+ Create" button for articles and stories; AI import and Story maker leave the side menu; the Organise group. */
class Step103Test extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        $this->actingAs($user);

        return $user;
    }

    public function test_the_create_screen_offers_the_four_ways_to_a_superuser(): void
    {
        $this->as('superuser');

        $this->get('/admin/create')->assertOk()
            ->assertSee('Write an article')->assertSee('Make an article with AI')->assertSee('Make a story in pictures')->assertSee('Make a story with AI')
            ->assertSee('/admin/articles/create', false)->assertSee('/admin/ai-import', false)->assertSee('/admin/stories/create', false)->assertSee('/admin/story-maker', false);
    }

    public function test_an_author_sees_only_the_choices_they_may_use(): void
    {
        $this->as('author');
        $keys = array_column(CreateContent::options(), 'key');

        $this->assertContains('article', $keys);
        $this->assertContains('story', $keys);
        $this->assertNotContains('article_ai', $keys, 'an author has no AI import unless it is ticked');
    }

    public function test_the_create_button_is_at_the_top_of_every_screen_for_staff_and_not_for_others(): void
    {
        $this->as('superuser');
        $this->get('/admin')->assertOk()->assertSee('data-create-menu', false)->assertSee('Write an article');

        auth()->logout();
        $this->as('member');
        $this->get('/admin')->assertStatus(403);
    }

    public function test_ai_import_and_story_maker_are_not_in_the_side_menu_but_still_open(): void
    {
        $this->as('superuser');

        $this->assertFalse(AiImport::shouldRegisterNavigation());
        $this->assertFalse(StoryMaker::shouldRegisterNavigation());
        $this->get('/admin/ai-import')->assertOk();
        $this->get('/admin/story-maker')->assertOk();
        $this->assertTrue(CreateContent::shouldRegisterNavigation());
    }

    public function test_categories_topics_and_contributors_are_in_the_organise_group(): void
    {
        $this->assertSame('Organise', CategoryResource::getNavigationGroup());
        $this->assertSame('Organise', TopicsToWrite::getNavigationGroup());
        $this->assertSame('Organise', PersonResource::getNavigationGroup());
    }
}
