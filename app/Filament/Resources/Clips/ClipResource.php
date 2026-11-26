<?php

namespace App\Filament\Resources\Clips;

use App\Filament\Resources\Clips\Pages\ManageClips;
use App\Models\Article;
use App\Models\Clip;
use App\Models\Story;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Clips: the videos of the Clips page. Drag the rows to change the order they play in. */
class ClipResource extends Resource
{
    protected static ?string $model = Clip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Clips';

    protected static ?string $modelLabel = 'clip';

    protected static ?string $slug = 'clips';

    protected static ?int $navigationSort = 9;

    private static function allowed(): bool
    {
        return auth()->user()?->canPublish() ?? false;
    }

    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }
    public static function canEdit(Model $record): bool { return static::allowed(); }
    public static function canDelete(Model $record): bool { return static::allowed(); }
    public static function canDeleteAny(): bool { return static::allowed(); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Name of the video')->required()->maxLength(150),
            FileUpload::make('file')->label('Video file')->required()
                ->disk('public')->directory(Clip::FOLDER)->visibility('public')->acceptedFileTypes(['video/mp4', 'video/webm'])->maxSize(102400)
                ->helperText('MP4 (H.264) or WebM, up to 100 MB (the live server may allow less). A short clip, 1280 x 720 or smaller, plays best. A file you put in the clips folder by hand is found with "Find new videos".'),
            Textarea::make('blurb')->label('Words shown on the video (optional)')->rows(2)->maxLength(300)
                ->helperText('One or two short lines. They show over the bottom of the video.'),
            Select::make('link_type')->label('The button on the video opens')->options(['article' => 'An article', 'story' => 'A story'])->placeholder('Nothing (no button)')->live()->native(false),
            Select::make('article_id')->label('The article')->visible(fn (Get $get) => $get('link_type') === 'article')->required(fn (Get $get) => $get('link_type') === 'article')
                ->searchable()->native(false)
                ->getSearchResultsUsing(fn (string $search) => Article::published()->where('title', 'like', '%'.$search.'%')->limit(30)->get()->mapWithKeys(fn (Article $a) => [$a->id => $a->mainTitle()])->all())
                ->getOptionLabelUsing(fn ($value) => Article::find($value)?->mainTitle() ?? 'Article '.$value),
            Select::make('story_id')->label('The story')->visible(fn (Get $get) => $get('link_type') === 'story')->required(fn (Get $get) => $get('link_type') === 'story')
                ->searchable()->native(false)
                ->getSearchResultsUsing(fn (string $search) => Story::published()->where('title', 'like', '%'.$search.'%')->limit(30)->get()->mapWithKeys(fn (Story $s) => [$s->id => $s->mainTitle()])->all())
                ->getOptionLabelUsing(fn ($value) => Story::find($value)?->mainTitle() ?? 'Story '.$value),
            TextInput::make('button_label')->label('Words on the button (optional)')->maxLength(60)->visible(fn (Get $get) => filled($get('link_type')))
                ->helperText('Empty: "Read the article" or "Open the story".'),
            Toggle::make('is_active')->label('Show it on the Clips page')->default(true),
        ])->columns(1);
    }

    /** Only the chosen kind of link is kept. */
    public static function clean(array $data): array
    {
        if (($data['link_type'] ?? null) !== 'article') {
            $data['article_id'] = null;
        }
        if (($data['link_type'] ?? null) !== 'story') {
            $data['story_id'] = null;
        }
        if (empty($data['link_type'])) {
            $data['link_type'] = null;
            $data['button_label'] = null;
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')->label('Name')->searchable()->limit(60)->wrap(),
                TextColumn::make('file')->label('File')->state(fn (Clip $r) => basename($r->file).($r->exists() ? '' : ' (missing)'))->color(fn (Clip $r) => $r->exists() ? null : 'danger')->toggleable(),
                TextColumn::make('opens')->label('Opens')->state(function (Clip $r) {
                    return match ($r->link_type) {
                        'article' => 'Article: '.(Article::find($r->article_id)?->mainTitle() ?? '—'),
                        'story' => 'Story: '.(Story::find($r->story_id)?->mainTitle() ?? '—'),
                        default => '—',
                    };
                })->limit(50)->wrap(),
                ToggleColumn::make('is_active')->label('Shown'),
            ])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the clip')->modalWidth(Width::FourExtraLarge)->mutateDataUsing(fn (array $data) => static::clean($data)),
                DeleteAction::make()->label('Delete')->modalDescription('The video file is deleted too.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageClips::route('/')];
    }
}
