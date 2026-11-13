<?php

namespace App\Filament\Resources\Newsletter\Pages;

use App\Filament\Resources\Newsletter\NewsletterIssueResource;
use App\Support\Html;
use App\Support\NewsletterSender;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageNewsletterIssues extends ManageRecords
{
    protected static string $resource = NewsletterIssueResource::class;

    protected ?string $heading = 'Send newsletter';

    public function getSubheading(): ?string
    {
        return NewsletterSender::audience().' people are subscribed now. An issue goes to them '.NewsletterSender::perRun().' at a time.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New issue')->modalWidth(Width::FiveExtraLarge)
                ->mutateDataUsing(fn (array $data) => ['body' => Html::clean((string) ($data['body'] ?? '')), 'status' => 'draft', 'created_by' => auth()->id()] + $data),
        ];
    }
}
