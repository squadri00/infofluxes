<?php

namespace Tests\Feature;

use App\Filament\Pages\TopicsToWrite;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\TopicAssignments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 94: topics to write: everybody on the staff sees them, takes a free one or gives it, and a taken one is protected. */
class Step94Test extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function topic(string $title = 'A topic', array $extra = []): Article
    {
        $cat = Category::query()->first() ?? Category::create(['name' => ['en' => 'Worship'], 'slug' => 'worship']);

        return Article::create(array_merge([
            'title' => ['en' => $title], 'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(), 'category_id' => $cat->id, 'status' => 'draft', 'is_index_line' => true,
            'custom_data' => ['subtopic' => 'Prayer > How it is done'], 'short_detail' => ['en' => ''], 'long_detail' => ['en' => ''],
        ], $extra));
    }

    public function test_every_staff_role_sees_the_screen_and_a_member_does_not(): void
    {
        foreach (['superuser', 'moderator', 'author'] as $role) {
            $this->actingAs($this->user($role));
            Livewire::test(TopicsToWrite::class)->assertOk();
            $this->assertTrue(TopicsToWrite::canAccess(), $role);
        }
        $this->actingAs($this->user('member'));
        $this->assertFalse(TopicsToWrite::canAccess());
    }

    public function test_the_screen_lists_free_topics_with_their_subtopic_and_no_text_of_others(): void
    {
        $this->topic('Pillars of prayer');
        $this->topic('Secret written one', ['status' => 'written', 'is_index_line' => false, 'long_detail' => ['en' => '<p>private words</p>']]);
        $this->actingAs($this->user('author'));

        Livewire::test(TopicsToWrite::class)->assertSee('Pillars of prayer')->assertSee('Prayer &gt; How it is done', false)->assertDontSee('Secret written one')->assertDontSee('private words');
    }

    public function test_an_author_takes_a_free_topic_and_becomes_its_author(): void
    {
        $topic = $this->topic();
        $author = $this->user('author');
        $this->actingAs($author);

        Livewire::test(TopicsToWrite::class)->callTableAction('take', $topic);

        $topic->refresh();
        $this->assertSame($author->id, $topic->assigned_to);
        $this->assertSame($author->id, $topic->author_id);
        $this->assertNotNull($topic->assigned_at);
        $this->assertTrue(ArticleResource::canEdit($topic), 'the assigned author may edit it');
    }

    public function test_a_taken_topic_cannot_be_taken_by_another_author(): void
    {
        $one = $this->user('author');
        $two = $this->user('author');
        $topic = $this->topic();
        $this->assertTrue(TopicAssignments::assign($topic, $one->id, $one));

        $this->actingAs($two);
        $this->assertFalse(TopicAssignments::assign($topic->fresh(), $two->id, $two));
        $this->assertFalse(TopicAssignments::canChange($topic->fresh(), $two));
        $this->assertSame($one->id, $topic->fresh()->assigned_to);
    }

    public function test_the_person_who_has_it_and_a_moderator_may_hand_it_back_or_give_it_on(): void
    {
        $one = $this->user('author');
        $two = $this->user('author');
        $mod = $this->user('moderator');
        $topic = $this->topic();
        TopicAssignments::assign($topic, $one->id, $one);

        $this->assertTrue(TopicAssignments::assign($topic->fresh(), $two->id, $one), 'the holder gives it on');
        $this->assertSame($two->id, $topic->fresh()->assigned_to);
        $this->assertSame($two->id, $topic->fresh()->author_id);

        $this->assertTrue(TopicAssignments::assign($topic->fresh(), null, $mod), 'a moderator takes it back');
        $this->assertNull($topic->fresh()->assigned_to);
        $this->assertNull($topic->fresh()->author_id);
    }

    public function test_any_staff_member_can_give_a_free_topic_to_someone_else(): void
    {
        $giver = $this->user('author');
        $receiver = $this->user('author');
        $topic = $this->topic();
        $this->actingAs($giver);

        Livewire::test(TopicsToWrite::class)->callTableAction('assign', $topic, ['user' => $receiver->id]);

        $this->assertSame($receiver->id, $topic->fresh()->assigned_to);
    }

    public function test_a_bulk_give_skips_topics_somebody_else_has(): void
    {
        $holder = $this->user('author');
        $me = $this->user('author');
        $free = $this->topic('Free one');
        $taken = $this->topic('Taken one');
        TopicAssignments::assign($taken, $holder->id, $holder);
        $this->actingAs($me);

        Livewire::test(TopicsToWrite::class)->callTableBulkAction('take_many', [$free, $taken]);

        $this->assertSame($me->id, $free->fresh()->assigned_to);
        $this->assertSame($holder->id, $taken->fresh()->assigned_to);
    }

    public function test_an_author_sees_the_articles_assigned_to_them_in_the_article_list(): void
    {
        $author = $this->user('author');
        $mine = $this->topic('Mine now');
        $other = $this->topic('Not mine');
        TopicAssignments::assign($mine, $author->id, $author);
        $this->actingAs($author);

        $ids = ArticleResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_the_filter_shows_free_then_mine_then_taken(): void
    {
        $me = $this->user('author');
        $other = $this->user('author');
        $free = $this->topic('Free topic');
        $mine = $this->topic('My topic');
        $theirs = $this->topic('Their topic');
        TopicAssignments::assign($mine, $me->id, $me);
        TopicAssignments::assign($theirs, $other->id, $other);
        $this->actingAs($me);

        Livewire::test(TopicsToWrite::class)->assertCanSeeTableRecords([$free])->assertCanNotSeeTableRecords([$mine, $theirs])
            ->filterTable('view', 'mine')->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$free, $theirs])
            ->filterTable('view', 'taken')->assertCanSeeTableRecords([$theirs])->assertCanNotSeeTableRecords([$free, $mine]);
    }

    public function test_the_search_box_finds_a_topic_by_its_title_or_its_subtopic(): void
    {
        $prayer = $this->topic('Pillars of prayer');
        $other = $this->topic('Charity rules', ['custom_data' => ['subtopic' => 'Zakat > Who receives']]);
        $this->actingAs($this->user('author'));

        Livewire::test(TopicsToWrite::class)
            ->searchTable('pillars')->assertCanSeeTableRecords([$prayer])->assertCanNotSeeTableRecords([$other])
            ->searchTable('Zakat')->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$prayer]);
    }

    public function test_the_id_column_can_be_sorted(): void
    {
        $a = $this->topic('First topic');
        $b = $this->topic('Second topic');
        $this->actingAs($this->user('author'));

        $page = Livewire::test(TopicsToWrite::class)->sortTable('id', 'desc');
        $this->assertSame([$b->id, $a->id], $page->instance()->getTableRecords()->pluck('id')->all());
        $page->sortTable('id', 'asc');
        $this->assertSame([$a->id, $b->id], $page->instance()->getTableRecords()->pluck('id')->all());
    }
}
