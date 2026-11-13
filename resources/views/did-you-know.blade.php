@extends('layouts.site')

@section('title', __('site.dyk_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('description', __('site.dyk_intro'))
@section('canonical', route('did-you-know'))

@section('content')
<div class="wrap wrap--wide">
    <nav class="path" aria-label="Path"><ol><li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li><li aria-current="page">{{ __('site.dyk_title') }}</li></ol></nav>

    <h1 class="topic__title">{{ __('site.dyk_title') }}</h1>
    <p class="topic__hook">{{ __('site.dyk_intro') }}</p>

    @include('partials.did-you-know-list', ['entries' => $entries])
</div>
@endsection
