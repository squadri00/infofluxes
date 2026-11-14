@extends('layouts.site')

@section('title', __('site.stories_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('description', __('site.stories_intro'))
@section('canonical', route('stories'))

@section('content')
<div class="wrap wrap--wide">
    <nav class="path" aria-label="Path"><ol><li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li><li aria-current="page">{{ __('site.stories_title') }}</li></ol></nav>

    <h1 class="topic__title">{{ __('site.stories_title') }}</h1>
    <p class="topic__hook">{{ __('site.stories_intro') }}</p>

    @if ($stories->isEmpty())
        <p class="empty">{{ __('site.stories_none') }}</p>
    @else
        @include('partials.story-grid', ['stories' => $stories])
        @include('partials.pager', ['paginator' => $stories])
    @endif
</div>
@endsection
