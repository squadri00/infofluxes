@extends('layouts.site')

@php $w = fn (string $key) => \App\Support\Newsletter::words($key); @endphp

@section('title', $w('newsletter_unsubscribe_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap form-page newsletter-page">
    <h1 class="topic__title">{{ $w('newsletter_unsubscribe_title') }}</h1>

    @if ($done)
        <div class="notice notice--ok" role="status">{{ $w('newsletter_unsubscribed') }}</div>
        <p><a class="btn" href="{{ route('home') }}">{{ $w('newsletter_home') }}</a></p>
    @else
        <p>{{ $w('newsletter_unsubscribe_ask') }}</p>
        <form method="post" action="{{ route('newsletter.unsubscribe.do', $token) }}">
            @csrf
            <button type="submit" class="btn btn--primary">{{ $w('newsletter_unsubscribe_button') }}</button>
        </form>
    @endif
</div>
@endsection
