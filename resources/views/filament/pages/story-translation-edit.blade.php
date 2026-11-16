<x-filament-panels::page>
    @php
        $box = 'width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent';
        $story = $this->story();
        $dir = $this->isRtl() ? 'rtl' : 'ltr';
        $en = config('infofluxes.default_locale');
        $status = \App\Support\StoryMaker\Translations::status($story, $this->lang);
        $pair = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:.8rem;align-items:start';
        $eng = 'padding:.5rem;border:1px dashed rgba(128,128,128,.4);border-radius:.4rem;opacity:.85;white-space:pre-line';
    @endphp

    <p style="max-width:52rem">
        <a href="{{ \App\Filament\Pages\StoryTranslations::getUrl(['story' => $story->id]) }}" style="text-decoration:underline">&larr; Back to Story translations</a>
        &middot; <strong>{{ $story->code }}</strong> {{ $story->mainTitle() }} &middot; <strong>{{ $this->languageName() }}</strong>
        ({{ $status === 'on' ? 'shown on the site' : 'hidden' }}).
        The English is on the left and is never changed. Fix the {{ $this->languageName() }} on the right and press <strong>Save</strong>.
        An Arabic line (a verse or a saying) must stay exactly as in English: a changed quotation is not saved.
    </p>

    <x-filament::section>
        <x-slot name="heading">Story</x-slot>
        <div style="display:grid;gap:.8rem;max-width:70rem">
            @foreach ([['title', 'Title', 'title', false], ['short', 'Short text', 'short_detail', true], ['meta_title', 'Google title', 'meta_title', false], ['meta_description', 'Google description', 'meta_description', true]] as [$key, $label, $field, $multi])
                <div wire:key="top-{{ $key }}">
                    <strong>{{ $label }}</strong>
                    <div style="{{ $pair }}">
                        <div style="{{ $eng }}">{{ strip_tags((string) $story->getTranslation($field, $en, false)) ?: '—' }}</div>
                        @if ($multi)
                            <textarea wire:model="form.{{ $key }}" rows="3" dir="{{ $dir }}" lang="{{ $this->lang }}" style="{{ $box }}"></textarea>
                        @else
                            <input type="text" wire:model="form.{{ $key }}" dir="{{ $dir }}" lang="{{ $this->lang }}" style="{{ $box }}">
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    @php $n = 0; @endphp
    @foreach ($story->chapters as $ci => $chapter)
        <x-filament::section collapsible wire:key="chapter-{{ $ci }}">
            <x-slot name="heading">Chapter {{ $ci + 1 }}: {{ $chapter->getTranslation('title', $en, false) }}</x-slot>
            <div style="display:grid;gap:1rem;max-width:70rem">
                <div>
                    <strong>Chapter title</strong>
                    <div style="{{ $pair }}">
                        <div style="{{ $eng }}">{{ $chapter->getTranslation('title', $en, false) }}</div>
                        <input type="text" wire:model="form.chapters.{{ $ci }}.title" dir="{{ $dir }}" lang="{{ $this->lang }}" style="{{ $box }}">
                    </div>
                </div>
                @foreach ($chapter->slides as $si => $slide)
                    @php $n++; @endphp
                    <div wire:key="slide-{{ $ci }}-{{ $si }}" style="padding:.7rem;border:1px solid rgba(128,128,128,.3);border-radius:.5rem;display:grid;gap:.6rem">
                        <strong>Slide {{ $n }}</strong>
                        <div style="{{ $pair }}">
                            <div style="{{ $eng }}"><strong>{{ $slide->getTranslation('heading', $en, false) }}</strong></div>
                            <input type="text" wire:model="form.chapters.{{ $ci }}.slides.{{ $si }}.heading" dir="{{ $dir }}" lang="{{ $this->lang }}" style="{{ $box }}">
                        </div>
                        <div style="{{ $pair }}">
                            <div style="{{ $eng }}">{{ strip_tags((string) $slide->getTranslation('body', $en, false)) }}</div>
                            <textarea wire:model="form.chapters.{{ $ci }}.slides.{{ $si }}.body" rows="8" dir="{{ $dir }}" lang="{{ $this->lang }}" style="{{ $box }}"></textarea>
                        </div>
                        <div style="{{ $pair }}">
                            <div style="{{ $eng }}"><span style="opacity:.7">Picture line:</span> {{ $slide->getTranslation('image_alt', $en, false) ?: '—' }}</div>
                            <input type="text" wire:model="form.chapters.{{ $ci }}.slides.{{ $si }}.alt" dir="{{ $dir }}" lang="{{ $this->lang }}" style="{{ $box }}">
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach

    <div style="display:flex;gap:.6rem;align-items:center">
        <x-filament::button wire:click="save" wire:loading.attr="disabled">Save</x-filament::button>
        <span style="opacity:.7">Saving does not change whether the language is shown or hidden.</span>
    </div>
</x-filament-panels::page>
