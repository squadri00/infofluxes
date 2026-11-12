@extends('layouts.site')

@section('title', __('site.my_account').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    <h1 class="topic__title">{{ __('site.my_account') }}</h1>
    <p class="topic__hook">{{ auth()->user()->name }} &middot; {{ auth()->user()->email }}</p>

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
</div>
@endsection
