<?php

namespace App\Filament\Resources\Challenge;

use App\Filament\Resources\Challenge\Pages\ManageFactStatements;
use App\Filament\Resources\ChallengeResource;
use App\Models\FactStatement;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** "Fact or myth?": a statement, whether it is true, and why. */
class FactStatementResource extends ChallengeResource
{
    protected static ?string $model = FactStatement::class;

    protected static ?string $navigationLabel = 'Fact or myth?';

    protected static ?string $modelLabel = 'statement';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('topic')->label('Topic')->options(FactStatement::TOPICS)->required()->native(true),
            Textarea::make('statement')->label('The statement')->required()->rows(3),
            Toggle::make('is_true')->label('The statement is true')->default(true),
            Textarea::make('explanation')->label('Why (shown after the answer)')->required()->rows(3),
            TextInput::make('source')->label('Where to check it')->maxLength(255),
            Toggle::make('is_active')->label('Used in the test')->default(true),
            Toggle::make('is_checked')->label('Checked against a reliable source by a person'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('id')->label('Code')->formatStateUsing(fn ($state) => '['.$state.']')->sortable(),
            TextColumn::make('topic')->badge()->formatStateUsing(fn ($state) => FactStatement::TOPICS[$state] ?? $state)->sortable(),
            TextColumn::make('statement')->limit(80)->searchable(),
            IconColumn::make('is_true')->label('True')->boolean(),
            ...static::flagColumns(),
            TextColumn::make('times_shown')->label('Shown')->sortable()->toggleable(),
            TextColumn::make('times_correct')->label('Right')->sortable()->toggleable(),
        ])->filters([
            SelectFilter::make('topic')->options(FactStatement::TOPICS),
        ]);

        return static::standardActions($table);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFactStatements::route('/')];
    }
}
