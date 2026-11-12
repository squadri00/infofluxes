@extends('layouts.site')

@section('title', __('site.not_found'))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap wrap--wide">
    <h1 class="topic__title">{{ __('site.not_found') }}</h1>
    <p class="empty">{{ __('site.not_found_text') }}</p>
    <p><a class="btn btn--small" href="{{ route('home') }}">{{ __('site.go_home') }}</a></p>
</div>
@endsection
