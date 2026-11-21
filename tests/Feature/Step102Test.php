<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Stories\Pages\EditStory;
use App\Filament\Resources\Stories\Pages\ListStories;
use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Models\StoryChapter;
use App\Models\StorySlide;
use App\Models\User;
use App\Support\ScriptExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 102: staff can download the script of an article or a story as a Word file or a plain text file. */
class Step102Test extends TestCase
{
    use RefreshDatabase;

    private function article(): Article
    {
        $cat = Category::create(['name' => ['en' => 'Topic'], 'slug' => 'topic']);

        return Article::create(['title' => ['en' => 'The Moon & the Tides'], 'slug' => 'moon-tides', 'category_id' => $cat->id, 'status' => 'draft',
            'short_detail' => ['en' => 'A short line.'], 'source_line' => 'Book One, page 4',
            'long_detail' => ['en' => '<h2>First part</h2><p>Some <strong>bold</strong> words.</p><ul><li><p>One point</p></li><li><p>Two point</p></li></ul>']]);
    }

    private function story(): Story
    {
        $cat = Category::create(['name' => ['en' => 'Tabs'], 'slug' => 'tabs']);
        $story = Story::create(['title' => ['en' => 'A Walk'], 'slug' => 'a-walk', 'category_id' => $cat->id, 'status' => 'draft', 'short_detail' => ['en' => 'About a walk.']]);
        $chapter = StoryChapter::create(['story_id' => $story->id, 'title' => ['en' => 'The road'], 'sort_order' => 1]);
        StorySlide::create(['story_chapter_id' => $chapter->id, 'heading' => ['en' => 'Setting out'], 'body' => ['en' => 'They left at dawn.'], 'sort_order' => 1]);
        StorySlide::create(['story_chapter_id' => $chapter->id, 'heading' => ['en' => ''], 'body' => ['en' => 'The sun rose.'], 'sort_order' => 2]);

        return $story;
    }

    public function test_the_article_script_has_the_title_and_the_content_in_order(): void
    {
        $txt = ScriptExport::txt(ScriptExport::blocks($this->article()));

        $this->assertStringContainsString('The Moon & the Tides', $txt);
        $this->assertStringContainsString('A short line.', $txt);
        $this->assertStringContainsString('FIRST PART', $txt);
        $this->assertStringContainsString('Some bold words.', $txt);
        $this->assertStringContainsString('- One point', $txt);
        $this->assertStringContainsString('Book One, page 4', $txt);
        $this->assertLessThan(strpos($txt, 'Two point'), strpos($txt, 'One point'));
    }

    public function test_the_story_script_lists_chapters_and_numbered_slides(): void
    {
        $txt = ScriptExport::txt(ScriptExport::blocks($this->story()));

        $this->assertStringContainsString('A Walk', $txt);
        $this->assertStringContainsString('THE ROAD', $txt);
        $this->assertStringContainsString('Slide 1: Setting out', $txt);
        $this->assertStringContainsString('They left at dawn.', $txt);
        $this->assertStringContainsString('Slide 2', $txt);
        $this->assertStringContainsString('The sun rose.', $txt);
    }

    public function test_the_word_file_is_a_real_docx_with_the_text_inside(): void
    {
        $data = ScriptExport::docx(ScriptExport::blocks($this->article()));
        $tmp = tempnam(sys_get_temp_dir(), 'dx');
        file_put_contents($tmp, $data);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp));
        $xml = $zip->getFromName('word/document.xml');
        $this->assertNotFalse($zip->getFromName('[Content_Types].xml'));
        $zip->close();
        @unlink($tmp);

        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('The Moon &amp; the Tides', $xml);
        $this->assertStringContainsString('Some bold words.', $xml);
    }

    public function test_the_download_buttons_exist_in_the_lists_and_on_the_edit_pages_and_send_a_file(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $article = $this->article();
        $story = $this->story();

        Livewire::test(ListArticles::class)->assertTableActionExists('script_docx', record: $article)->assertTableActionExists('script_txt', record: $article);
        Livewire::test(ListStories::class)->assertTableActionExists('script_docx', record: $story)->assertTableActionExists('script_txt', record: $story);
        Livewire::test(EditArticle::class, ['record' => $article->id])->assertActionExists('script_docx')->callAction('script_txt')->assertFileDownloaded('A-'.$article->id.'-moon-tides.txt');
        Livewire::test(EditStory::class, ['record' => $story->id])->assertActionExists('script_txt')->callAction('script_docx')->assertFileDownloaded($story->code.'-a-walk.docx');
    }
}
