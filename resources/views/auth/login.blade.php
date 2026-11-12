@extends('layouts.site')

@section('title', __('site.login_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    <h1 class="topic__title">{{ __('site.login_title') }}</h1>
    <form class="auth-form" method="post" action="{{ route('login') }}">
        @csrf
        <label class="field"><span>{{ __('site.email') }}</span><input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"></label>
        <label class="field"><span>{{ __('site.password') }}</span><input type="password" name="password" required autocomplete="current-password"></label>
        @error('email')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        @error('password')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field field--check"><input type="checkbox" name="remember" value="1"> <span>{{ __('site.remember') }}</span></label>
        <button class="btn btn--small" type="submit">{{ __('site.login') }}</button>
    </form>
    @if ((bool) \App\Models\Setting::get('signups_open', true))
        <p class="auth-form__other">{{ __('site.no_account') }} <a href="{{ route('register') }}">{{ __('site.signup') }}</a></p>
    @endif
</div>
@endsection
