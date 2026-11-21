<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Support\MasterBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Step 98: new masters, topics and planned subjects are added under a section from a plan file, safely and only once. */
class Step98Test extends TestCase
{
    use RefreshDatabase;

    private function section(): array
    {
        $root = Category::create(['name' => ['en' => 'About Islam'], 'slug' => 'about-islam']);
        $allah = Category::create(['name' => ['en' => 'Allah Index'], 'slug' => 'allah-index', 'parent_id' => $root->id, 'sort_order' => 1]);
        $sharia = Category::create(['name' => ['en' => 'Sharia Index'], 'slug' => 'sharia-index', 'parent_id' => $root->id, 'sort_order' => 3]);
        $topic = Category::create(['name' => ['en' => 'Prayer'], 'slug' => 'prayer', 'parent_id' => $sharia->id]);
        $existing = Article::create(['title' => ['en' => 'Controlling anger'], 'slug' => 'controlling-anger', 'category_id' => $topic->id, 'status' => 'draft', 'is_index_line' => true]);
        $graded = Article::create(['title' => ['en' => 'Sahih, Hasan and Daif narrations'], 'slug' => 'sahih-hasan-daif', 'category_id' => $topic->id, 'status' => 'draft', 'is_index_line' => true]);

        return compact('root', 'allah', 'sharia', 'topic', 'existing', 'graded');
    }

    private function plan(int $gradedId): array
    {
        return [
            'parent' => 'about-islam',
            'order' => ['allah-index', 'Hadith Index', 'sharia-index', 'Character Index'],
            'same_as' => ['What do sahih, hasan and daif mean?' => 'A-'.$gradedId],
            'masters' => [
                ['name' => 'Hadith Index', 'icon' => 'scroll', 'description' => 'About hadith.', 'topics' => [
                    ['name' => 'Grades', 'description' => 'How graded.', 'groups' => ['The grades' => ['What do sahih, hasan and daif mean?', 'Mutawatir and ahad hadith'], 'Using them' => ['Can a weak hadith be used?']]],
                ]],
                ['name' => 'Character Index', 'icon' => 'heart', 'description' => 'About character.', 'topics' => [
                    ['name' => 'Manners', 'description' => 'Manners.', 'groups' => ['Anger' => ['Controlling anger', 'Honesty and promises']]],
                ]],
            ],
        ];
    }

    public function test_a_dry_run_only_counts(): void
    {
        $s = $this->section();
        $r = MasterBuilder::run($this->plan($s['graded']->id), false);

        $this->assertFalse($r['applied']);
        $this->assertSame(2, $r['masters']);
        $this->assertSame(2, $r['topics']);
        $this->assertSame(3, $r['subjects']);
        $this->assertSame(2, $r['linked']);
        $this->assertSame(0, Category::where('name', 'like', '%Hadith Index%')->count());
        $this->assertSame(2, Article::count());
    }

    public function test_applying_builds_three_levels_visible_with_subtopics_and_the_right_order(): void
    {
        $s = $this->section();
        $r = MasterBuilder::run($this->plan($s['graded']->id), true);

        $this->assertTrue($r['applied']);
        $master = Category::where('slug', 'hadith-index')->first();
        $topic = Category::where('slug', 'grades')->first();
        $this->assertTrue($master->is_visible && $topic->is_visible);
        $this->assertSame($s['root']->id, $master->parent_id);
        $this->assertSame($master->id, $topic->parent_id);
        $this->assertSame('scroll', $master->menu_icon);
        $this->assertSame('About hadith.', (string) $master->description);

        $subjects = Article::where('category_id', $topic->id)->orderBy('sort_order')->get();
        $this->assertSame(['Mutawatir and ahad hadith', 'Can a weak hadith be used?'], $subjects->map->mainTitle()->all(), 'the one that already exists is not made again');
        $this->assertTrue($subjects->every(fn ($a) => $a->status === 'draft' && $a->is_index_line && $a->author_id === null));
        $this->assertSame('The grades', $subjects[0]->custom_data['subtopic']);
        $this->assertSame('Using them', $subjects[1]->custom_data['subtopic']);

        $order = Category::where('parent_id', $s['root']->id)->orderBy('sort_order')->pluck('slug')->all();
        $this->assertSame(['allah-index', 'hadith-index', 'sharia-index', 'character-index'], $order);
        $this->assertSame(3, substr_count((string) $topic->tree_path, '/') + 1, 'the topic is on level 3 of the section');
    }

    public function test_an_existing_article_is_listed_in_the_new_topic_instead_of_being_written_twice(): void
    {
        $s = $this->section();
        MasterBuilder::run($this->plan($s['graded']->id), true);

        $grades = Category::where('slug', 'grades')->first();
        $manners = Category::where('slug', 'manners')->first();
        $this->assertTrue(DB::table('article_category')->where(['article_id' => $s['graded']->id, 'category_id' => $grades->id])->exists(), 'by the same_as list');
        $this->assertTrue(DB::table('article_category')->where(['article_id' => $s['existing']->id, 'category_id' => $manners->id])->exists(), 'by the same title');
        $this->assertSame(1, Article::where('title', 'like', '%Controlling anger%')->count());
        $this->assertSame($s['topic']->id, $s['existing']->fresh()->category_id, 'its own home does not change');
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $s = $this->section();
        $plan = $this->plan($s['graded']->id);
        MasterBuilder::run($plan, true);
        $before = [Category::count(), Article::count(), DB::table('article_category')->count()];

        $again = MasterBuilder::run($plan, true);

        $this->assertSame($before, [Category::count(), Article::count(), DB::table('article_category')->count()]);
        $this->assertSame(0, $again['masters'] + $again['topics'] + $again['subjects']);
    }

    public function test_the_new_subjects_show_in_topics_to_write_and_the_real_plan_file_is_valid(): void
    {
        $s = $this->section();
        MasterBuilder::run($this->plan($s['graded']->id), true);
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'author', 'status' => 'active']));

        \Livewire\Livewire::test(\App\Filament\Pages\TopicsToWrite::class)->assertSee('Mutawatir and ahad hadith')->assertSee('Honesty and promises');

        $plan = require base_path('database/data/about-islam-masters.php');
        $titles = [];
        foreach ($plan['masters'] as $master) {
            $this->assertNotSame([], $master['topics']);
            foreach ($master['topics'] as $topic) {
                foreach ($topic['groups'] as $group => $list) {
                    $this->assertNotSame('', (string) $group);
                    foreach ($list as $title) {
                        $this->assertNotSame('', trim($title));
                        $this->assertLessThan(200, mb_strlen($title));
                        $titles[$topic['name']][] = MasterBuilder::normalize($title);
                    }
                }
            }
        }
        foreach ($titles as $topicName => $list) {
            $this->assertSame($list, array_values(array_unique($list)), 'no subject is repeated inside the topic '.$topicName);
        }
        $this->assertSame(['allah-swt-index', 'the-quran', 'Prophet Muhammad ﷺ Index', 'Hadith and Sunnah Index', 'sharia-index'], array_slice($plan['order'], 0, 5));
        $this->assertContains('sharia-index', $plan['order']);
        $this->assertLessThan(array_search('Islamic Beliefs Index', $plan['order'], true), array_search('sharia-index', $plan['order'], true), 'Sharia comes before Islamic Beliefs');
        $this->assertSame('Common Questions and Misconceptions', end($plan['order']));
    }
}
