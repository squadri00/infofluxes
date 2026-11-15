<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Support\Newsletter;
use App\Support\SiteControls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 69: the newsletter card (bottom left). */
class Step69Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    public function test_the_card_is_on_the_pages_with_the_form_the_button_and_the_fine_print(): void
    {
        Page::create(['title' => ['en' => 'Privacy'], 'slug' => 'privacy', 'status' => 'published', 'body' => ['en' => '<p>x</p>']]);
        Page::create(['title' => ['en' => 'Terms of use'], 'slug' => 'terms', 'status' => 'published', 'body' => ['en' => '<p>x</p>']]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-nl-card', $html);
        $this->assertStringContainsString('Every new article in your inbox', $html);
        $this->assertStringContainsString('placeholder="email@example.com"', $html);
        $this->assertStringContainsString('Sign up for free', $html);
        $this->assertStringContainsString('By signing up you agree to our <a href="'.route('article.show', 'privacy').'">Privacy</a> and <a href="'.route('article.show', 'terms').'">Terms of use</a>.', $html);
        $this->assertStringContainsString('js/newsletter-popup.js', $html);
        $this->assertStringContainsString('name="website_url"', $html, 'the hidden box for robots');
        $this->assertStringContainsString('data-delay="8"', $html);
        $this->assertStringContainsString('data-days="14"', $html);
    }

    public function test_the_footer_subscribe_link_still_goes_to_the_page_and_opens_the_card_with_the_script(): void
    {
        $this->get('/')->assertSee('href="'.route('newsletter.show').'" data-nl-open', false);
    }

    public function test_it_is_not_on_the_newsletter_pages_and_follows_its_switches(): void
    {
        $this->get('/newsletter')->assertOk()->assertDontSee('data-nl-card', false);

        SiteControls::set('newsletter_popup', false);
        $this->get('/')->assertDontSee('data-nl-card', false)->assertSee('data-nl-open', false);

        SiteControls::set('newsletter_popup', true);
        Setting::put('newsletter_on', false);
        $this->get('/')->assertDontSee('data-nl-card', false);
    }

    public function test_the_delay_and_the_days_come_from_the_settings_within_limits(): void
    {
        Setting::put('newsletter_popup_delay', 30);
        Setting::put('newsletter_popup_days', 3);
        $this->assertSame(30, Newsletter::popupDelay());
        $this->assertSame(3, Newsletter::popupDays());
        $this->get('/')->assertSee('data-delay="30"', false)->assertSee('data-days="3"', false);

        Setting::put('newsletter_popup_delay', 9999);
        Setting::put('newsletter_popup_days', 0);
        $this->assertSame(120, Newsletter::popupDelay());
        $this->assertSame(1, Newsletter::popupDays());
    }

    public function test_the_card_uses_the_same_json_answers_as_the_page(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $r = $this->postJson('/newsletter/subscribe', ['email' => 'reader@example.com'])->assertOk()->json();
        $this->assertSame('code', $r['step']);
        $this->assertSame('reader@example.com', $r['email']);
        $this->assertNotSame('', $r['message']);

        $bad = $this->postJson('/newsletter/subscribe', ['email' => 'not-an-email'])->assertOk()->json();
        $this->assertSame('form', $bad['step']);
        $this->assertFalse($bad['ok']);
    }

    public function test_the_script_waits_for_the_cookie_notice_and_remembers_a_close(): void
    {
        $js = file_get_contents(public_path('js/newsletter-popup.js'));
        foreach (['data-consent-banner', 'MutationObserver', 'infofluxes.nl.hiddenUntil', 'infofluxes.nl.done', 'data-nl-open', 'data-nl-close'] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle);
        }
    }
}
