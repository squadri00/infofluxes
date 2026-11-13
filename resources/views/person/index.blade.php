@extends('layouts.site')

@section('title', __('site.people_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('description', __('site.people_intro'))
@section('canonical', route('people.index'))

@section('content')
<div class="wrap wrap--wide">
    <nav class="path" aria-label="Path"><ol><li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li><li aria-current="page">{{ __('site.people_title') }}</li></ol></nav>
    <h1 class="topic__title">{{ __('site.people_title') }}</h1>
    <p>{{ __('site.people_intro') }}</p>

    @if ($people->isEmpty())
        <p class="empty">{{ __('site.people_none') }}</p>
    @else
        <ul class="people">
            @foreach ($people as $person)
                <li class="person-card">
                    <a class="person-card__link" href="{{ route('people.show', $person->slug) }}">
                        @if ($person->photo)
                            <img class="person-card__photo" src="{{ asset('storage/'.$person->photo) }}" alt="{{ $person->photo_alt ?: $person->name }}" loading="lazy">
                        @else
                            <span class="person-card__photo person-card__photo--none" aria-hidden="true">{{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}</span>
                        @endif
                        <span class="person-card__name">{{ $person->name }}</span>
                    </a>
                    @if ($person->role_title)<span class="person-card__role">{{ $person->role_title }}</span>@endif
                    @if ($person->bio)<p class="person-card__bio">{{ \Illuminate\Support\Str::limit($person->bio, 160) }}</p>@endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
