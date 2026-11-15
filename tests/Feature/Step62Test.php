<?php

namespace Tests\Feature;

use App\Filament\Pages\AnalyticsDashboard;
use App\Models\Setting;
use App\Models\User;
use App\Support\GaException;
use App\Support\GoogleAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 62: the Google Analytics dashboard (Google is faked; no real key is used). */
class Step62Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Cache::flush();
        Storage::fake('local');
    }

    private function keyJson(): string
    {
        // Only for making a test key: on Windows (XAMPP) PHP may need to be told where openssl.cnf is.
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        foreach ([PHP_BINARY ? dirname(PHP_BINARY).'/extras/ssl/openssl.cnf' : '', 'D:/xampp/apache/conf/openssl.cnf'] as $cnf) {
            if (! openssl_pkey_new($options) && is_file($cnf)) {
                $options['config'] = $cnf;
            }
        }
        $res = openssl_pkey_new($options);
        openssl_pkey_export($res, $pem, null, isset($options['config']) ? ['config' => $options['config']] : []);

        return json_encode(['type' => 'service_account', 'client_email' => 'reader@project.iam.gserviceaccount.com', 'private_key' => $pem]);
    }

    private function connect(): void
    {
        $this->assertNull(GoogleAnalytics::saveKey($this->keyJson()));
        Setting::put('analytics_property_id', '123456789');
    }

    private function fakeGoogle(int $realtime = 3): void
    {
        $day = now()->format('Ymd');
        Http::fake(function (Request $request) use ($day, $realtime) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600]);
            }
            $body = $request->data();
            $dims = array_column($body['dimensions'] ?? [], 'name');
            $row = fn (array $d, array $m) => ['dimensionValues' => array_map(fn ($v) => ['value' => (string) $v], $d), 'metricValues' => array_map(fn ($v) => ['value' => (string) $v], $m)];
            if (str_contains($request->url(), 'runRealtimeReport')) {
                return Http::response(['rows' => $dims ? [$row(['Home'], [2]), $row(['Moon article'], [1])] : [$row([], [$realtime])]]);
            }
            if (! $dims) {
                return Http::response(['rows' => [$row(['date_range_0'], [120, 80, 150, 400, 95, 0.61]), $row(['date_range_1'], [100, 70, 130, 300, 90, 0.5])]]);
            }
            return Http::response(['rows' => match ($dims[0]) {
                'date' => [$row([$day], [42, 77])],
                'pageTitle' => [$row(['Moon article', '/moon-article'], [90, 60]), $row(['Home', '/'], [30, 25])],
                'sessionDefaultChannelGroup' => [$row(['Organic Search'], [70]), $row(['Direct'], [30])],
                'sessionSource' => [$row(['google'], [60]), $row(['(direct)'], [30])],
                'country' => [$row(['Pakistan'], [50]), $row(['United Kingdom'], [20])],
                'deviceCategory' => [$row(['mobile'], [80]), $row(['desktop'], [40])],
                default => [],
            }]);
        });
    }

    public function test_a_key_file_is_checked_before_it_is_saved(): void
    {
        $this->assertNotNull(GoogleAnalytics::saveKey('not json'));
        $this->assertNotNull(GoogleAnalytics::saveKey(json_encode(['type' => 'service_account', 'client_email' => 'a@b.c', 'private_key' => 'nope'])));
        $this->assertFalse(GoogleAnalytics::hasKey());

        $this->assertNull(GoogleAnalytics::saveKey($this->keyJson()));
        $this->assertTrue(GoogleAnalytics::hasKey());
        $this->assertSame('reader@project.iam.gserviceaccount.com', GoogleAnalytics::serviceEmail());
        Storage::disk('local')->assertExists('analytics/service-account.json');
        $this->assertFalse(file_exists(public_path('analytics/service-account.json')), 'never in the public folder');
    }

    public function test_it_asks_google_with_a_signed_pass_and_reads_the_numbers(): void
    {
        $this->connect();
        $this->fakeGoogle();

        $d = GoogleAnalytics::dashboard(28);
        $this->assertSame(120.0, $d['now']['activeUsers']);
        $this->assertSame(100.0, $d['before']['activeUsers']);
        $this->assertCount(28, $d['per_day']);
        $this->assertSame(42, $d['per_day'][27]['visitors']);
        $this->assertSame('Moon article', $d['pages'][0]['title']);
        $this->assertSame(70, $d['channels'][0]['value']);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && str_contains($r['assertion'], '.') && $r['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'properties/123456789:runReport') && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_the_numbers_are_kept_for_ten_minutes_and_refresh_asks_again(): void
    {
        $this->connect();
        $this->fakeGoogle();
        GoogleAnalytics::dashboard(28);
        $calls = count(Http::recorded());
        GoogleAnalytics::dashboard(28);
        $this->assertSame($calls, count(Http::recorded()), 'second time comes from the memory');

        GoogleAnalytics::dashboard(28, true);
        $this->assertGreaterThan($calls, count(Http::recorded()));
    }

    public function test_google_problems_are_explained_in_plain_words(): void
    {
        $this->connect();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'analyticsdata.googleapis.com/*' => Http::response(['error' => ['code' => 403, 'message' => 'User does not have sufficient permissions', 'status' => 'PERMISSION_DENIED']], 403),
        ]);
        try {
            GoogleAnalytics::dashboard(28);
            $this->fail('should throw');
        } catch (GaException $e) {
            $this->assertStringContainsString('reader@project.iam.gserviceaccount.com', $e->getMessage());
            $this->assertStringContainsString('Viewer', $e->getMessage());
        }

    }

    public function test_a_switched_off_api_is_explained(): void
    {
        $this->connect();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'analyticsdata.googleapis.com/*' => Http::response(['error' => ['code' => 403, 'message' => 'Google Analytics Data API has not been used in project 1 before or it is disabled.', 'status' => 'PERMISSION_DENIED']], 403),
        ]);
        $this->expectException(GaException::class);
        $this->expectExceptionMessage('switched off');
        GoogleAnalytics::dashboard(7);
    }

    public function test_the_page_is_for_the_super_user_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(AnalyticsDashboard::canAccess());
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->assertTrue(AnalyticsDashboard::canAccess());
    }

    public function test_before_it_is_connected_the_page_shows_the_steps(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        Livewire::test(AnalyticsDashboard::class)->assertSee('Connect Google Analytics')->assertSee('Property access management')->assertSee('not connected');
    }

    public function test_the_page_shows_the_numbers_in_plain_words(): void
    {
        $this->connect();
        $this->fakeGoogle(3);
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(AnalyticsDashboard::class)
            ->assertSee('Visitors each day')->assertSee('Most read pages')->assertSee('Moon article')
            ->assertSee('Search engines (Google, Bing...)')->assertSee('Pakistan')->assertSee('Phone')
            ->assertSee('3')->assertSee('people are')->assertSee('+20%')
            ->assertSee('connected');
    }

    public function test_saving_the_setup_stores_the_property_and_rejects_a_wrong_id(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        Livewire::test(AnalyticsDashboard::class)->set('propertyId', 'G-ABCDEF')->call('saveSetup');
        $this->assertSame('', GoogleAnalytics::propertyId());

        Livewire::test(AnalyticsDashboard::class)->set('propertyId', ' 123 456 789 ')->call('saveSetup');
        $this->assertSame('123456789', GoogleAnalytics::propertyId());
    }

    public function test_the_key_can_be_deleted_from_the_page(): void
    {
        $this->connect();
        $this->fakeGoogle();
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        Livewire::test(AnalyticsDashboard::class)->call('removeKey');
        $this->assertFalse(GoogleAnalytics::hasKey());
        $this->assertFalse(GoogleAnalytics::configured());
    }
}
