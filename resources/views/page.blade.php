@extends('layouts.site')

@php
    $title = $page->title;
    $body = \App\Support\Html::clean((string) $page->body);
    $site = \App\Models\Setting::get('site_name', config('app.name'));
    $summary = $page->meta_description ?: \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 160);
@endphp

@section('title', $title.' | '.$site)
@if (filled($summary))
    @section('description', $summary)
@endif
@section('canonical', route('article.show', $page->slug))
@if ($page->no_index || $preview)
    @section('robots')
        <meta name="robots" content="noindex">
    @endsection
@endif

@section('content')
@if ($preview)
    <div class="preview-banner"><div class="wrap">{{ __('site.draft_notice') }}</div></div>
@endif

<div class="wrap page">
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            <li aria-current="page">{{ $title }}</li>
        </ol>
    </nav>

    <h1 class="topic__title">{{ $title }}</h1>
    <div class="page__body article__body">{!! $body !!}</div>
</div>
@endsection
