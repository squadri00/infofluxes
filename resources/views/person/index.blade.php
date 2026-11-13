@extends('layouts.site')

@section('title', __('site.people_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('description', __('site.people_intro'))
@section('canonical', route('people.index'))

@section('content')
<div class="wrap wrap--wide person-index">
    <nav class="path" aria-label="Path"><ol><li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li><li aria-current="page">{{ __('site.people_title') }}</li></ol></nav>

    <h1 class="topic__title">{{ __('site.people_title') }}</h1>
    <p class="topic__hook">{{ __('site.people_intro') }}</p>

    @if ($people->isEmpty())
        <p class="empty">{{ __('site.people_none') }}</p>
    @else
        <ul class="tiles person-tiles">
            @foreach ($people as $p)
                <li class="tile">
                    <a class="tile__media" href="{{ route('people.show', $p->slug) }}" tabindex="-1" aria-hidden="true" style="--h: {{ crc32($p->name) % 360 }}">
                        @if ($p->photo)
                            <img src="{{ asset('storage/'.$p->photo) }}" alt="" loading="lazy">
                        @else
                            <span class="tile__initial">{{ mb_strtoupper(mb_substr($p->name, 0, 1)) }}</span>
                        @endif
                    </a>
                    <div class="tile__body">
                        <a class="tile__link" href="{{ route('people.show', $p->slug) }}">{{ $p->name }}</a>
                        @if ($p->role_title)<p class="tile__text">{{ $p->role_title }}</p>@endif
                        @if ($p->tradition)<p class="tile__meta">{{ $p->tradition }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
