@extends('layouts.site')

@section('title', __('account.following_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    @include('account._nav', ['active' => 'following', 'title' => __('account.following_title')])

    <p>{{ __('account.following_intro') }}</p>
    @if (session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif

    @if ($follows->isEmpty())
        <p class="empty">{{ __('account.following_none') }}</p>
    @else
        @foreach (['category' => 'following_categories', 'person' => 'following_people', 'keyword' => 'following_keywords'] as $kind => $heading)
            @if (($follows[$kind] ?? collect())->isNotEmpty())
                <section class="account-card" data-following="{{ $kind }}">
                    <h2>{{ __('account.'.$heading) }}</h2>
                    <ul class="account-list">
                        @foreach ($follows[$kind] as $follow)
                            @php
                                $target = $follow->target();
                                $href = $kind === 'category' && $target ? route('category.show', $target->slug) : ($kind === 'person' && $target ? $target->publicPath() : ($kind === 'keyword' ? route('search', ['q' => $follow->keyword]) : null));
                            @endphp
                            <li>
                                <span class="account-list__title">@if ($href)<a href="{{ $href }}">{{ $follow->label() }}</a>@else{{ $follow->label() }}@endif</span>
                                <form method="post" action="{{ $kind === 'keyword' ? route('follow.keyword') : route('follow', [$kind, $follow->target_id]) }}" style="display:inline">
                                    @csrf
                                    <input type="hidden" name="back" value="{{ route('account.following', [], false) }}">
                                    @if ($kind === 'keyword')<input type="hidden" name="keyword" value="{{ $follow->keyword }}"><input type="hidden" name="toggle" value="1">@endif
                                    <button type="submit" class="btn btn--small">{{ __('account.follow_stop') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach
    @endif

    <section class="account-card">
        <h2>{{ __('account.following_add_word') }}</h2>
        <form method="post" action="{{ route('follow.keyword') }}" class="form">
            @csrf
            <input type="hidden" name="back" value="{{ route('account.following', [], false) }}">
            <label class="field"><span>{{ __('account.following_word_help') }}</span><input type="text" name="keyword" maxlength="60" required></label>
            <p><button type="submit" class="btn btn--small">{{ __('account.follow') }}</button> <span class="muted">{{ __('account.following_limit', ['max' => \App\Models\Follow::MAX]) }}</span></p>
        </form>
    </section>

    @if (\App\Support\Notify::on())
    <section class="account-card">
        <h2>{{ __('account.digest_title') }}</h2>
        <form method="post" action="{{ route('account.digest') }}" class="form">
            @csrf
            <label class="field--check"><input type="checkbox" name="digest" value="1" @checked($digest)> {{ __('account.digest_label') }}</label>
            <p><button type="submit" class="btn btn--small">{{ __('account.digest_save') }}</button></p>
        </form>
    </section>
    @endif
</div>
@endsection
