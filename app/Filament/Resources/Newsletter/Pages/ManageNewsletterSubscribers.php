<?php

namespace App\Filament\Resources\Newsletter\Pages;

use App\Filament\Resources\Newsletter\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageNewsletterSubscribers extends ManageRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected ?string $heading = 'Newsletter';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Download subscribers (CSV)')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(function () {
                    $rows = NewsletterSubscriber::query()->where('status', 'active')->orderBy('confirmed_at')->get(['email', 'language', 'confirmed_at']);

                    return response()->streamDownload(function () use ($rows) {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['email', 'language', 'subscribed_on']);
                        foreach ($rows as $row) {
                            // A cell that starts with = + - or @ could run as a formula in a spreadsheet: make it plain text.
                            $email = preg_match('/^[=+\-@]/', $row->email) ? "'".$row->email : $row->email;
                            fputcsv($out, [$email, $row->language, optional($row->confirmed_at)->toDateString()]);
                        }
                        fclose($out);
                    }, 'newsletter-subscribers-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
                }),
        ];
    }
}
