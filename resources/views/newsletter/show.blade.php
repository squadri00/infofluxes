@extends('layouts.site')

@php $w = fn (string $key) => \App\Support\Newsletter::words($key); @endphp

@section('title', $w('newsletter_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap form-page newsletter-page">
    <h1 class="topic__title">{{ $w('newsletter_title') }}</h1>

    @if (session('newsletter_message'))
        <div class="notice {{ session('newsletter_good') ? 'notice--ok' : 'notice--error' }}" role="status">{{ session('newsletter_message') }}</div>
    @endif

    @if ($step === 'done')
        <p>{{ $w('newsletter_done_text') }}</p>
        <p><a class="btn" href="{{ route('home') }}">{{ $w('newsletter_home') }}</a></p>
    @elseif ($step === 'code' && $email)
        @include('newsletter._code-form', ['email' => $email])
    @else
        <p>{{ $w('newsletter_intro') }}</p>
        @include('newsletter._email-form')
    @endif
</div>
@endsection
