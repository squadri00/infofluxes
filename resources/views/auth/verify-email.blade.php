@extends('layouts.site')

@section('title', __('site.verify_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    <h1 class="topic__title">{{ __('site.verify_title') }}</h1>
    <p class="topic__hook">{{ str_replace(':email', $email, __('site.verify_intro')) }}</p>

    @error('email')<p class="field__error" role="alert">{{ $message }}</p>@enderror

    <form class="auth-form" method="post" action="{{ route('verification.send') }}">
        @csrf
        <button class="btn btn--small" type="submit">{{ __('site.verify_resend') }}</button>
    </form>
    <p class="auth-form__other">{{ __('site.verify_not_yours') }}
        <form method="post" action="{{ route('logout') }}" style="display:inline">@csrf <button type="submit" class="linklike">{{ __('site.logout') }}</button></form>
    </p>
</div>
@endsection
