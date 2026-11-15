<?php

namespace Tests\Feature;

use App\Filament\Resources\Faqs\FaqResource;
use App\Models\Faq;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 66: the FAQ page as an accordion. */
class Step66Test extends TestCase
{
    use RefreshDatabase;

    private function faq(string $q, string $a, array $extra = []): Faq
    {
        return Faq::create(array_merge(['question' => ['en' => $q], 'answer' => ['en' => $a], 'sort_order' => Faq::count() + 1, 'is_active' => true], $extra));
    }

    public function test_the_page_lists_the_questions_as_an_accordion_with_a_link_for_each(): void
    {
        $a = $this->faq('Who writes it?', '<p>A small team.</p>');
        $this->faq('Can I take part?', '<p>Yes, <strong>please</strong>.</p>');

        $html = $this->get('/faq')->assertOk()->assertSee('Frequently asked questions')->assertSee('Who writes it?')->assertSee('A small team.')->getContent();
        $this->assertSame(2, substr_count($html, '<details class="acc faq__item"'));
        $this->assertStringContainsString('id="q-'.$a->id.'"', $html);
        $this->assertStringContainsString('<strong>please</strong>', $html);
        $this->assertStringContainsString('js/faq.js', $html);
        $this->assertStringContainsString('data-acc-toggle', $html);
        $this->assertStringContainsString('data-faq-search', $html);
    }

    public function test_hidden_questions_are_not_shown_and_an_empty_page_says_so(): void
    {
        $this->get('/faq')->assertOk()->assertSee('There are no questions yet.')->assertDontSee('data-faq-search', false);

        $this->faq('Shown?', '<p>Yes.</p>');
        $this->faq('Hidden one', '<p>No.</p>', ['is_active' => false]);
        $this->get('/faq')->assertSee('Shown?')->assertDontSee('Hidden one');
    }

    public function test_headings_group_the_questions_in_the_order_of_their_first_question(): void
    {
        $this->faq('Loose question', '<p>x</p>');
        $this->faq('Taking part one', '<p>x</p>', ['group' => ['en' => 'Taking part']]);
        $this->faq('About one', '<p>x</p>', ['group' => ['en' => 'About the site']]);
        $this->faq('Taking part two', '<p>x</p>', ['group' => ['en' => 'Taking part']]);

        $html = $this->get('/faq')->getContent();
        $this->assertLessThan(strpos($html, 'Taking part</h2>'), strpos($html, 'Loose question'), 'no heading first');
        $this->assertLessThan(strpos($html, 'About the site</h2>'), strpos($html, 'Taking part</h2>'));
        $this->assertLessThan(strpos($html, 'About the site</h2>'), strpos($html, 'Taking part two'), 'Taking part two sits with its heading, before About');
    }

    public function test_dangerous_answers_are_cleaned(): void
    {
        $this->faq('Q?', '<p onclick="x()">Hi</p><script>alert(1)</script>');
        $html = $this->get('/faq')->getContent();
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_google_gets_the_questions_as_structured_data(): void
    {
        $this->faq('Who writes it?', '<p>A <b>small</b> team.</p>');
        $html = $this->get('/faq')->getContent();

        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('"name":"Who writes it?"', $html);
        $this->assertStringContainsString('"text":"A small team."', $html);
    }

    public function test_the_footer_and_the_sitemap_list_it_only_when_there_are_questions(): void
    {
        $this->get('/')->assertDontSee(route('faq'), false);
        $this->get('/sitemap.xml')->assertDontSee('/faq');

        $this->faq('Q?', '<p>A.</p>');
        $this->get('/')->assertSee(route('faq'), false);
        $this->get('/sitemap.xml')->assertSee(route('faq'), false);
    }

    public function test_the_old_faq_page_is_reserved_so_no_page_takes_the_address(): void
    {
        $this->assertContains('faq', Page::RESERVED);
        $this->get('/faq')->assertOk();
    }

    public function test_the_old_page_is_moved_into_questions_by_the_migration(): void
    {
        $page = Page::create(['title' => ['en' => 'FAQ'], 'slug' => 'faq', 'status' => 'published', 'in_footer' => true, 'body' => ['en' => '<h2>One?</h2><p>First.</p><h2>Two?</h2><p>Second.</p>']]);
        Faq::query()->delete();
        \Illuminate\Support\Facades\Schema::drop('faqs');
        (require database_path('migrations/2026_12_29_100000_create_faqs_table.php'))->up();

        $this->assertSame(['One?', 'Two?'], Faq::orderBy('sort_order')->get()->map->mainQuestion()->all());
        $this->assertStringContainsString('First.', (string) Faq::orderBy('sort_order')->first()->getTranslation('answer', 'en'));
        $this->assertSame('draft', $page->fresh()->status);
        $this->assertFalse((bool) $page->fresh()->in_footer);
    }

    public function test_the_admin_screen_follows_the_pages_permission(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(FaqResource::canViewAny());

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->assertTrue(FaqResource::canViewAny());
        $this->get('/admin/faqs')->assertOk();
    }

    public function test_saving_cleans_the_answer(): void
    {
        $data = FaqResource::clean(['answer' => ['en' => '<p onclick="x()">Hi <span style="position:fixed">x</span></p>']]);
        $this->assertStringNotContainsString('onclick', $data['answer']['en']);
        $this->assertStringNotContainsString('position', $data['answer']['en']);
    }
}
