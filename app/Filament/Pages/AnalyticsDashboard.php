<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\GaException;
use App\Support\GoogleAnalytics;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use UnitEnum;

/**
 * Analytics: what is going on at the site, read from Google Analytics 4, in plain words.
 * Only the super user opens it. The numbers are kept 10 minutes; "Refresh" asks Google again.
 */
class AnalyticsDashboard extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Analytics';

    protected static ?string $slug = 'analytics';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.analytics';

    #[Url(as: 'days')]
    public string $range = '28';

    public string $propertyId = '';

    public $keyFile = null;

    public bool $fresh = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperuser();
    }

    public function mount(): void
    {
        $this->propertyId = GoogleAnalytics::propertyId();
        if (! in_array($this->range, ['7', '28', '90'], true)) {
            $this->range = '28';
        }
    }

    public function configured(): bool
    {
        return GoogleAnalytics::configured();
    }

    public function status(): array
    {
        return ['property' => GoogleAnalytics::propertyId(), 'key' => GoogleAnalytics::hasKey(), 'email' => GoogleAnalytics::serviceEmail()];
    }

    /** @return array{ok: bool, error?: string, data?: array, live?: array|null, live_error?: string} */
    public function report(): array
    {
        if (! $this->configured()) {
            return ['ok' => false];
        }
        try {
            $data = GoogleAnalytics::dashboard((int) $this->range, $this->fresh);
        } catch (GaException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => 'The numbers could not be loaded now ('.class_basename($e).'). Try again in a few minutes.'];
        }
        $this->fresh = false;
        $out = ['ok' => true, 'data' => $data, 'live' => null];
        try {
            $out['live'] = GoogleAnalytics::realtime();
        } catch (\Throwable $e) {
            $out['live_error'] = $e instanceof GaException ? $e->getMessage() : 'The live count is not available now.';
        }

        return $out;
    }

    public function refreshNumbers(): void
    {
        $this->fresh = true;
        \Illuminate\Support\Facades\Cache::forget('ga:token');
    }

    public function saveSetup(): void
    {
        $id = preg_replace('/\D/', '', $this->propertyId);
        if ($id === '' || strlen($id) < 5 || strlen($id) > 14) {
            Notification::make()->danger()->title('The Property ID is a number, for example 123456789.')->send();

            return;
        }
        if ($this->keyFile) {
            $this->validate(['keyFile' => ['file', 'max:100']]);
            $error = GoogleAnalytics::saveKey((string) file_get_contents($this->keyFile->getRealPath()));
            $this->keyFile->delete();
            $this->keyFile = null;
            if ($error) {
                Notification::make()->danger()->title($error)->send();

                return;
            }
        }
        Setting::put('analytics_property_id', $id);
        GoogleAnalytics::forgetReports();
        $this->propertyId = $id;
        $this->fresh = true;
        Notification::make()->success()->title('Saved.')->send();
    }

    public function removeKey(): void
    {
        GoogleAnalytics::removeKey();
        Notification::make()->success()->title('The key file is deleted from the server.')->send();
    }

    public function num(float|int $n): string
    {
        return number_format((float) $n, 0, '.', ',');
    }

    public function duration(float $seconds): string
    {
        $s = (int) round($seconds);

        return intdiv($s, 60).'m '.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT).'s';
    }

    /** "+12%" or "-5%" against the period before; null when there is nothing to compare with. @return array{text: string, dir: string}|null */
    public function change(float $now, float $before): ?array
    {
        if ($before <= 0) {
            return null;
        }
        $pct = round(($now - $before) / $before * 100);

        return ['text' => ($pct > 0 ? '+' : '').$pct.'%', 'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'same')];
    }
}
