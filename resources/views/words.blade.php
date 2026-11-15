@extends('layouts.site')

@section('title', __('site.words_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('description', __('site.words_intro'))
@section('canonical', route('words'))

@section('content')
<div class="wrap wrap--wide">
    <nav class="path" aria-label="Path"><ol><li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li><li aria-current="page">{{ __('site.words_title') }}</li></ol></nav>

    <h1 class="topic__title">{{ __('site.words_title') }}</h1>
    <p class="topic__hook">{{ __('site.words_intro') }}</p>

    @if ($count === 0)
        <p class="empty">{{ __('site.words_none') }}</p>
    @else
        <nav class="words__letters" aria-label="{{ __('site.words_title') }}">
            @foreach ($groups->keys() as $letter)<a href="#letter-{{ $loop->index }}">{{ $letter }}</a>@endforeach
        </nav>
        @foreach ($groups as $letter => $notes)
            <section class="words__group" id="letter-{{ $loop->index }}">
                <h2 class="block__title">{{ $letter }}</h2>
                <dl class="words__list">
                    @foreach ($notes as $note)
                        <div class="words__item" id="word-{{ $note->id }}">
                            <dt>{{ $note->term }} <span class="words__kind">{{ __('site.word_kind_'.$note->kind) }}</span></dt>
                            <dd>{{ $note->body }}@if (filled($note->source_line)) <span class="words__source">{{ __('site.source') }}: {{ $note->source_line }}</span>@endif</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    @endif
</div>
@endsection
