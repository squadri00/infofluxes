<?php

namespace App\Filament\Resources\Challenge;

use App\Filament\Resources\Challenge\Pages\ManageQuranChapters;
use App\Filament\Resources\ChallengeResource;
use App\Models\QuranChapter;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** "Put the chapters in order": the 114 chapters. They are fixed, so they can be corrected but not added or removed. */
class QuranChapterResource extends ChallengeResource
{
    protected static ?string $model = QuranChapter::class;

    protected static ?string $navigationLabel = 'The 114 chapters';

    protected static ?string $modelLabel = 'chapter';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('number')->label('Number')->disabled()->dehydrated(false),
            TextInput::make('arabic_name')->label('Arabic name')->required()->maxLength(60)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            TextInput::make('name')->label('Name in English letters')->required()->maxLength(80),
            TextInput::make('meaning')->label('Meaning in English')->required()->maxLength(120),
            TextInput::make('honorific')->label('Salutation after the meaning (for a chapter named for a prophet)')->helperText('For example (AS). Leave empty for none.')->maxLength(20),
            TextInput::make('verses')->label('Number of verses')->numeric()->required()->minValue(1)->maxValue(300),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table->defaultSort('number')->columns([
            TextColumn::make('number')->label('No.')->sortable(),
            TextColumn::make('arabic_name')->label('Arabic'),
            TextColumn::make('name')->searchable(),
            TextColumn::make('shown_meaning')->label('Meaning')->state(fn (QuranChapter $r) => $r->shown_meaning)->searchable(['meaning']),
            TextColumn::make('verses')->sortable(),
        ]);

        return static::standardActions($table, clone: false, delete: false);
    }

    public static function getPages(): array
    {
        return ['index' => ManageQuranChapters::route('/')];
    }
}
