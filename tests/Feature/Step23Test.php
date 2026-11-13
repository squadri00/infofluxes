<?php

namespace Tests\Feature;

use App\Filament\Resources\People\Pages\ManagePeople;
use App\Models\Article;
use App\Models\NewsletterSubscriber;
use App\Models\Person;
use App\Models\Setting;
use App\Models\User;
use App\Support\Newsletter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 23: the newsletter sign-up (a code by email) and the contributors. */
class Step23Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
        config(['mail.default' => 'array']);
        Mail::purge();
    }

    /** The emails that were "sent" so far, as plain text. */
    private function mails(): array
    {
        $transport = app('mailer')->getSymfonyTransport();

        return collect($transport->messages())->map(fn ($m) => $m->getOriginalMessage())->map(fn ($m) => [
            'to' => $m->getTo()[0]->getAddress(), 'subject' => $m->getSubject(), 'body' => $m->getTextBody(),
        ])->all();
    }

    private function lastCode(): string
    {
        $mails = $this->mails();
        preg_match('/\b(\d{6})\b/', end($mails)['body'], $m);

        return $m[1];
    }

    public function test_the_subscribe_page_and_the_footer_link_are_there(): void
    {
        $this->get(route('newsletter.show'))->assertOk()->assertSee('Subscribe to the newsletter')->assertSee('Send me a code');
        $this->get('/')->assertSee(route('newsletter.show'), false);
    }

    public function test_the_whole_sign_up_works_with_a_code_sent_by_email(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'Reader@Example.com'])->assertRedirect(route('newsletter.show'));

        $person = NewsletterSubscriber::first();
        $this->assertSame('reader@example.com', $person->email, 'lower case');
        $this->assertSame('pending', $person->status);
        $this->assertNotNull($person->code_hash);
        $this->assertSame('reader@example.com', $this->mails()[0]['to']);

        $code = $this->lastCode();
        $this->assertStringNotContainsString($code, (string) $person->code_hash, 'the code itself is never stored');

        $this->post(route('newsletter.confirm'), ['email' => 'reader@example.com', 'code' => $code])->assertRedirect(route('newsletter.show'));

        $person->refresh();
        $this->assertSame('active', $person->status);
        $this->assertNull($person->code_hash);
        $this->assertCount(2, $this->mails(), 'the code, then the welcome with the unsubscribe link');
        $this->assertStringContainsString('/newsletter/unsubscribe/'.$person->unsubscribe_token, $this->mails()[1]['body']);
    }

    public function test_a_wrong_code_counts_a_try_and_five_wrong_tries_lock_it(): void
    {
        Newsletter::request('a@example.com');

        for ($i = 1; $i <= 5; $i++) {
            $this->assertSame('wrong', Newsletter::confirm('a@example.com', '000000')['status']);
        }
        $this->assertSame('locked', Newsletter::confirm('a@example.com', $this->lastCode())['status'], 'even the right code, after five wrong ones');
    }

    public function test_an_old_code_runs_out(): void
    {
        Newsletter::request('a@example.com');
        $code = $this->lastCode();
        NewsletterSubscriber::first()->update(['code_expires_at' => now()->subMinute()]);

        $this->assertSame('expired', Newsletter::confirm('a@example.com', $code)['status']);
    }

    public function test_a_bad_address_is_refused_and_a_second_code_must_wait(): void
    {
        $this->assertSame('invalid', Newsletter::request('not an email')['status']);

        $this->assertSame('sent', Newsletter::request('a@example.com')['status']);
        $this->assertSame('wait', Newsletter::request('a@example.com')['status']);
    }

    public function test_an_address_that_is_already_subscribed_is_told_so(): void
    {
        Newsletter::request('a@example.com');
        Newsletter::confirm('a@example.com', $this->lastCode());

        $this->assertSame('already', Newsletter::request('a@example.com')['status']);
    }

    public function test_the_hidden_robot_box_stops_a_bot_without_telling_it(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'bot@example.com', 'website_url' => 'http://spam'])->assertRedirect(route('newsletter.show'));

        $this->assertSame(0, NewsletterSubscriber::count());
        $this->assertCount(0, $this->mails());
    }

    public function test_only_a_few_codes_a_day_go_to_one_address(): void
    {
        $key = 'newsletter-codes:'.sha1('a@example.com').':'.now()->toDateString();
        foreach (range(1, Newsletter::DAILY_CODES) as $i) {
            RateLimiter::hit($key, 86400);
        }

        $this->assertSame('limit', Newsletter::request('a@example.com')['status']);
    }

    public function test_unsubscribing_needs_the_secret_link_and_takes_a_click(): void
    {
        Newsletter::request('a@example.com');
        Newsletter::confirm('a@example.com', $this->lastCode());
        $token = NewsletterSubscriber::first()->unsubscribe_token;

        $this->get(route('newsletter.unsubscribe', $token))->assertOk()->assertSee('Yes, unsubscribe me');
        $this->assertSame('active', NewsletterSubscriber::first()->status, 'opening the link changes nothing');

        $this->post(route('newsletter.unsubscribe.do', $token))->assertOk()->assertSee('You are unsubscribed');
        $this->assertSame('unsubscribed', NewsletterSubscriber::first()->status);
    }

    public function test_switched_off_the_newsletter_is_closed_and_leaves_the_footer(): void
    {
        Setting::put('newsletter_on', false);

        $this->get(route('newsletter.show'))->assertNotFound();
        $this->get('/')->assertDontSee(route('newsletter.show'), false);
    }

    public function test_the_subscriber_list_is_for_the_super_user_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        Livewire::test(\App\Filament\Resources\Newsletter\Pages\ManageNewsletterSubscribers::class)->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        NewsletterSubscriber::create(['email' => 'a@example.com', 'status' => 'active', 'unsubscribe_token' => str_repeat('x', 40)]);

        Livewire::test(\App\Filament\Resources\Newsletter\Pages\ManageNewsletterSubscribers::class)->assertSuccessful()->assertSee('a@example.com');
    }

    // ---------------- contributors ----------------

    private function person(string $name, array $extra = []): Person
    {
        return Person::create(array_merge(['name' => $name, 'show_page' => true, 'role_title' => 'Research contributor', 'bio' => 'Writes about history.'], $extra));
    }

    public function test_the_contributors_page_lists_only_people_with_a_public_page(): void
    {
        $this->person('Amina Khan');
        $this->person('Hidden Person', ['show_page' => false]);

        $this->get(route('people.index'))->assertOk()->assertSee('Amina Khan')->assertSee('Research contributor')->assertDontSee('Hidden Person');
    }

    public function test_a_contributor_page_shows_their_words_and_their_published_articles_only(): void
    {
        $amina = $this->person('Amina Khan', ['page_body' => '<p>Her own words.</p><script>alert(1)</script>']);
        Article::create(['title' => ['en' => 'Her article'], 'slug' => 'her-article', 'status' => 'published', 'publish_date' => now()->subDay(), 'contributor_id' => $amina->id]);
        Article::create(['title' => ['en' => 'Her draft'], 'slug' => 'her-draft', 'status' => 'draft', 'contributor_id' => $amina->id]);
        Article::create(['title' => ['en' => 'Someone else'], 'slug' => 'someone-else', 'status' => 'published', 'publish_date' => now()->subDay()]);

        $this->get(route('people.show', 'amina-khan'))->assertOk()
            ->assertSee('Her own words.')->assertSee('Her article')
            ->assertDontSee('Her draft')->assertDontSee('Someone else')->assertDontSee('<script>alert(1)', false);
    }

    public function test_a_person_without_a_public_page_is_not_found_for_visitors_but_staff_can_preview(): void
    {
        $this->person('Quiet One', ['show_page' => false]);

        $this->get(route('people.show', 'quiet-one'))->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->get(route('people.show', 'quiet-one'))->assertOk()->assertSee('This page is switched off');
    }

    public function test_the_article_names_its_contributor_and_links_to_their_page(): void
    {
        $amina = $this->person('Amina Khan');
        $quiet = $this->person('Quiet One', ['show_page' => false]);
        Article::create(['title' => ['en' => 'First'], 'slug' => 'first', 'status' => 'published', 'publish_date' => now()->subDay(), 'contributor_id' => $amina->id]);
        Article::create(['title' => ['en' => 'Second'], 'slug' => 'second', 'status' => 'published', 'publish_date' => now()->subDay(), 'contributor_id' => $quiet->id]);

        $html = $this->get('/first')->assertOk()->assertSee('href="'.route('people.show', 'amina-khan').'"', false)->getContent();
        $this->assertStringContainsString('class="byline byline--topic"', $html, 'the old site\'s byline');
        $this->assertStringContainsString('byline__pic--letter', $html, 'a coloured letter when there is no photo');
        $this->assertStringContainsString('Research contributor', $html, 'the role');
        $this->assertStringContainsString('Published', $html, 'and the date');

        $this->get('/second')->assertOk()->assertSee('Quiet One')->assertDontSee(route('people.show', 'quiet-one'), false);
    }

    public function test_the_byline_shows_the_photo_when_there_is_one(): void
    {
        $amina = $this->person('Amina Khan', ['photo' => 'people/amina.webp']);
        Article::create(['title' => ['en' => 'First'], 'slug' => 'first', 'status' => 'published', 'publish_date' => now()->subDay(), 'contributor_id' => $amina->id]);

        $this->get('/first')->assertSee('class="byline__pic" src="'.asset('storage/people/amina.webp').'"', false);
    }

    public function test_the_card_of_an_article_carries_the_small_byline(): void
    {
        $amina = $this->person('Amina Khan');
        Article::create(['title' => ['en' => 'Credited one'], 'slug' => 'credited-one', 'status' => 'published', 'publish_date' => now()->subDay(), 'contributor_id' => $amina->id, 'is_featured' => true]);
        Article::create(['title' => ['en' => 'Plain one'], 'slug' => 'plain-one', 'status' => 'published', 'publish_date' => now()->subHour()]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('byline--card', $html);
        $this->assertSame(1, substr_count($html, 'byline--card'), 'only the credited article has one');
    }

    public function test_the_contributor_page_has_the_old_sites_parts(): void
    {
        $this->person('Amina Khan', ['tradition' => 'Sunni scholar', 'qualification_note' => 'Studied hadith for ten years.', 'link' => 'https://amina.example.org', 'bio' => "Line one.\nLine two."]);

        $html = $this->get(route('people.show', 'amina-khan'))->assertOk()->getContent();

        foreach (['person__photo--letter', 'Research contributor', 'Tradition or background: Sunni scholar', 'What qualifies them:', 'Studied hadith for ten years.', 'https://amina.example.org', 'Line one.<br />'] as $part) {
            $this->assertStringContainsString($part, $html, $part);
        }
    }

    public function test_the_index_uses_tiles_with_a_letter_or_a_photo(): void
    {
        $this->person('Amina Khan', ['tradition' => 'Sunni scholar']);
        $this->person('Photo Person', ['photo' => 'people/p.webp']);

        $html = $this->get(route('people.index'))->assertOk()->getContent();

        $this->assertStringContainsString('class="tiles person-tiles"', $html);
        $this->assertStringContainsString('tile__initial', $html);
        $this->assertStringContainsString(asset('storage/people/p.webp'), $html);
        $this->assertStringContainsString('Sunni scholar', $html);
    }

    public function test_an_unsafe_website_link_is_not_shown(): void
    {
        $this->person('Amina Khan', ['link' => 'javascript:alert(1)']);

        $this->get(route('people.show', 'amina-khan'))->assertOk()->assertDontSee('javascript:alert', false);
    }

    public function test_the_footer_links_to_contributors_only_when_someone_has_a_page(): void
    {
        $this->get('/')->assertDontSee(route('people.index'), false);

        $this->person('Amina Khan');
        $this->get('/')->assertSee(route('people.index'), false);
    }

    public function test_two_people_with_one_name_get_different_addresses_and_deleting_keeps_articles(): void
    {
        $a = $this->person('Same Name');
        $b = $this->person('Same Name');
        $this->assertNotSame($a->slug, $b->slug);

        $article = Article::create(['title' => ['en' => 'Kept'], 'slug' => 'kept', 'status' => 'published', 'publish_date' => now()->subDay(), 'contributor_id' => $a->id]);
        $a->forceDelete();
        $this->assertNull($article->fresh()->contributor_id);
        $this->assertNotNull($article->fresh());
    }

    public function test_the_contributors_admin_creates_a_person_with_an_address(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active']));
        Livewire::test(ManagePeople::class)->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        Livewire::test(ManagePeople::class)
            ->callAction('create', ['name' => 'Yusuf Ali', 'role_title' => 'Reviewer', 'show_page' => true])
            ->assertHasNoActionErrors();

        $this->assertSame('yusuf-ali', Person::first()->slug);
        $this->assertSame('K-'.Person::first()->id, Person::first()->code);
    }

    public function test_a_contributor_address_never_clashes_with_pages_or_articles(): void
    {
        $this->assertContains('people', \App\Models\Page::RESERVED);
        $this->assertContains('newsletter', \App\Models\Category::RESERVED_SLUGS);
    }
}
