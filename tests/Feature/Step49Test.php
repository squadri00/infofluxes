<?php

namespace Tests\Feature;

use App\Filament\Resources\History\Pages\ListActivities;
use App\Models\Activity;
use App\Models\AiPrompt;
use App\Models\Article;
use App\Models\Category;
use App\Models\Person;
use App\Models\Setting;
use App\Models\User;
use App\Support\History\HistoryKeeper;
use App\Support\History\Present;
use App\Support\History\TextDiff;
use App\Support\History\Undo;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 49: the history (who changed what and when) and putting old words back. Only the super user sees it. */
class Step49Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        Activity::query()->delete();   // start each test with an empty history
    }

    private function user(string $role = 'superuser'): User
    {
        $u = User::factory()->create(['role' => $role, 'status' => 'active']);
        $this->actingAs($u);
        Activity::query()->delete();

        return $u;
    }

    private function article(array $extra = []): Article
    {
        return Article::create(array_merge(['title' => ['en' => 'First title'], 'slug' => 'first', 'category_id' => $this->cat->id, 'long_detail' => ['en' => '<p>Old words here.</p>']], $extra));
    }

    public function test_creating_changing_trashing_restoring_and_removing_are_all_written(): void
    {
        $me = $this->user();
        $a = $this->article();
        $a->update(['status' => 'published']);
        $a->delete();
        $a->restore();
        $a->forceDelete();

        $this->assertSame(['created', 'updated', 'deleted', 'restored', 'purged'], Activity::orderBy('id')->pluck('event')->all());
        $this->assertSame([$me->id], Activity::pluck('user_id')->unique()->all());
        $first = Activity::orderBy('id')->first();
        $this->assertSame(Article::class, $first->subject_type);
        $this->assertSame($a->id, $first->subject_id);
        $this->assertStringContainsString('First title', $first->subject_label, 'the name is kept even after the record is gone');
    }

    public function test_a_change_keeps_only_the_fields_that_changed_with_old_and_new(): void
    {
        $this->user();
        $a = $this->article()->fresh();   // as the admin loads it: with the real values

        $a->update(['status' => 'published', 'short_detail' => ['en' => 'New short']]);

        $line = Activity::where('event', 'updated')->first();
        $this->assertEqualsCanonicalizing(['status', 'short_detail'], $line->changedFields());
        $this->assertSame('draft', $line->old_values['status']);
        $this->assertSame('published', $line->new_values['status']);
        $this->assertSame('{"en":"New short"}', $line->new_values['short_detail']);
        $this->assertArrayNotHasKey('long_detail', $line->new_values);
    }

    public function test_a_save_with_no_real_change_or_only_ignored_fields_writes_nothing(): void
    {
        $this->user();
        $a = $this->article();
        Activity::query()->delete();

        $a->save();                                  // nothing changed
        $a->update(['sort_order' => 9]);             // not worth a line
        $u = User::factory()->create();
        Activity::query()->delete();
        $u->forceFill(['remember_token' => 'abc'])->save();

        $this->assertSame(0, Activity::count());
    }

    public function test_a_password_is_never_written_but_a_role_change_is(): void
    {
        $this->user();
        $u = User::factory()->create(['role' => 'member', 'status' => 'active']);
        Activity::query()->delete();

        $u->update(['password' => 'a-new-secret-password', 'role' => 'moderator']);

        $line = Activity::first();
        $this->assertSame(['role'], $line->changedFields());
        $this->assertStringNotContainsString('secret', json_encode($line->getAttributes()));
    }

    public function test_the_line_says_who_a_person_or_the_system(): void
    {
        $me = $this->user();
        $this->article();
        $this->assertSame($me->name, Activity::first()->who());

        auth()->logout();
        Article::create(['title' => ['en' => 'By nobody'], 'slug' => 'nobody', 'category_id' => $this->cat->id]);
        $this->assertSame('The system', Activity::latest('id')->first()->who());
    }

    public function test_other_models_are_logged_too(): void
    {
        $this->user();
        $cat = Category::create(['name' => ['en' => 'New one'], 'slug' => 'new-one']);
        $p = Person::create(['name' => 'Dr X']);
        AiPrompt::where('slug', 'master')->first()->update(['body' => 'Changed rules']);

        $types = Activity::pluck('subject_type')->unique()->all();
        $this->assertEqualsCanonicalizing([Category::class, Person::class, AiPrompt::class], $types);
        $this->assertSame(['body'], Activity::where('subject_type', AiPrompt::class)->first()->changedFields());
    }

    public function test_a_big_import_can_run_without_filling_the_history(): void
    {
        $this->user();

        Activity::quiet(fn () => $this->article());

        $this->assertSame(0, Activity::count());
        $this->article(['slug' => 'second', 'title' => ['en' => 'Second']]);
        $this->assertSame(1, Activity::count(), 'logging is back on afterwards');
    }

    public function test_a_failure_of_the_history_never_stops_a_save(): void
    {
        $this->user();
        \Illuminate\Support\Facades\Schema::drop('activity_log');
        $this->withoutExceptionHandling();

        $a = $this->article();   // the table is gone; the article is still saved

        $this->assertNotNull(Article::find($a->id));
    }

    // ---------- the screen ----------

    public function test_only_the_super_user_sees_the_history(): void
    {
        foreach (['moderator', 'author', 'member'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active', 'admin_screens' => ['articles', 'import', 'categories', 'comments']]));
            $this->get('/admin/history')->assertForbidden();
        }
        $this->user();
        $this->get('/admin/history')->assertOk()->assertSee('History');
    }

    public function test_the_list_shows_the_lines_and_filters_work(): void
    {
        $this->user();
        $a = $this->article();
        $a->update(['status' => 'published']);
        $c = Category::create(['name' => ['en' => 'Other'], 'slug' => 'other']);

        $created = Activity::where('event', 'created')->where('subject_type', Article::class)->first();
        $updated = Activity::where('event', 'updated')->first();
        $catLine = Activity::where('subject_type', Category::class)->first();

        Livewire::test(ListActivities::class)->assertCanSeeTableRecords([$created, $updated, $catLine])
            ->filterTable('event', 'updated')->assertCanSeeTableRecords([$updated])->assertCanNotSeeTableRecords([$created, $catLine]);
        Livewire::test(ListActivities::class)->filterTable('subject_type', Category::class)->assertCanSeeTableRecords([$catLine])->assertCanNotSeeTableRecords([$updated]);
        Livewire::test(ListActivities::class)->searchTable('First title')->assertCanSeeTableRecords([$created, $updated])->assertCanNotSeeTableRecords([$catLine]);
    }

    public function test_the_date_filter_works(): void
    {
        $this->user();
        $this->article();
        $old = Activity::first();
        $old->update(['created_at' => now()->subDays(40)]);
        $this->article(['slug' => 'recent', 'title' => ['en' => 'Recent']]);
        $recent = Activity::where('subject_label', 'like', '%Recent%')->first();

        Livewire::test(ListActivities::class)
            ->filterTable('when', ['from' => now()->subDays(7)->toDateString(), 'until' => now()->toDateString()])
            ->assertCanSeeTableRecords([$recent])->assertCanNotSeeTableRecords([$old]);
    }

    public function test_the_summary_and_the_readable_values(): void
    {
        $this->user();
        $a = $this->article()->fresh();   // as the admin loads it: with the real values
        $a->update(['status' => 'written', 'category_id' => Category::create(['name' => ['en' => 'Elsewhere'], 'slug' => 'elsewhere'])->id]);
        $line = Activity::where('event', 'updated')->first();

        $summary = Present::summary($line);

        $this->assertStringContainsString('Status (Draft → Written, waiting for review)', $summary);
        $this->assertStringContainsString('Category (', $summary);
        $this->assertStringContainsString('Elsewhere', $summary);
        $this->assertSame(['EN' => 'Old words here.'], Present::value('long_detail', '{"en":"<p>Old words here.</p>"}'));
    }

    public function test_the_word_comparison_marks_removed_and_added_words_and_escapes_html(): void
    {
        $html = TextDiff::inline('The cat sat down', 'The dog sat <b>down</b>');

        $this->assertStringContainsString('<del>cat</del>', $html);
        $this->assertStringContainsString('<ins>dog</ins>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertNull(TextDiff::inline(str_repeat('word ', 2000), str_repeat('other ', 2000)), 'too long: shown side by side instead');
        $this->assertSame("First\n\nSecond\nline", TextDiff::plain('<p>First</p><p>Second<br>line</p>'));
    }

    public function test_the_details_window_shows_before_and_after(): void
    {
        $this->user();
        $a = $this->article();
        $a->setTranslation('long_detail', 'en', '<p>New words here.</p>')->save();
        $line = Activity::where('event', 'updated')->first();

        Livewire::test(ListActivities::class)->mountAction(TestAction::make('details')->table($line))->assertHasNoErrors();

        $html = view('filament.history.details', ['a' => $line])->render();
        $this->assertStringContainsString('Long detail', $html);
        $this->assertStringContainsString('<del>Old</del>', $html);
        $this->assertStringContainsString('<ins>New</ins>', $html);
        $this->assertStringContainsString('removed', $html);
    }

    // ---------- putting the old words back ----------

    public function test_the_old_words_can_be_put_back_and_that_is_written_too(): void
    {
        $me = $this->user();
        $a = $this->article();
        $a->setTranslation('long_detail', 'en', '<p>Ruined text.</p>')->setTranslation('title', 'en', 'Ruined title')->save();
        $line = Activity::where('event', 'updated')->first();

        $this->assertEqualsCanonicalizing(['title', 'long_detail'], array_keys(Undo::fields($line)));
        $done = Undo::apply($line, ['long_detail']);

        $this->assertSame(['long_detail'], $done['restored']);
        $fresh = $a->fresh();
        $this->assertSame('<p>Old words here.</p>', $fresh->getTranslation('long_detail', 'en'));
        $this->assertSame('Ruined title', $fresh->getTranslation('title', 'en'), 'only the chosen field is put back');
        $undoLine = Activity::where('event', 'updated')->latest('id')->first();
        $this->assertSame($line->id, $undoLine->custom_data['undo_of']);
        $this->assertSame($me->id, $undoLine->user_id);
        $this->assertSame('<p>Ruined text.</p>', Present::value('long_detail', $undoLine->old_values['long_detail'])['EN'] === 'Ruined text.' ? '<p>Ruined text.</p>' : 'wrong', 'the undo can itself be undone');
    }

    public function test_the_undo_button_works_from_the_screen(): void
    {
        $this->user();
        $a = $this->article();
        $a->setTranslation('short_detail', 'en', 'Short words')->save();
        $a->setTranslation('short_detail', 'en', 'Spoiled')->save();
        $line = Activity::where('event', 'updated')->latest('id')->first();

        Livewire::test(ListActivities::class)
            ->callAction(TestAction::make('undo')->table($line), ['fields' => ['short_detail']])->assertHasNoActionErrors();

        $this->assertSame('Short words', $a->fresh()->getTranslation('short_detail', 'en'));
    }

    public function test_a_status_a_category_and_a_gone_record_cannot_be_put_back(): void
    {
        $this->user();
        $a = $this->article();
        $a->update(['status' => 'published']);
        $statusLine = Activity::where('event', 'updated')->first();

        $this->assertSame([], Undo::fields($statusLine), 'a status goes through the review rules, not through undo');
        $this->assertSame(['restored' => [], 'skipped' => ['status']], Undo::apply($statusLine, ['status']));
        $this->assertSame('published', $a->fresh()->status);

        $a->setTranslation('title', 'en', 'Changed')->save();
        $titleLine = Activity::where('event', 'updated')->latest('id')->first();
        $a->forceDelete();
        $this->assertSame([], Undo::fields($titleLine), 'the record is gone');
    }

    public function test_it_tells_when_the_field_was_changed_again_later(): void
    {
        $this->user();
        $a = $this->article();
        $a->setTranslation('title', 'en', 'Second')->save();
        $first = Activity::where('event', 'updated')->first();
        $this->assertFalse(Undo::changedSince($first, 'title'));

        $a->setTranslation('title', 'en', 'Third')->save();

        $this->assertTrue(Undo::changedSince($first, 'title'));
    }

    public function test_a_users_fields_and_a_person_can_not_be_put_back_but_a_contributor_text_can(): void
    {
        $this->user();
        $u = User::factory()->create(['role' => 'member', 'name' => 'Old Name']);
        Activity::query()->delete();
        $u->update(['name' => 'New Name']);
        $this->assertSame([], Undo::fields(Activity::first()));

        $p = Person::create(['name' => 'Dr X', 'bio' => 'Old bio']);
        Activity::query()->delete();
        $p->update(['bio' => 'Spoiled bio']);
        $this->assertSame(['bio' => 'Bio'], Undo::fields(Activity::first()));
    }

    // ---------- keeping and tidying ----------

    public function test_old_lines_are_removed_after_the_keep_time(): void
    {
        $this->user();
        $this->article();
        Activity::first()->update(['created_at' => now()->subMonths(13)]);
        $this->article(['slug' => 'b', 'title' => ['en' => 'B']]);

        $this->assertSame(12, HistoryKeeper::months());
        $this->artisan('history:prune')->expectsOutputToContain('Removed 1 old history line')->assertSuccessful();
        $this->assertSame(1, Activity::count());

        Setting::put('history_keep_months', 0);
        $this->assertSame(1, HistoryKeeper::months(), 'never below one month');
    }

    public function test_the_keep_time_can_be_set_from_the_screen_and_the_tidy_is_scheduled(): void
    {
        $this->user();

        Livewire::test(ListActivities::class)->callAction('keep', ['months' => 6])->assertHasNoActionErrors();
        $this->assertSame(6, HistoryKeeper::months());

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'history:prune'));
        $this->assertCount(1, $events);
    }

    public function test_the_history_screen_is_read_only(): void
    {
        $this->user();
        $this->article();
        $line = Activity::first();

        $this->assertFalse(\App\Filament\Resources\History\ActivityResource::canCreate());
        $this->assertFalse(\App\Filament\Resources\History\ActivityResource::canEdit($line));
        $this->assertFalse(\App\Filament\Resources\History\ActivityResource::canDelete($line));
        $this->assertFalse(\App\Filament\Resources\History\ActivityResource::canDeleteAny());
    }
}
