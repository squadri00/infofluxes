<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Filament\Support\Translated;
use App\Models\Faq;
use App\Support\Html;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** FAQ: the questions and answers of the /faq page, shown as an accordion. Drag the rows to change the order. */
class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'FAQ';

    protected static ?string $modelLabel = 'question';

    protected static ?int $navigationSort = 8;

    private static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('pages') ?? false;
    }

    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }
    public static function canEdit(Model $record): bool { return static::allowed(); }
    public static function canDelete(Model $record): bool { return static::allowed(); }
    public static function canDeleteAny(): bool { return static::allowed(); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...Translated::text('question', 'The question', required: true, max: 300),
            ...Translated::rich('answer', 'The answer', required: true, help: 'Simple English. Salutations apply: Prophet Muhammad ﷺ, other prophets (AS), companions (RA), Allah (SWT).'),
            ...Translated::text('group', 'Heading to list it under (optional)', max: 120, help: 'Questions with the same heading are listed together, for example "About the site" or "Taking part". Leave empty for no heading.'),
            Toggle::make('is_active')->label('Show it on the FAQ page')->default(true),
        ])->columns(1);
    }

    /** The answer is cleaned the same way as every long text. */
    public static function clean(array $data): array
    {
        foreach ((array) ($data['answer'] ?? []) as $locale => $html) {
            $data['answer'][$locale] = Html::clean((string) $html);
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('question')->label('Question')->state(fn (Faq $r) => $r->mainQuestion())->searchable(['question'])->limit(100)->wrap(),
                TextColumn::make('group')->label('Heading')->state(fn (Faq $r) => $r->getTranslation('group', config('infofluxes.default_locale'), true))->placeholder('—')->toggleable(),
                ToggleColumn::make('is_active')->label('Shown'),
            ])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the question')->modalWidth(Width::FourExtraLarge)->mutateDataUsing(fn (array $data) => static::clean($data)),
                DeleteAction::make()->label('Delete'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFaqs::route('/')];
    }
}
