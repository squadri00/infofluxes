<?php

namespace Tests\Feature;

use App\Filament\Resources\Social\Pages\ManageSocialLinks;
use App\Models\Page;
use App\Models\Setting;
use App\Models\SocialLink;
use App\Models\User;
use App\Support\Social;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 22: the social media admin and the complete footer. */
class Step22Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
    }

    private function footer(): string
    {
        $html = $this->get('/')->assertOk()->getContent();

        return substr($html, strpos($html, '<footer'));
    }

    public function test_the_footer_shows_the_active_accounts_in_order_as_branded_buttons(): void
    {
        SocialLink::create(['platform' => 'youtube', 'url' => 'https://youtube.com/@us', 'sort_order' => 2]);
        SocialLink::create(['platform' => 'facebook', 'url' => 'https://facebook.com/us', 'sort_order' => 1]);
        SocialLink::create(['platform' => 'tiktok', 'url' => 'https://tiktok.com/@us', 'is_active' => false]);

        $footer = $this->footer();

        $this->assertLessThan(strpos($footer, 'social__btn--youtube'), strpos($footer, 'social__btn--facebook'));
        $this->assertStringNotContainsString('social__btn--tiktok', $footer, 'switched off');
        $this->assertStringContainsString('<svg', substr($footer, strpos($footer, 'social__btn--facebook'), 600), 'a real drawing, not a letter');
        $this->assertStringContainsString('rel="noopener noreferrer me"', $footer);
    }

    public function test_an_unsafe_address_is_never_shown(): void
    {
        SocialLink::create(['platform' => 'facebook', 'url' => 'javascript:alert(1)']);

        $this->assertStringNotContainsString('javascript:', $this->footer());
        $this->assertCount(0, Social::links());
    }

    public function test_no_accounts_means_no_buttons(): void
    {
        $this->assertStringNotContainsString('class="social', $this->footer());
    }

    public function test_the_footer_has_the_about_text_the_pages_and_take_part_links(): void
    {
        Setting::put('footer_about', ['en' => 'A research space on religion.']);
        Page::create(['title' => ['en' => 'Privacy'], 'slug' => 'privacy', 'status' => 'published', 'in_footer' => true]);

        $footer = $this->footer();

        foreach (['A research space on religion.', 'Privacy', 'Challenge yourself', 'Log in', 'Sign up', 'Search the site', 'Sitemap'] as $word) {
            $this->assertStringContainsString($word, $footer);
        }
    }

    public function test_a_signed_in_person_sees_account_links_instead_of_log_in(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active']));

        $footer = $this->footer();

        $this->assertStringContainsString('My account', $footer);
        $this->assertStringContainsString('Send in an article', $footer);
        $this->assertStringNotContainsString('>Sign up<', $footer);
    }

    public function test_sign_up_is_not_in_the_footer_when_sign_ups_are_closed(): void
    {
        Setting::put('signups_open', '0');

        $this->assertStringNotContainsString('>Sign up<', $this->footer());
    }

    public function test_the_admin_list_is_for_the_super_user_only_and_adds_an_account_at_the_end(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        Livewire::test(ManageSocialLinks::class)->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        SocialLink::create(['platform' => 'facebook', 'url' => 'https://facebook.com/us', 'sort_order' => 4]);

        Livewire::test(ManageSocialLinks::class)
            ->callAction('create', ['platform' => 'instagram', 'url' => 'https://instagram.com/us', 'is_active' => true, 'sort_order' => 0])
            ->assertHasNoActionErrors();

        $this->assertSame(5, SocialLink::where('platform', 'instagram')->first()->sort_order, 'after the last one');
    }

    public function test_a_web_address_that_is_not_a_web_address_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(ManageSocialLinks::class)
            ->callAction('create', ['platform' => 'facebook', 'url' => 'javascript:alert(1)', 'is_active' => true])
            ->assertHasActionErrors(['url']);
    }

    public function test_every_network_has_a_name_a_colour_and_a_drawing(): void
    {
        foreach (array_keys(Social::PLATFORMS) as $platform) {
            $this->assertNotSame('', Social::options()[$platform]);
            $this->assertNotSame('', Social::colour($platform));
            $this->assertStringContainsString('<', Social::drawing($platform));
        }
    }
}
