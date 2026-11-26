<?php

namespace Tests\Feature;

use App\Filament\Resources\Clips\Pages\ManageClips;
use App\Models\Article;
use App\Models\Category;
use App\Models\Clip;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 133: the Clips page (a big player and six thumbnails), the menu button, and the Clips admin screen. */
class Step133Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Storage::fake('public');
    }

    private function clip(string $name, array $extra = []): Clip
    {
        Storage::disk('public')->put('clips/'.$name, 'x');

        return Clip::create($extra + ['title' => ucfirst($name), 'file' => 'clips/'.$name, 'sort_order' => Clip::count() + 1, 'is_active' => true]);
    }

    private function article(string $status): Article
    {
        $cat = Category::create(['name' => ['en' => 'Cat'.Article::count()], 'slug' => 'cat'.Article::count()]);

        return Article::create(['title' => ['en' => 'Art '.Article::count()], 'slug' => 'art-'.Article::count(), 'category_id' => $cat->id, 'status' => $status, 'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'x'], 'long_detail' => ['en' => '<p>x</p>']]);
    }

    public function test_the_menu_has_a_clips_button_after_the_gallery_and_the_page_opens(): void
    {
        $this->article('published');   // the main menu is shown when there is a category
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-clips-link', $html);
        $this->assertGreaterThan(strpos($html, 'data-challenge-link') ?: 0, strpos($html, 'data-clips-link'));
        $this->get(route('clips'))->assertOk()->assertSee('No clips yet');
    }

    public function test_the_footer_of_the_home_page_links_to_clips_and_the_switch_removes_it(): void
    {
        $this->assertSame(1, substr_count($this->get('/')->getContent(), 'class="site-footer__list"') ? 1 : 0);
        $this->assertStringContainsString('<li><a href="'.route('clips').'">Clips</a></li>', $this->get('/')->getContent());
        Setting::put('ctl_clips', '0');
        Setting::forget();
        $this->assertStringNotContainsString(route('clips'), $this->get('/')->getContent());
    }

    public function test_the_page_lists_active_clips_in_order_with_six_thumbnails_per_page_and_a_button_to_the_article(): void
    {
        $article = $this->article('published');
        for ($i = 1; $i <= 8; $i++) {
            $this->clip("c{$i}.mp4", $i === 1 ? ['blurb' => 'Look at this', 'link_type' => 'article', 'article_id' => $article->id] : []);
        }
        $this->clip('hidden.mp4', ['is_active' => false]);

        $r = $this->get(route('clips'))->assertOk();
        $r->assertSee('data-clips', false)->assertSee('js/clips.js', false);
        $r->assertDontSee('Hidden.mp4');
        $html = $r->getContent();
        $this->assertSame(8, substr_count($html, 'data-clips-item='));
        $this->assertSame(2, substr_count($html, ' hidden>') >= 2 ? 2 : 0);   // thumbnails 7 and 8 start hidden
        $this->assertStringContainsString('"blurb":"Look at this"', $html);
        $this->assertStringContainsString(str_replace('/', '\/', url($article->publicPath())), $html);
    }

    public function test_a_clip_leading_to_an_unpublished_article_has_no_button_and_a_missing_file_is_left_out(): void
    {
        $draft = $this->article('draft');
        $this->clip('a.mp4', ['link_type' => 'article', 'article_id' => $draft->id]);
        Clip::create(['title' => 'Gone', 'file' => 'clips/gone.mp4', 'is_active' => true]);

        $html = $this->get(route('clips'))->assertOk()->getContent();
        $this->assertStringContainsString('"url":""', $html);
        $this->assertStringNotContainsString('Gone', $html);
    }

    public function test_the_switch_turns_the_page_and_the_button_off(): void
    {
        Setting::put('ctl_clips', '0');
        Setting::forget();
        $this->get(route('clips'))->assertNotFound();
        $this->get('/')->assertDontSee('data-clips-link', false);
    }

    public function test_the_folder_scan_lists_new_video_files_once(): void
    {
        Storage::disk('public')->put('clips/new-one.mp4', 'x');
        Storage::disk('public')->put('clips/notes.txt', 'x');
        $this->assertSame(1, Clip::scan());
        $this->assertSame(0, Clip::scan());
        $this->assertSame('New One', Clip::first()->title);
    }

    public function test_deleting_a_clip_deletes_its_file_and_clips_cannot_be_an_address_of_an_article(): void
    {
        $c = $this->clip('bye.mp4');
        $c->delete();
        Storage::disk('public')->assertMissing('clips/bye.mp4');
        $this->assertContains('clips', \App\Models\Category::RESERVED_SLUGS);
        $this->assertContains('clips', \App\Models\Page::RESERVED);
    }

    public function test_the_admin_screen_lists_the_clips_and_a_moderator_can_use_it(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $c = $this->clip('one.mp4');
        $this->get('/admin/clips')->assertOk()->assertSee('Find new videos');
        Livewire::test(ManageClips::class)->assertCanSeeTableRecords([$c]);
    }

    public function test_the_clean_function_keeps_only_the_chosen_link(): void
    {
        $d = \App\Filament\Resources\Clips\ClipResource::clean(['link_type' => 'story', 'article_id' => 5, 'story_id' => 7, 'button_label' => 'Go']);
        $this->assertNull($d['article_id']);
        $this->assertSame(7, $d['story_id']);
        $d = \App\Filament\Resources\Clips\ClipResource::clean(['link_type' => '', 'article_id' => 5, 'story_id' => 7, 'button_label' => 'Go']);
        $this->assertNull($d['link_type']);
        $this->assertNull($d['story_id']);
        $this->assertNull($d['button_label']);
    }
}
