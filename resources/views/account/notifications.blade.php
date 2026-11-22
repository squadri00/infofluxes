@extends('layouts.site')

@section('title', __('account.notes_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    @include('account._nav', ['active' => 'notifications', 'title' => __('account.notes_title')])

    @if ($items->isEmpty())
        <p class="empty">{{ __('account.notes_none') }}</p>
    @else
        @if ($unread > 0)
            <form method="post" action="{{ route('account.notifications.read') }}" style="margin:0 0 12px">@csrf <button type="submit" class="btn btn--small">{{ __('account.notes_mark_all') }}</button></form>
        @endif
        <ul class="account-list notes" data-notes>
            @foreach ($items as $note)
                <li class="notes__item{{ $note->isNew() ? ' is-new' : '' }}">
                    <a href="{{ route('account.notification', $note->id) }}">{{ $note->title }}</a>
                    <span class="account-list__status">@if ($note->isNew()){{ __('account.notes_new') }} &middot; @endif{{ $note->created_at?->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
        @include('partials.pager', ['paginator' => $items])
    @endif
</div>
@endsection
