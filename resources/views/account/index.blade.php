@extends('layouts.site')

@section('title', __('site.my_account').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    @include('account._nav', ['active' => 'account'])
    @if (auth()->user()->bio)<p>{{ auth()->user()->bio }}</p>@endif

    @unless (auth()->user()->hasVerifiedEmail())
        <div class="notice" role="status">
            {{ str_replace(':email', auth()->user()->email, __('site.verify_banner')) }}
            <form method="post" action="{{ route('verification.send') }}" style="display:inline">@csrf <button type="submit" class="btn btn--small">{{ __('site.verify_resend') }}</button></form>
            @error('email')<span class="field__error">{{ $message }}</span>@enderror
        </div>
    @endunless

    <p>
        <a class="btn btn--small" href="{{ route('submit') }}">{{ __('site.submit_article') }}</a>
        @if ($staff)<a class="btn btn--small" href="{{ url('/admin') }}">{{ __('site.admin_area') }}</a>@endif
    </p>

    <section class="block">
        <h2 class="block__title">{{ __('site.my_articles') }}</h2>
        @if ($articles->isEmpty())
            <p class="empty">{{ __('site.no_submissions') }}</p>
        @else
            <ul class="account-list">
                @foreach ($articles as $article)
                    <li>
                        <strong>{{ $article->title }}</strong> @include('partials.id-tag', ['code' => $article->code])
                        <span class="account-list__status">{{ __('site.status_'.$article->status) }}</span>
                        @if ($article->status === 'published')
                            <a href="{{ route('article.show', $article->slug) }}">{{ __('site.view_page') }}</a>
                        @elseif (in_array($article->status, ['draft', 'in_review'], true))
                            <a href="{{ route('submit.edit', $article->id) }}">{{ __('site.edit') }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    <section class="block">
        <h2 class="block__title">{{ __('engage.my_saved') }}</h2>
        @if ($saved->isEmpty())
            <p class="empty">{{ __('engage.no_saved') }}</p>
        @else
            <ul class="account-list">
                @foreach ($saved as $row)
                    @php
                        $thing = $row->saveable;
                        $kind = $thing instanceof \App\Models\Story ? 'story' : 'article';
                        $thingUrl = $kind === 'story' ? $thing->url() : route('article.show', $thing->slug);
                    @endphp
                    <li>
                        <a href="{{ $thingUrl }}"><strong>{{ $thing->mainTitle() }}</strong></a> @include('partials.id-tag', ['code' => $thing->code])
                        <span class="account-list__status">{{ __('engage.kind_'.$kind) }}</span>
                        <form method="post" action="{{ route('save', [$kind, $thing->id]) }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="from" value="account">
                            <button type="submit" class="btn btn--small">{{ __('engage.remove') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
