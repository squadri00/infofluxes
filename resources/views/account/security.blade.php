@extends('layouts.site')

@section('title', __('account.security_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    @include('account._nav', ['active' => 'security', 'title' => __('account.security_title')])

    <section class="account-card">
        <h2>{{ __('account.password_title') }}</h2>
        @unless ($user->hasOwnPassword())
            <p>{{ __('account.password_none') }}</p>
        @endunless
        <form class="auth-form" method="post" action="{{ route('account.password') }}">
            @csrf
            @if ($user->hasOwnPassword())
                <label class="field"><span>{{ __('account.password_current') }}</span><input type="password" name="current_password" required autocomplete="current-password"></label>
                @error('current_password')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            @endif
            <label class="field"><span>{{ __('account.password_new') }}</span><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
            @error('password')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            <label class="field"><span>{{ __('account.password_again') }}</span><input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
            <div><button class="btn btn--small" type="submit">{{ $user->hasOwnPassword() ? __('account.password_change') : __('account.password_set') }}</button></div>
        </form>
    </section>

    <section class="account-card">
        <h2>{{ __('account.email_title') }}</h2>
        <p>{{ str_replace(':email', $user->email, __('account.email_now')) }}</p>
        <p><small>{{ __('account.email_note') }}</small></p>
        <form class="auth-form" method="post" action="{{ route('account.email') }}">
            @csrf
            <label class="field"><span>{{ __('account.email_new') }}</span><input type="email" name="new_email" value="{{ old('new_email') }}" required autocomplete="email"></label>
            @error('new_email')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            @if ($user->hasOwnPassword())
                <label class="field"><span>{{ __('account.password_current') }}</span><input type="password" name="email_current_password" required autocomplete="current-password"></label>
                @error('email_current_password')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            @endif
            <div><button class="btn btn--small" type="submit">{{ __('account.email_change') }}</button></div>
        </form>
    </section>

    <section class="account-card">
        <h2>{{ __('account.signins_title') }}</h2>
        @error('unlink')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        @if ($socials->isEmpty())
            <p>{{ __('account.signins_none') }}</p>
        @else
            <ul class="account-list">
                @foreach ($socials as $s)
                    <li>
                        <strong>{{ ucfirst($s->provider) }}</strong> @if ($s->email)<span class="account-list__status">{{ $s->email }}</span>@endif
                        <form method="post" action="{{ route('account.unlink', $s->provider) }}" style="display:inline">@csrf <button type="submit" class="btn btn--small">{{ __('account.signins_unlink') }}</button></form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
