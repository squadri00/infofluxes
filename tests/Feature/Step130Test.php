<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Support\AiImport;
use App\Support\ArticlePictures;
use App\Support\PicturePrompt;
use App\Support\Prompts;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 130: the AI import makes one picture row for every tab, each with a prompt for the image maker; the rules (4:3, light for prophets, light faces for companions). */
class Step130Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
        $master = Category::create(['name' => ['en' => 'Master'], 'slug' => 'master']);
        $this->cat = Category::create(['name' => ['en' => 'History place'], 'slug' => 'history-place', 'parent_id' => $master->id]);
    }

    protected function tearDown(): void
    {
        Setting::forget();
        parent::tearDown();
    }

    private function user(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    private function answer(?string $pictures = null, string $title = 'The Journey of the Traveller'): string
    {
        $pictures ??= "PICTURES:\nTAB 1: The start\nPROMPT: A young traveller leaves a town at dawn with a small bag.\nALT: A traveller leaving a town at dawn\nTAB 2: The road\nPROMPT: A long dusty road across the desert with a caravan far away.\nALT: A caravan on a desert road\nTAB 3: The arrival\nPROMPT: A busy harbour city with ships and traders.\nALT: A harbour city with ships\n";

        return "TYPE: article\nCATEGORY: History place\nTITLE: {$title}\nSHORT: A short line about a traveller.\nLONG:\n## The start\nHe left home at dawn.\n\n## The road\nThe road was long.\n\n## The arrival\nHe reached the harbour.\n\nKEYWORDS: travel\nSOURCE: A real book, page 4\n".$pictures."END\n";
    }

    private function import(string $text, ?int $replaceId = null): Article
    {
        $items = AiImport::parse($text, false, null, $replaceId);
        $this->assertSame([], $items[0]['errors'], json_encode($items[0]['errors']));
        $made = AiImport::commit($items, $text, $this->user());

        return Article::findOrFail($made['articles'][0]);
    }

    // ---------- reading the block ----------

    public function test_the_pictures_block_is_read_by_tab_number(): void
    {
        $read = PicturePrompt::read("TAB 1: First\nPROMPT: A calm hill\nat sunrise.\nALT: A hill\n**TAB 2:** Second\n**PROMPT:** A river.\nALT: A river\n");

        $this->assertSame([1, 2], array_keys($read));
        $this->assertSame('A calm hill at sunrise.', $read[1]['prompt']);
        $this->assertSame('A hill', $read[1]['alt']);
        $this->assertSame('A river.', $read[2]['prompt']);
        $this->assertSame([], PicturePrompt::read(''));
    }

    // ---------- the import makes the rows ----------

    public function test_an_imported_article_gets_one_waiting_picture_row_for_each_tab(): void
    {
        $article = $this->import($this->answer());

        $rows = $article->images()->get();
        $this->assertCount(3, $rows);
        $this->assertSame([1, 2, 3], $rows->pluck('section')->map(fn ($s) => (int) $s)->all());
        $this->assertSame([null, null, null], $rows->pluck('image')->all(), 'the pictures wait');
        $this->assertSame('A young traveller leaves a town at dawn with a small bag.', $rows[0]->prompt);
        $this->assertSame('A caravan on a desert road', $rows[1]->getTranslation('alt', 'en'));
        $this->assertSame($rows->pluck('sort_order')->sort()->values()->all(), $rows->pluck('sort_order')->all(), 'in the order of the tabs');
    }

    public function test_tabs_without_a_prompt_get_a_simple_one_made_from_the_heading_and_the_check_says_so(): void
    {
        $text = $this->answer("PICTURES:\nTAB 1: The start\nPROMPT: A traveller at dawn.\nALT: A traveller\n");
        $items = AiImport::parse($text);
        $this->assertStringContainsString('prompts for 1 of the 3 tabs', implode(' ', $items[0]['warnings']));
        $this->assertStringContainsString('3 picture place(s) will be made', implode(' ', $items[0]['data']['please_check']));

        $article = $this->import($text);
        $rows = $article->images()->get();
        $this->assertCount(3, $rows);
        $this->assertSame('A traveller at dawn.', $rows[0]->prompt);
        $this->assertStringContainsString('The road', $rows[1]->prompt);
        $this->assertStringContainsString('The Journey of the Traveller', $rows[1]->prompt);

        // No PICTURES block at all: every tab still gets a row.
        $none = $this->import($this->answer('', 'A Second Journey'));
        $this->assertCount(3, $none->images()->get());
    }

    public function test_more_prompts_than_tabs_is_a_warning_and_the_extra_ones_are_ignored(): void
    {
        $text = $this->answer("PICTURES:\nTAB 1: a\nPROMPT: One.\nTAB 2: b\nPROMPT: Two.\nTAB 3: c\nPROMPT: Three.\nTAB 4: d\nPROMPT: Four.\n");
        $this->assertStringContainsString('more prompts than the text has tabs', implode(' ', AiImport::parse($text)[0]['warnings']));
        $this->assertCount(3, $this->import($text)->images()->get());
    }

    public function test_replacing_an_article_keeps_its_pictures_and_adds_only_the_missing_rows(): void
    {
        $article = $this->import($this->answer());
        $first = $article->images()->orderBy('section')->first();
        $first->update(['image' => 'articles/real.webp', 'prompt' => 'My own prompt']);
        $article->images()->where('section', 3)->delete();

        $replaced = $this->import($this->answer(null, 'The Journey Again'), $article->id);

        $this->assertSame($article->id, $replaced->id);
        $rows = $replaced->images()->orderBy('section')->get();
        $this->assertCount(3, $rows);
        $this->assertSame('articles/real.webp', $rows[0]->image);
        $this->assertSame('My own prompt', $rows[0]->prompt, 'a row that is there is not changed');
        $this->assertNull($rows[2]->image);
        $this->assertSame('A busy harbour city with ships and traders.', $rows[2]->prompt);
    }

    public function test_sync_is_safe_to_repeat_and_fills_an_empty_prompt(): void
    {
        $article = Article::create(['title' => ['en' => 'Plain'], 'slug' => 'plain', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'long_detail' => ['en' => '<h2>One</h2><p>a</p><h2>Two</h2><p>b</p>']]);
        ArticleImage::create(['article_id' => $article->id, 'section' => 1, 'image' => null, 'sort_order' => 1]);

        $this->assertSame(1, PicturePrompt::sync($article), 'only tab 2 was missing');
        $this->assertSame(0, PicturePrompt::sync($article->fresh()));
        $rows = $article->images()->orderBy('section')->get();
        $this->assertStringContainsString('One', $rows[0]->prompt, 'the empty prompt was filled');
        $this->assertCount(2, $rows);

        $noTabs = Article::create(['title' => ['en' => 'No tabs'], 'slug' => 'no-tabs', 'category_id' => $this->cat->id, 'status' => 'draft', 'long_detail' => ['en' => '<p>Just text.</p>']]);
        $this->assertSame(0, PicturePrompt::sync($noTabs));
    }

    // ---------- the prompt for the image maker ----------

    public function test_the_whole_prompt_has_the_scene_the_look_and_the_picture_rules(): void
    {
        $full = PicturePrompt::full('A caravan crosses the desert.');

        foreach (['A caravan crosses the desert.', 'Look of the picture:', '4:3', 'never show any part of a body', 'pure light', 'faces are made of light', 'Allah (SWT): never draw', 'No writing'] as $needle) {
            $this->assertStringContainsString($needle, $full, $needle);
        }
        $this->assertStringContainsString(PicturePrompt::style(), $full);

        Setting::put('picture_prompt_rules', '- Only my own rule.');
        Setting::forget();
        $this->assertStringContainsString('Only my own rule.', PicturePrompt::full('x'));
        $this->assertStringNotContainsString('4:3', PicturePrompt::full('x'));
    }

    public function test_the_article_prompts_ask_for_the_pictures_block_with_the_rules(): void
    {
        foreach ([Prompts::make('article', 'A topic'), Prompts::research(['topic' => 'A topic']), Prompts::research(['topic' => 'A topic', 'own_text' => 'My own text here for the shape.'])] as $text) {
            $this->assertStringContainsString("PICTURES:\nTAB 1:", $text);
            $this->assertStringContainsString('PROMPT:', $text);
            $this->assertStringContainsString('4:3', $text);
            $this->assertStringContainsString('pure light', $text);
            $this->assertStringNotContainsString('{{', $text);
        }
    }

    // ---------- the public page and the admin ----------

    public function test_a_waiting_row_shows_nothing_to_visitors_and_a_row_with_a_picture_does(): void
    {
        $article = $this->import($this->answer());
        $article->update(['status' => 'published', 'publish_date' => now()->subDay()]);

        $this->get(route('article.show', $article->slug))->assertOk()->assertDontSee('A caravan on a desert road')->assertDontSee('A traveller leaving a town at dawn');

        $article->images()->where('section', 2)->update(['image' => 'articles/real.webp']);
        $this->get(route('article.show', $article->slug))->assertOk()->assertSee('A caravan on a desert road')->assertDontSee('A harbour city with ships');
    }

    public function test_the_closed_row_says_it_is_waiting(): void
    {
        $label = ArticlePictures::itemLabel(['section' => 1, 'image' => null, 'alt' => ['en' => 'A traveller']], '<h2>The start</h2>');
        $this->assertStringContainsString('waiting for the picture', $label);
        $this->assertStringNotContainsString('waiting', ArticlePictures::itemLabel(['section' => 1, 'image' => 'articles/x.webp', 'alt' => ['en' => 'A traveller']], '<h2>The start</h2>'));
    }

    public function test_the_edit_page_shows_the_rows_and_its_button_makes_the_missing_ones(): void
    {
        $article = $this->import($this->answer());
        $article->images()->where('section', 3)->delete();

        $page = Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->assertSuccessful();
        $page->assertSee('The whole prompt for the image maker')->assertSee('4:3');

        $page->callAction('picture_rows');
        $this->assertCount(3, $article->images()->get());
        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->callAction('picture_rows')->assertNotified('Every tab already has a picture row.');
    }
}
