<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 61: SEO and tracking settings, custom code, robots.txt, structured data. */
class Step61Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function article(): Article
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);

        return Article::create(['title' => ['en' => 'Moon article'], 'slug' => 'moon-article', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
    }

    public function test_titles_are_the_same_as_before_until_a_pattern_is_set(): void
    {
        $this->article();
        $this->get('/moon-article')->assertSee('<title>Moon article | InfoFluxes</title>', false);

        Setting::put('seo_title_pattern', ':page – :site');
        Setting::put('seo_home_title', 'InfoFluxes: religions by their own sources');
        $this->get('/moon-article')->assertSee('<title>Moon article – InfoFluxes</title>', false);
        $this->get('/')->assertSee('<title>InfoFluxes: religions by their own sources</title>', false);
    }

    public function test_a_page_without_a_description_gets_the_default(): void
    {
        Setting::put('seo_default_description', 'The default words for Google.');
        $this->get('/login')->assertSee('<meta name="description" content="The default words for Google.">', false);
    }

    public function test_hiding_the_site_adds_noindex_and_closes_robots(): void
    {
        $this->get('/')->assertDontSee('noindex', false);
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')->assertSee('Disallow: /admin')->assertSee('Sitemap: ', false)->assertDontSee("Disallow: /\n", false);

        Setting::put('seo_noindex_site', true);
        $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
    }

    public function test_verification_codes_keep_only_safe_characters(): void
    {
        Setting::put('seo_google_verification', 'abc_DEF-123"><script>x</script>');
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('<meta name="google-site-verification" content="abc_DEF-123scriptxscript">', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }

    public function test_the_home_page_and_articles_carry_structured_data(): void
    {
        $this->article();
        $home = $this->get('/')->getContent();
        $this->assertStringContainsString('application/ld+json', $home);
        $this->assertStringContainsString('SearchAction', $home);

        $article = $this->get('/moon-article')->getContent();
        $this->assertStringContainsString('"@type":"Article"', $article);
        $this->assertStringContainsString('"headline":"Moon article"', $article);
    }

    public function test_tracking_never_runs_on_localhost_and_uses_the_standard_snippets_on_the_real_site(): void
    {
        Setting::put('consent_on', false);
        Setting::put('seo_gtm_id', 'GTM-M57JT9XJ');
        $this->get('/')->assertDontSee('GTM-M57JT9XJ', false);

        $html = $this->get('https://infofluxes.com/')->getContent();
        $this->assertStringContainsString("'dataLayer','GTM-M57JT9XJ'", $html);
        $this->assertStringContainsString('ns.html?id=GTM-M57JT9XJ', $html);
        $this->assertLessThan(strpos($html, '<title>'), strpos($html, 'GTM-M57JT9XJ'), 'the container is in the head, before the title');
        $this->assertGreaterThan(strpos($html, '<body>'), strpos($html, 'ns.html?id='), 'the noscript part is in the body');
    }

    public function test_analytics_alone_is_used_only_without_tag_manager_and_a_bad_id_is_ignored(): void
    {
        Setting::put('consent_on', false);
        Setting::put('seo_ga4_id', 'G-ABC123DEF4');
        $this->assertStringContainsString("gtag('config','G-ABC123DEF4')", $this->get('https://infofluxes.com/')->getContent());

        Setting::put('seo_gtm_id', 'GTM-AAAA111');
        $html = $this->get('https://infofluxes.com/')->getContent();
        $this->assertStringContainsString('GTM-AAAA111', $html);
        $this->assertStringNotContainsString('G-ABC123DEF4', $html);

        Setting::put('seo_gtm_id', 'GTM-"><script>');
        Setting::put('seo_ga4_id', null);
        $this->assertSame('', Seo::gtmId());
    }

    public function test_staff_are_not_tracked_unless_switched_on(): void
    {
        Setting::put('consent_on', false);
        Setting::put('seo_gtm_id', 'GTM-M57JT9XJ');
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->get('https://infofluxes.com/')->assertDontSee('GTM-M57JT9XJ', false);

        Setting::put('seo_track_staff', true);
        $this->get('https://infofluxes.com/')->assertSee('GTM-M57JT9XJ', false);
    }

    public function test_custom_code_is_printed_where_it_belongs(): void
    {
        Setting::put('seo_code_head', '<meta name="x-head" content="1">');
        Setting::put('seo_code_body_start', '<!-- body-start -->');
        Setting::put('seo_code_body_end', '<!-- body-end -->');
        $html = $this->get('/')->getContent();

        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'x-head'));
        $this->assertGreaterThan(strpos($html, '<body>'), strpos($html, '<!-- body-start -->'));
        $this->assertLessThan(strpos($html, '</body>'), strpos($html, '<!-- body-end -->'));
        $this->assertGreaterThan(strpos($html, '</main>'), strpos($html, '<!-- body-end -->'));
    }

    public function test_only_the_super_user_opens_the_seo_screen_and_saving_cleans_the_values(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(\App\Filament\Pages\SeoSettings::canAccess());

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->assertTrue(\App\Filament\Pages\SeoSettings::canAccess());
        \Livewire\Livewire::test(\App\Filament\Pages\SeoSettings::class)
            ->fillForm(['seo_gtm_id' => ' gtm-m57jt9xj ', 'seo_google_verification' => 'a b"c', 'seo_noindex_site' => true])->call('save')->assertHasNoFormErrors();
        $this->assertSame('GTM-M57JT9XJ', Setting::get('seo_gtm_id'));
        $this->assertSame('abc', Setting::get('seo_google_verification'));
        $this->assertTrue((bool) filter_var(Setting::get('seo_noindex_site'), FILTER_VALIDATE_BOOLEAN));
    }

    public function test_with_the_cookie_notice_on_counting_is_held_back_until_the_visitor_agrees(): void
    {
        Setting::put('seo_gtm_id', 'GTM-M57JT9XJ');
        $html = $this->get('https://infofluxes.com/')->getContent();

        $this->assertStringContainsString('data-consent-banner', $html);
        $this->assertStringContainsString('js/consent.js', $html);
        $this->assertStringContainsString('Cookie settings', $html);
        $this->assertStringContainsString('<script type="text/plain" data-consent="analytics">', $html, 'held back, not run');
        $this->assertStringNotContainsString('<script>(function(w,d,s,l,i)', $html);
        $this->assertStringNotContainsString('ns.html?id=', $html);
    }

    public function test_the_notice_can_be_switched_off_and_it_is_on_by_default(): void
    {
        $this->assertTrue(Seo::consentOn());
        $this->get('/')->assertSee('data-consent-banner', false);

        Setting::put('consent_on', false);
        $this->get('/')->assertDontSee('data-consent-banner', false)->assertDontSee('Cookie settings');
    }

    public function test_google_analytics_alone_is_also_held_back(): void
    {
        Setting::put('seo_ga4_id', 'G-ABC123DEF4');
        $html = $this->get('https://infofluxes.com/')->getContent();
        $this->assertStringContainsString('data-src="https://www.googletagmanager.com/gtag/js?id=G-ABC123DEF4"', $html);
        $this->assertStringNotContainsString('<script async src=', $html);
    }

    public function test_the_notice_script_exists_and_has_both_buttons(): void
    {
        $js = file_get_contents(public_path('js/consent.js'));
        $this->assertStringContainsString('data-consent-accept', $js);
        $this->assertStringContainsString('data-consent-reject', $js);
        $this->assertStringContainsString('if_consent', $js);
        $this->get('/')->assertSee('data-consent-accept', false)->assertSee('data-consent-reject', false);
    }
}
