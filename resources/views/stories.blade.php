@extends('layouts.site')

@section('title', __('site.stories_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('description', __('site.stories_intro'))
@section('canonical', route('stories'))
@if (! empty($storyLang))
    @section('shell_ltr', '1')
@endif

@section('content')
<div class="wrap wrap--wide">
    <nav class="path" aria-label="Path"><ol><li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li><li aria-current="page">{{ __('site.stories_title') }}</li></ol></nav>

    <h1 class="topic__title">{{ __('site.stories_title') }}</h1>
    <p class="topic__hook">{{ __('site.stories_intro') }}</p>

    @if (count($languageChips) > 1)
        <nav class="story-langs" aria-label="{{ __('site.stories_in') }}">
            <span class="story-langs__label">{{ __('site.stories_in') }}:</span>
            @foreach ($languageChips as $chip)
                <a class="story-langs__link" href="{{ $chip['url'] }}" lang="{{ $chip['code'] }}"@if ($chip['current']) aria-current="true"@endif>{{ $chip['native'] }}</a>
            @endforeach
        </nav>
    @endif

    @if ($stories->isEmpty())
        <p class="empty">{{ __('site.stories_none') }}</p>
    @else
        <div @if (! empty($storyLang)) lang="{{ $storyLang }}" dir="{{ $storyRtl ? 'rtl' : 'ltr' }}"@endif>
            @include('partials.story-grid', ['stories' => $stories])
        </div>
        @include('partials.pager', ['paginator' => $stories])
    @endif
</div>
@endsection
