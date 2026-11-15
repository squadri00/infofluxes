@extends('layouts.site')

@section('title', __('account.privacy_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    @include('account._nav', ['active' => 'privacy', 'title' => __('account.privacy_title')])

    <section class="account-card">
        <h2>{{ __('account.download_title') }}</h2>
        <p>{{ __('account.download_text') }}</p>
        <p><a class="btn btn--small" href="{{ route('account.data') }}">{{ __('account.download_button') }}</a></p>
    </section>

    <section class="account-card account-card--danger">
        <h2>{{ __('account.delete_title') }}</h2>
        @if ($user->isStaff())
            <p>{{ __('account.delete_staff') }}</p>
        @else
            <p><strong>{{ __('account.delete_text') }}</strong></p>
            <p>{{ __('account.delete_what') }}</p>
            <ul>
                @foreach (['delete_1', 'delete_2', 'delete_3', 'delete_4'] as $line)
                    <li>{{ __('account.'.$line) }}</li>
                @endforeach
            </ul>
            <form class="auth-form" method="post" action="{{ route('account.delete') }}" onsubmit="return confirm('{{ __('account.delete_button') }}?')">
                @csrf
                @if ($user->hasOwnPassword())
                    <label class="field"><span>{{ __('account.delete_password') }}</span><input type="password" name="delete_password" required autocomplete="current-password"></label>
                    @error('delete_password')<p class="field__error" role="alert">{{ $message }}</p>@enderror
                @else
                    <label class="field"><span>{{ __('account.delete_email') }}</span><input type="email" name="delete_email" required autocomplete="off"></label>
                    @error('delete_email')<p class="field__error" role="alert">{{ $message }}</p>@enderror
                @endif
                <div><button class="btn btn--small" type="submit">{{ __('account.delete_button') }}</button></div>
            </form>
        @endif
    </section>
</div>
@endsection
