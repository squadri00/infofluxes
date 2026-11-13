<?php

namespace App\Filament\Resources\Challenge;

use App\Filament\Resources\Challenge\Pages\ManageQuizPassages;
use App\Filament\Resources\ChallengeResource;
use App\Models\QuizPassage;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** "Which one is from the Quran?": the Arabic passages (the Quran ones are the right answers, the others are the disguises). */
class QuizPassageResource extends ChallengeResource
{
    protected static ?string $model = QuizPassage::class;

    protected static ?string $navigationLabel = 'Quran-style test';

    protected static ?string $modelLabel = 'Arabic passage';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->label('Kind of source')->options(QuizPassage::KINDS)->required()->native(true),
            TextInput::make('reference')->label('Reference')->helperText('For example 112:1-4, or the collection and number, or the poet and poem.')->required()->maxLength(255),
            TextInput::make('source_note')->label('Source note (shown after the answer)')->maxLength(255),
            Textarea::make('arabic')->label('The Arabic passage')->required()->rows(4)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            Textarea::make('english')->label('English meaning (shown after the answer)')->rows(3),
            Textarea::make('note')->label('A note shown after the answer')->rows(2),
            Toggle::make('is_active')->label('Used in the test')->default(true),
            Toggle::make('is_checked')->label('Checked against a printed edition by a person'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('id')->label('Code')->formatStateUsing(fn ($state) => '['.$state.']')->sortable(),
            TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => QuizPassage::KINDS[$state] ?? $state)->sortable(),
            TextColumn::make('reference')->searchable()->limit(30),
            TextColumn::make('arabic')->label('Passage')->limit(60)->searchable(),
            TextColumn::make('words')->label('Words')->sortable(),
            ...static::flagColumns(),
            TextColumn::make('times_shown')->label('Shown')->sortable()->toggleable(),
            TextColumn::make('times_picked')->label('Picked')->sortable()->toggleable(),
        ])->filters([
            SelectFilter::make('kind')->options(QuizPassage::KINDS),
        ]);

        return static::standardActions($table);
    }

    public static function getPages(): array
    {
        return ['index' => ManageQuizPassages::route('/')];
    }
}
