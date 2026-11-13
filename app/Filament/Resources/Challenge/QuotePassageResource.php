<?php

namespace App\Filament\Resources\Challenge;

use App\Filament\Resources\Challenge\Pages\ManageQuotePassages;
use App\Filament\Resources\ChallengeResource;
use App\Models\QuotePassage;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** "Who said it?": a quote, the person, and the tradition. */
class QuotePassageResource extends ChallengeResource
{
    protected static ?string $model = QuotePassage::class;

    protected static ?string $navigationLabel = 'Who said it?';

    protected static ?string $modelLabel = 'quote';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->label('Tradition of the speaker')->options(QuotePassage::KINDS)->required()->native(true),
            Textarea::make('quote')->label('The quote')->required()->rows(4),
            TextInput::make('speaker')->label('Who said it')->required()->maxLength(255),
            TextInput::make('speaker_note')->label('Dates and role of the speaker')->maxLength(255),
            TextInput::make('source')->label('The work or the occasion')->maxLength(255),
            Toggle::make('is_attributed')->label('Only traditionally credited to this speaker')->helperText('The test then says so after the answer.'),
            Textarea::make('note')->label('One line of context (shown after the answer)')->rows(2),
            Toggle::make('is_active')->label('Used in the test')->default(true),
            Toggle::make('is_checked')->label('Checked against a printed edition by a person'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('id')->label('Code')->formatStateUsing(fn ($state) => '['.$state.']')->sortable(),
            TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => QuotePassage::KINDS[$state] ?? $state)->sortable(),
            TextColumn::make('speaker')->searchable()->limit(30),
            TextColumn::make('quote')->limit(70)->searchable(),
            IconColumn::make('is_attributed')->label('Credited')->boolean()->toggleable(),
            ...static::flagColumns(),
            TextColumn::make('times_shown')->label('Shown')->sortable()->toggleable(),
            TextColumn::make('times_correct')->label('Right')->sortable()->toggleable(),
        ])->filters([
            SelectFilter::make('kind')->options(QuotePassage::KINDS),
        ]);

        return static::standardActions($table);
    }

    public static function getPages(): array
    {
        return ['index' => ManageQuotePassages::route('/')];
    }
}
