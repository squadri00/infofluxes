@extends('layouts.site')

@section('title', __('site.challenge').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('description', __('site.challenge_intro'))
@section('canonical', route('challenge'))

@section('content')
<div class="wrap wrap--wide">
    <nav class="path" aria-label="Path"><ol><li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li><li aria-current="page">{{ __('site.challenge') }}</li></ol></nav>
    <h1 class="topic__title">{{ __('site.challenge') }}</h1>
    <p>{{ __('site.challenge_intro') }}</p>

    @php $tests = $tests->where('on', true); @endphp
    @if ($tests->isEmpty())
        <p class="empty">{{ \App\Support\Words::get('tests_none') }}</p>
    @else
        <ul class="tests-grid">
            @foreach ($tests as $test)
                <li class="test-card">
                    <h2 class="test-card__title"><a href="{{ route('challenge.show', $test['key']) }}">{{ $test['title'] }}</a></h2>
                    @if ($test['text'] !== '')<p class="test-card__text">{{ $test['text'] }}</p>@endif
                    <span class="test-card__go" aria-hidden="true">{{ \App\Support\Words::get('tests_open') }} &rarr;</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
