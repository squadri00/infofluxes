<?php

namespace App\Filament\Resources\AiPrompts;

use App\Filament\Resources\AiPrompts\Pages\ManageAiPrompts;
use App\Models\AiPrompt;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** The AI prompt library: the master rules and the specialist prompts that the AI import builds its prompt from. */
class AiPromptResource extends Resource
{
    protected static ?string $model = AiPrompt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'AI prompts';

    protected static ?string $modelLabel = 'AI prompt';

    protected static ?int $navigationSort = 5;

    private static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('import') ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::allowed();
    }

    public static function canCreate(): bool
    {
        return static::allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed() && $record->kind !== 'master';
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Name')->required()->maxLength(200),
            TextInput::make('use_for')->label('Use it for')->maxLength(255)->helperText('One line that tells a contributor when to choose this prompt.'),
            Textarea::make('body')->label('The words of the prompt')->required()->rows(24)->extraInputAttributes(['style' => 'font-family: monospace; font-size: 13px'])
                ->helperText('These words are copied into the AI chat. Keep them in simple English. "Reset to the original" brings back the first version.'),
            TagsInput::make('custom_data.match')->label('Match words')->helperText('A specialist prompt is suggested for a category when these words are found in its path (for example "bible"). Not used by the master rules.'),
            TextInput::make('sort_order')->label('Order (small numbers first)')->numeric()->default(10),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('id')->label('Code')->formatStateUsing(fn ($state) => 'PR-'.$state)->sortable(),
                TextColumn::make('title')->label('Name')->searchable()->wrap(),
                TextColumn::make('kind')->label('Kind')->badge()->formatStateUsing(fn ($state) => $state === 'master' ? 'Master rules' : 'Specialist'),
                TextColumn::make('use_for')->label('Use it for')->wrap()->limit(90),
                IconColumn::make('changed')->label('Changed by you')->boolean()->state(fn (AiPrompt $r) => $r->isChanged()),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the prompt')->modalWidth('5xl'),
                Action::make('reset')->label('Reset to the original')->color('gray')
                    ->visible(fn (AiPrompt $r) => $r->isChanged() && $r->originalBody() !== '')
                    ->requiresConfirmation()->modalDescription('Your words are replaced by the first version. This cannot be undone.')
                    ->action(function (AiPrompt $r) {
                        $r->resetToOriginal();
                        Notification::make()->success()->title('The original words are back.')->send();
                    }),
                DeleteAction::make()->label('Delete')->visible(fn (AiPrompt $r) => $r->kind !== 'master'),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAiPrompts::route('/')];
    }
}
