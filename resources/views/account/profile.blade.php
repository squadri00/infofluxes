@extends('layouts.site')

@section('title', __('account.profile_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    @include('account._nav', ['active' => 'profile', 'title' => __('account.profile_title')])
    <p>{{ __('account.profile_intro') }}</p>

    <section class="account-card">
        <form class="auth-form" method="post" action="{{ route('account.profile') }}" enctype="multipart/form-data">
            @csrf
            <label class="field"><span>{{ __('account.name') }}</span><input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name"></label>
            @error('name')<p class="field__error" role="alert">{{ $message }}</p>@enderror

            <label class="field"><span>{{ __('account.bio') }}</span><textarea name="bio" rows="4" maxlength="500">{{ old('bio', $user->bio) }}</textarea><small>{{ __('account.bio_help') }}</small></label>
            @error('bio')<p class="field__error" role="alert">{{ $message }}</p>@enderror

            <div class="field">
                <span>{{ __('account.photo') }}</span>
                @if ($user->photoUrl())
                    <img class="avatar avatar--big" src="{{ $user->photoUrl() }}" alt="">
                    <label class="field--check"><input type="checkbox" name="remove_photo" value="1"> {{ __('account.photo_remove') }}</label>
                @endif
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif">
                <small>{{ __('account.photo_help') }}</small>
            </div>
            @error('photo')<p class="field__error" role="alert">{{ $message }}</p>@enderror

            <div><button class="btn btn--small" type="submit">{{ __('account.save') }}</button></div>
        </form>
    </section>
</div>
@endsection
