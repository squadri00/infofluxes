@extends('layouts.site')

@section('title', __('account.forgot_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    <h1 class="topic__title">{{ __('account.forgot_title') }}</h1>
    <p class="topic__hook">{{ __('account.forgot_intro') }}</p>
    <form class="auth-form" method="post" action="{{ route('password.email') }}">
        @csrf
        <label class="field"><span>{{ __('site.email') }}</span><input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"></label>
        @error('email')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <button class="btn btn--small" type="submit">{{ __('account.forgot_button') }}</button>
    </form>
    <p class="auth-form__other"><a href="{{ route('login') }}">{{ __('site.login') }}</a></p>
</div>
@endsection
