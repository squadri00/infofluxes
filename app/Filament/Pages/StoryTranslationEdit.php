<?php

namespace App\Filament\Pages;

use App\Models\Story;
use App\Support\StoryMaker\Rules;
use App\Support\StoryMaker\TranslationReader;
use App\Support\StoryMaker\Translations;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Edit one language of one story by hand: the title, the short text, the Google lines, the chapter titles and every slide.
 * English is shown beside each box and is never changed. An Arabic line (a verse or a saying) must stay exactly as in English.
 * It sits under Story translations (no menu entry of its own).
 */
class StoryTranslationEdit extends Page
{
    protected static ?string $title = 'Edit a translation';

    protected static ?string $slug = 'story-translation-edit';

    protected string $view = 'filament.pages.story-translation-edit';

    public ?int $storyId = null;

    public string $lang = '';

    /** @var array<string, mixed> */
    public array $form = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canUseScreen('storymaker') ?? false;
    }

    public function mount(): void
    {
        $story = Story::query()->with('chapters.slides')->find((int) request()->query('story'));
        $lang = (string) request()->query('lang');
        abort_unless($story && isset(Rules::languages()[$lang]) && Translations::has($story, $lang), 404);
        $this->storyId = $story->id;
        $this->lang = $lang;
        $this->load();
    }

    public function story(): Story
    {
        return Story::query()->with('chapters.slides')->findOrFail($this->storyId);
    }

    public function languageName(): string
    {
        return Rules::languages()[$this->lang]['name'] ?? $this->lang;
    }

    public function isRtl(): bool
    {
        return (bool) (Rules::languages()[$this->lang]['rtl'] ?? false);
    }

    private function load(): void
    {
        $story = $this->story();
        $l = $this->lang;
        $get = fn ($model, string $key) => (string) ($model->getTranslation($key, $l, false) ?? '');
        $this->form = [
            'title' => $get($story, 'title'),
            'short' => $get($story, 'short_detail'),
            'meta_title' => $get($story, 'meta_title'),
            'meta_description' => $get($story, 'meta_description'),
            'chapters' => [],
        ];
        foreach ($story->chapters as $c) {
            $row = ['title' => $get($c, 'title'), 'slides' => []];
            foreach ($c->slides as $s) {
                $row['slides'][] = ['heading' => $get($s, 'heading'), 'body' => $get($s, 'body'), 'alt' => $get($s, 'image_alt')];
            }
            $this->form['chapters'][] = $row;
        }
    }

    public function save(): void
    {
        $story = $this->story();
        $en = config('infofluxes.default_locale');
        $f = $this->form;
        $errors = [];
        $warnings = [];

        if (trim((string) ($f['title'] ?? '')) === '') {
            $errors[] = 'The title cannot be empty.';
        }
        $n = 0;
        foreach ($story->chapters as $ci => $chapter) {
            foreach ($chapter->slides as $si => $slide) {
                $n++;
                $row = $f['chapters'][$ci]['slides'][$si] ?? ['heading' => '', 'body' => ''];
                if (trim((string) $row['heading']) === '' || trim((string) $row['body']) === '') {
                    $errors[] = 'Slide '.$n.': the heading and the text cannot be empty.';

                    continue;
                }
                TranslationReader::checkBody($n, (string) $slide->getTranslation('body', $en), (string) $row['body'], Rules::languages()[$this->lang], $errors, $warnings);
            }
        }
        if ($errors !== []) {
            Notification::make()->danger()->title('Not saved')->body(implode("\n", array_slice($errors, 0, 6)))->persistent()->send();

            return;
        }

        $code = $this->lang;
        $keep = Translations::all($story)[$code] ?? ['run' => null, 'keywords' => []];
        DB::transaction(function () use ($story, $f, $code, $keep) {
            $story->setTranslation('title', $code, trim((string) $f['title']));
            $story->setTranslation('short_detail', $code, trim((string) $f['short']));
            $story->setTranslation('meta_title', $code, trim((string) $f['meta_title']));
            $story->setTranslation('meta_description', $code, trim((string) $f['meta_description']));
            $story->save();
            foreach ($story->chapters as $ci => $chapter) {
                $chapter->setTranslation('title', $code, trim((string) ($f['chapters'][$ci]['title'] ?? '')))->save();
                foreach ($chapter->slides as $si => $slide) {
                    $row = $f['chapters'][$ci]['slides'][$si];
                    $slide->setTranslation('heading', $code, trim((string) $row['heading']))
                        ->setTranslation('body', $code, trim((string) $row['body']))
                        ->setTranslation('image_alt', $code, trim((string) $row['alt']))->save();
                }
            }
            Translations::saved($story->fresh(), $code, $keep['run'], $keep['keywords']);
        });

        $this->load();
        Notification::make()->success()->title($this->languageName().' is saved. Its status (shown or hidden) did not change.')
            ->body($warnings !== [] ? implode("\n", array_slice($warnings, 0, 4)) : null)->send();
    }
}
