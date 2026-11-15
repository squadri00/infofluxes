@extends('layouts.site')

@section('title', __('account.reset_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    <h1 class="topic__title">{{ __('account.reset_title') }}</h1>
    <form class="auth-form" method="post" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="field"><span>{{ __('site.email') }}</span><input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></label>
        @error('email')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field"><span>{{ __('account.password_new') }}</span><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        @error('password')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field"><span>{{ __('account.password_again') }}</span><input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
        <button class="btn btn--small" type="submit">{{ __('account.reset_button') }}</button>
    </form>
</div>
@endsection
