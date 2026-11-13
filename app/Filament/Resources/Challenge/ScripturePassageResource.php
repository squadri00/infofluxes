<?php

namespace App\Filament\Resources\Challenge;

use App\Filament\Resources\Challenge\Pages\ManageScripturePassages;
use App\Filament\Resources\ChallengeResource;
use App\Models\ScripturePassage;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** "Which scripture?": short passages in plain English, one voice for every scripture. */
class ScripturePassageResource extends ChallengeResource
{
    protected static ?string $model = ScripturePassage::class;

    protected static ?string $navigationLabel = 'Which scripture?';

    protected static ?string $modelLabel = 'scripture passage';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->label('Scripture')->options(ScripturePassage::KINDS)->required()->native(true),
            TextInput::make('reference')->label('Reference')->required()->maxLength(255),
            Textarea::make('text')->label('The passage (plain English)')->required()->rows(4),
            TextInput::make('source_note')->label('Source note (translation used)')->maxLength(255),
            Textarea::make('note')->label('A note shown after the answer')->rows(2),
            Toggle::make('is_active')->label('Used in the test')->default(true),
            Toggle::make('is_checked')->label('Compared with a printed edition by a person'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('id')->label('Code')->formatStateUsing(fn ($state) => '['.$state.']')->sortable(),
            TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => ScripturePassage::KINDS[$state] ?? $state)->sortable(),
            TextColumn::make('reference')->searchable()->limit(30),
            TextColumn::make('text')->label('Passage')->limit(70)->searchable(),
            ...static::flagColumns(),
            TextColumn::make('times_shown')->label('Shown')->sortable()->toggleable(),
            TextColumn::make('times_correct')->label('Right')->sortable()->toggleable(),
        ])->filters([
            SelectFilter::make('kind')->options(ScripturePassage::KINDS),
        ]);

        return static::standardActions($table);
    }

    public static function getPages(): array
    {
        return ['index' => ManageScripturePassages::route('/')];
    }
}
