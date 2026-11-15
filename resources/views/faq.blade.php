@extends('layouts.site')

@section('title', __('site.faq_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('description', __('site.faq_intro'))
@section('canonical', route('faq'))

@push('jsonld')
    {!! \App\Support\Seo::faqJsonLd($items) !!}
@endpush

@section('content')
<div class="wrap">
    <nav class="path" aria-label="Path"><ol><li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li><li aria-current="page">{{ __('site.faq_title') }}</li></ol></nav>

    <h1 class="topic__title">{{ __('site.faq_title') }}</h1>
    <p class="topic__hook">{{ __('site.faq_intro') }}</p>

    @if ($count === 0)
        <p class="empty">{{ __('site.faq_none') }}</p>
    @else
        <div class="faq__tools" data-faq-tools>
            <input type="search" class="faq__search" data-faq-search placeholder="{{ __('site.faq_search') }}" aria-label="{{ __('site.faq_search') }}" autocomplete="off">
            <div class="acc-tools" data-acc-tools hidden>
                <button type="button" class="acc-toggle" data-acc-toggle data-label-open="{{ __('site.expand_all') }}" data-label-close="{{ __('site.collapse_all') }}">{{ __('site.expand_all') }}</button>
            </div>
        </div>
        <p class="empty" data-faq-nothing hidden>{{ __('site.faq_nothing') }}</p>

        <div class="faq" data-faq>
            @foreach ($groups as $name => $rows)
                <section class="faq__group" data-faq-group>
                    @if ($name !== '')<h2 class="block__title">{{ $name }}</h2>@endif
                    @foreach ($rows as $row)
                        <details class="acc faq__item" id="q-{{ $row['id'] }}" data-faq-item>
                            <summary class="acc__title">{{ $row['question'] }}</summary>
                            <div class="acc__body article__body">{!! $row['answer'] !!}</div>
                        </details>
                    @endforeach
                </section>
            @endforeach
        </div>
    @endif
</div>
@endsection

@if ($count > 0)
    @push('scripts')
        <script src="{{ asset('js/accordion.js') }}?v={{ @filemtime(public_path('js/accordion.js')) ?: 1 }}" defer></script>
        <script src="{{ asset('js/faq.js') }}?v={{ @filemtime(public_path('js/faq.js')) ?: 1 }}" defer></script>
    @endpush
@endif
