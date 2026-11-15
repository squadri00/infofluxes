{{-- The notes of the words that are tappable in the text above. $used is a list of Explainer. They are always shown here (with no script too); the script also shows each one in a small card at its word. --}}
@if (! empty($used))
    <section class="exp-cards" id="exp-cards" aria-label="{{ __('site.words_on_page') }}">
        <h2 class="block__title">{{ __('site.words_on_page') }}</h2>
        <dl class="exp-cards__list">
            @foreach ($used as $note)
                <div class="exp-card" id="exp-card-{{ $note->id }}" data-exp-card="{{ $note->id }}">
                    <dt class="exp-card__term">{{ $note->term }} <span class="words__kind">{{ __('site.word_kind_'.$note->kind) }}</span></dt>
                    <dd class="exp-card__body">{{ $note->body }}@if (filled($note->source_line)) <span class="words__source">{{ __('site.source') }}: {{ $note->source_line }}</span>@endif</dd>
                </div>
            @endforeach
        </dl>
        <p class="exp-cards__more"><a href="{{ route('words') }}">{{ __('site.words_all') }} &rarr;</a></p>
    </section>
@endif
