<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Person;
use App\Models\PictureRedirect;
use App\Models\Story;
use App\Models\StoryChapter;
use App\Models\StorySlide;
use App\Support\ImageStore;
use App\Support\PictureAlt;
use App\Support\PictureNames;
use App\Support\PictureRenamer;
use App\Support\StoryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Step 114: pictures are named after their page, get an ALT text by themselves, and the old picture addresses keep working. */
class Step114Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function article(array $extra = []): Article
    {
        $cat = Category::firstOrCreate(['slug' => 'topic'], ['name' => ['en' => 'Topic']]);

        return Article::create(array_merge(['title' => ['en' => 'Ibn Battuta: The Muslim Traveller'], 'slug' => 'ibn-battuta', 'category_id' => $cat->id, 'status' => 'draft',
            'long_detail' => ['en' => '<h2>1. Executive Summary</h2><p>A.</p><h2>2. The Maldives and Southeast Asia</h2><p>B.</p>']], $extra));
    }

    // ---------------- names ----------------

    public function test_a_name_is_a_clean_slug_that_is_not_too_long_and_not_empty(): void
    {
        $this->assertSame('ibn-battuta-the-muslim-traveller', PictureNames::slug('Ibn Battuta: The Muslim Traveller!'));
        $this->assertSame('cafe-ete', PictureNames::slug('Café été'));
        $this->assertLessThanOrEqual(PictureNames::MAX, strlen(PictureNames::slug(str_repeat('word ', 60))));
        $this->assertSame('', PictureNames::slug('???'));
        $this->assertMatchesRegularExpression('#^stories/the-walk-[a-z0-9]{6}\.webp$#', PictureNames::path('stories', 'The Walk'));
        $this->assertMatchesRegularExpression('#^stories/picture-[a-z0-9]{6}\.webp$#', PictureNames::path('stories', '???'));
        $this->assertTrue(PictureNames::isNamedFor('stories/the-walk-abc123.webp', 'The Walk'));
        $this->assertFalse(PictureNames::isNamedFor('stories/qeppebs31bmkawnmvsljguml.webp', 'The Walk'));
    }

    public function test_an_uploaded_picture_is_named_after_its_page_or_else_after_the_file(): void
    {
        $file = UploadedFile::fake()->image('IMG_2041.jpg', 800, 450);

        $this->assertMatchesRegularExpression('#^articles/ibn-battuta-the-muslim-traveller-[a-z0-9]{6}\.webp$#', ImageStore::store($file, 'articles', 'Ibn Battuta: The Muslim Traveller'));
        $this->assertMatchesRegularExpression('#^articles/img-2041-[a-z0-9]{6}\.webp$#', ImageStore::store($file, 'articles'));
        $this->assertMatchesRegularExpression('#^profiles/member-[a-z0-9]{6}\.webp$#', ImageStore::store(UploadedFile::fake()->image('john-smith-passport.jpg'), 'profiles', 'member'));
        $this->assertMatchesRegularExpression('#^stories/persia-before-the-conquest-the-muslim-conquest-of-persia-[a-z0-9]{6}\.webp$#', StoryImage::store($file, 'Persia before the conquest The Muslim Conquest of Persia'));
        Storage::disk('public')->assertExists(ImageStore::store($file, 'articles', 'x'));
    }

    // ---------------- ALT ----------------

    public function test_an_empty_alt_is_filled_from_the_page_and_a_written_alt_is_never_changed(): void
    {
        $article = $this->article(['main_image' => 'articles/a.webp']);
        $this->assertSame('Ibn Battuta: The Muslim Traveller', $article->fresh()->getTranslation('main_image_alt', 'en'));

        $own = $this->article(['slug' => 'own', 'main_image' => 'articles/b.webp', 'main_image_alt' => ['en' => 'A man on a ship']]);
        $this->assertSame('A man on a ship', $own->fresh()->getTranslation('main_image_alt', 'en'));

        $none = $this->article(['slug' => 'none']);
        $this->assertSame('', (string) $none->fresh()->getTranslation('main_image_alt', 'en', false), 'no picture, no ALT');
    }

    public function test_a_picture_under_a_tab_gets_the_article_title_and_the_tab_heading(): void
    {
        $article = $this->article();
        $image = $article->images()->create(['image' => 'articles/c.webp', 'section' => 2]);
        $plain = $article->images()->create(['image' => 'articles/d.webp']);

        $this->assertSame('Ibn Battuta: The Muslim Traveller - The Maldives and Southeast Asia', $image->fresh()->getTranslation('alt', 'en'));
        $this->assertSame('Ibn Battuta: The Muslim Traveller', $plain->fresh()->getTranslation('alt', 'en'));
    }

    public function test_slides_categories_facts_and_people_get_an_alt_too(): void
    {
        $cat = Category::create(['name' => ['en' => 'History'], 'slug' => 'history', 'image' => 'categories/h.webp']);
        $this->assertSame('History', $cat->fresh()->getTranslation('image_alt', 'en'));

        $story = Story::create(['title' => ['en' => 'A Walk'], 'slug' => 'a-walk', 'category_id' => $cat->id, 'status' => 'draft']);
        $chapter = StoryChapter::create(['story_id' => $story->id, 'title' => ['en' => 'One'], 'sort_order' => 1]);
        $withHeading = StorySlide::create(['story_chapter_id' => $chapter->id, 'heading' => ['en' => 'Setting out'], 'image' => 'stories/s1.webp', 'sort_order' => 1]);
        $noHeading = StorySlide::create(['story_chapter_id' => $chapter->id, 'heading' => ['en' => ''], 'image' => 'stories/s2.webp', 'sort_order' => 2]);
        $this->assertSame('Setting out', $withHeading->fresh()->getTranslation('image_alt', 'en'));
        $this->assertSame('A Walk, slide 2', $noHeading->fresh()->getTranslation('image_alt', 'en'));

        $fact = DidYouKnow::create(['title' => ['en' => 'A fact'], 'slug' => 'a-fact', 'body' => ['en' => 'x'], 'image' => 'facts/f.webp', 'status' => 'draft']);
        $this->assertSame('A fact', $fact->fresh()->image_alt);

        $person = Person::create(['name' => 'Shamim Saifullah', 'photo' => 'people/p.webp']);
        $this->assertSame('Photo of Shamim Saifullah', $person->fresh()->photo_alt);
    }

    public function test_the_alt_is_short_enough_for_a_screen_reader(): void
    {
        $article = $this->article(['title' => ['en' => str_repeat('long title ', 30)], 'slug' => 'long', 'main_image' => 'articles/l.webp']);

        $this->assertLessThanOrEqual(PictureAlt::MAX, mb_strlen($article->fresh()->getTranslation('main_image_alt', 'en')));
    }

    // ---------------- renaming the pictures that are already there ----------------

    private function putFile(string $path): void
    {
        Storage::disk('public')->put($path, 'picture-bytes');
    }

    public function test_the_renamer_moves_the_file_updates_every_place_and_remembers_the_old_address(): void
    {
        $this->putFile('articles/qwerty1234.webp');
        $this->putFile('articles/second5678.webp');
        $article = $this->article(['main_image' => 'articles/qwerty1234.webp']);
        $image = $article->images()->create(['image' => 'articles/second5678.webp', 'section' => 1]);

        $plan = PictureRenamer::plan();
        $this->assertCount(2, $plan);
        $result = PictureRenamer::apply($plan);

        $this->assertSame(2, $result['renamed']);
        $this->assertSame([], $result['problems']);
        $new = $article->fresh()->main_image;
        $this->assertMatchesRegularExpression('#^articles/ibn-battuta-the-muslim-traveller-[a-z0-9]{6}\.webp$#', $new);
        $this->assertMatchesRegularExpression('#^articles/executive-summary-ibn-battuta-the-muslim-traveller-[a-z0-9]{6}\.webp$#', $image->fresh()->image);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing('articles/qwerty1234.webp');
        $this->assertSame($new, PictureRedirect::target('articles/qwerty1234.webp'));
    }

    public function test_the_old_address_goes_to_the_new_one_with_a_301_and_unknown_ones_are_404(): void
    {
        $this->putFile('articles/qwerty1234.webp');
        $article = $this->article(['main_image' => 'articles/qwerty1234.webp']);
        PictureRenamer::apply(PictureRenamer::plan());
        $new = $article->fresh()->main_image;

        $this->get('/storage/articles/qwerty1234.webp')->assertStatus(301)->assertRedirect(asset('storage/'.$new));
        $this->get('/storage/articles/never-existed.webp')->assertNotFound();
        $this->get('/storage/articles/../../.env')->assertNotFound();
    }

    public function test_running_it_again_changes_nothing_and_a_renamed_page_gets_a_new_name_without_a_chain(): void
    {
        $this->putFile('articles/qwerty1234.webp');
        $article = $this->article(['main_image' => 'articles/qwerty1234.webp']);
        PictureRenamer::apply(PictureRenamer::plan());
        $first = $article->fresh()->main_image;

        $this->assertSame([], PictureRenamer::plan(), 'a picture that is named after its page is left alone');

        $article->update(['title' => ['en' => 'Ibn Battuta and the Rihla']]);
        PictureRenamer::apply(PictureRenamer::plan());
        $second = $article->fresh()->main_image;

        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('articles/ibn-battuta-and-the-rihla-', $second);
        $this->assertSame($second, PictureRedirect::target('articles/qwerty1234.webp'), 'the very old address goes straight to the newest name');
        $this->assertSame($second, PictureRedirect::target($first));
    }

    public function test_a_file_used_in_two_places_is_renamed_once_and_both_places_follow(): void
    {
        $this->putFile('categories/shared123.webp');
        $cat = Category::create(['name' => ['en' => 'Science'], 'slug' => 'science', 'image' => 'categories/shared123.webp']);
        $person = Person::create(['name' => 'Someone', 'photo' => 'categories/shared123.webp']);

        PictureRenamer::apply(PictureRenamer::plan());

        $this->assertSame($cat->fresh()->image, $person->fresh()->photo);
        $this->assertStringStartsWith('categories/science-', $cat->fresh()->image);
        $this->assertSame(1, PictureRedirect::count());
    }

    public function test_a_file_that_is_missing_is_skipped_and_nothing_breaks(): void
    {
        $article = $this->article(['main_image' => 'articles/gone.webp']);

        $this->assertSame([], PictureRenamer::plan());
        $this->assertSame('articles/gone.webp', $article->fresh()->main_image);
    }

    public function test_the_commands_do_nothing_without_apply_and_work_with_it(): void
    {
        $this->putFile('articles/qwerty1234.webp');
        $article = $this->article(['main_image' => 'articles/qwerty1234.webp']);
        $article->setTranslation('main_image_alt', 'en', '');
        $article->saveQuietly();

        $this->artisan('pictures:rename')->assertSuccessful();
        $this->assertSame('articles/qwerty1234.webp', $article->fresh()->main_image);
        $this->artisan('pictures:fill-alt')->assertSuccessful();
        $this->assertSame('', (string) $article->fresh()->getTranslation('main_image_alt', 'en', false));

        $this->artisan('pictures:rename', ['--apply' => true])->assertSuccessful();
        $this->artisan('pictures:fill-alt', ['--apply' => true])->assertSuccessful();
        $this->assertNotSame('articles/qwerty1234.webp', $article->fresh()->main_image);
        $this->assertSame('Ibn Battuta: The Muslim Traveller', $article->fresh()->getTranslation('main_image_alt', 'en'));
    }

    public function test_the_logo_and_the_menu_challenge_picture_are_renamed_too(): void
    {
        $this->putFile('site/logo-random1.webp');
        $this->putFile('menu/challenge-random2.webp');
        \App\Models\Setting::put('site_name', 'InfoFluxes');
        \App\Models\Setting::put('logo', 'site/logo-random1.webp');
        $cat = Category::create(['name' => ['en' => 'Science and Knowledge'], 'slug' => 'science', 'custom_data' => ['menu_style' => 'wide', 'menu_challenge_image' => 'menu/challenge-random2.webp']]);

        $result = PictureRenamer::apply(PictureRenamer::plan());

        $this->assertSame(2, $result['renamed']);
        $this->assertStringStartsWith('site/infofluxes-logo-', \App\Models\Setting::get('logo'));
        $data = $cat->fresh()->custom_data;
        $this->assertStringStartsWith('menu/science-and-knowledge-menu-challenge-', $data['menu_challenge_image']);
        $this->assertSame('wide', $data['menu_style'], 'the other custom data stays');
        $this->assertNotNull(PictureRedirect::target('site/logo-random1.webp'));
        \App\Models\Setting::forget();
    }
}
