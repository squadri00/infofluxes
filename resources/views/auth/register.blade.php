@extends('layouts.site')

@section('title', __('site.signup_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    <h1 class="topic__title">{{ __('site.signup_title') }}</h1>
    <p class="topic__hook">{{ __('site.signup_intro') }}</p>
    <form class="auth-form" method="post" action="{{ route('register') }}">
        @csrf
        {{-- A hidden box for robots. A person never sees it or fills it. --}}
        <div class="field--trap" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <label class="field"><span>{{ __('site.name') }}</span><input type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></label>
        @error('name')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field"><span>{{ __('site.email') }}</span><input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
        @error('email')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field"><span>{{ __('site.password') }}</span><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        @error('password')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field"><span>{{ __('site.password_again') }}</span><input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
        <button class="btn btn--small" type="submit">{{ __('site.signup') }}</button>
    </form>
    <p class="auth-form__other">{{ __('site.have_account') }} <a href="{{ route('login') }}">{{ __('site.login') }}</a></p>
</div>
@endsection
