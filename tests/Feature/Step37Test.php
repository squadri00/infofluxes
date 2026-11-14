<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\ReviewChecklist;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 37: the review checklist stands between an AI-written draft and publishing. */
class Step37Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function draft(array $custom = []): Article
    {
        return Article::create([
            'title' => ['en' => 'A draft'], 'slug' => 'a-draft', 'category_id' => $this->cat->id, 'status' => 'draft', 'ai_assisted' => true,
            'custom_data' => array_merge(['sources' => [['type' => 'primary', 'ref' => 'A book', 'url' => 'https://example.org']], 'please_check' => ['The date', 'A hadith grade']], $custom),
        ]);
    }

    private function mod(): User
    {
        $u = User::factory()->create(['role' => 'moderator', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    public function test_the_lines_left_are_counted(): void
    {
        $a = $this->draft();

        $this->assertCount(count(ReviewChecklist::standard()) + 2, ReviewChecklist::left($a->custom_data));
        $this->assertSame([], ReviewChecklist::left(['review' => ['done' => array_keys(ReviewChecklist::standard())]]));
        $this->assertCount(1, ReviewChecklist::left(['please_check' => ['x'], 'review' => ['done' => array_keys(ReviewChecklist::standard())]]));
    }

    public function test_the_checklist_shows_on_a_draft_written_with_ai_help_and_not_on_an_ordinary_article(): void
    {
        $this->mod();
        $a = $this->draft();

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->assertSee(__('review.section'))->assertSee('The date')->assertSee('A hadith grade')->assertSee(__('review.items.verses'));

        $plain = Article::create(['title' => ['en' => 'Plain'], 'slug' => 'plain', 'category_id' => $this->cat->id]);
        Livewire::test(EditArticle::class, ['record' => $plain->getRouteKey()])->assertDontSee(__('review.section'));
    }

    public function test_it_cannot_be_published_with_unticked_lines_even_with_a_reviewer_name(): void
    {
        $this->mod();
        $a = $this->draft();

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->fillForm(['status' => 'published', 'reviewer_name' => 'Dr Check', 'custom_data' => ['review' => ['done' => array_keys(ReviewChecklist::standard())]]])->call('save');

        $this->assertSame('draft', $a->fresh()->status, 'the two points of the AI are not ticked');
    }

    public function test_it_is_published_when_everything_is_ticked_and_the_reviewer_is_recorded(): void
    {
        $this->mod();
        $a = $this->draft();

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->fillForm(['status' => 'published', 'reviewer_name' => 'Dr Check', 'custom_data' => ['review' => [
                'done' => array_keys(ReviewChecklist::standard()), 'points_done' => ['0', '1'],
            ]]])->call('save')->assertHasNoFormErrors();

        $a = $a->fresh();
        $this->assertSame('published', $a->status);
        $this->assertSame('Dr Check', $a->custom_data['review']['reviewed_by']);
        $this->assertNotEmpty($a->custom_data['review']['reviewed_at']);
    }

    public function test_saving_the_form_keeps_the_sources_and_the_ai_points(): void
    {
        $this->mod();
        $a = $this->draft();

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->fillForm(['custom_data' => ['review' => ['done' => ['verses']]]])->call('save')->assertHasNoFormErrors();

        $a = $a->fresh();
        $this->assertSame('A book', $a->custom_data['sources'][0]['ref']);
        $this->assertSame(['The date', 'A hadith grade'], $a->custom_data['please_check']);
        $this->assertSame(['verses'], $a->custom_data['review']['done']);
    }

    public function test_an_already_published_article_is_not_blocked_again(): void
    {
        $this->mod();
        $a = $this->draft();
        $a->update(['status' => 'published', 'publish_date' => now()->subDay()]);

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])->fillForm(['sort_order' => 3])->call('save')->assertHasNoFormErrors();

        $this->assertSame('published', $a->fresh()->status);
    }

    public function test_an_author_still_cannot_publish_even_with_everything_ticked(): void
    {
        $author = User::factory()->create(['role' => 'author', 'status' => 'active']);
        $this->actingAs($author);
        $a = $this->draft();
        $a->update(['author_id' => $author->id]);

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->fillForm(['status' => 'published', 'reviewer_name' => 'Me', 'custom_data' => ['review' => ['done' => array_keys(ReviewChecklist::standard()), 'points_done' => ['0', '1']]]])->call('save');

        $this->assertSame('draft', $a->fresh()->status);
    }
}
