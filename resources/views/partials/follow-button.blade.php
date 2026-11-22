{{-- The Follow button. $kind: category, person or keyword. $target: the id of the category or person; $keyword: the word. Members press it to follow or stop; a visitor is sent to log in. --}}
@if (\App\Support\Following::on())
    @php
        $user = auth()->user();
        $key = $kind === 'keyword' ? ($keyword ?? '') : ($target ?? null);
        $following = $user && \App\Support\Following::is($user, $kind, $key);
        $label = $kind === 'keyword' ? ($following ? __('account.unfollow_word') : __('account.follow_word')) : ($following ? __('account.following') : __('account.follow'));
    @endphp
    @if (! $user)
        <a class="btn btn--small follow-btn" data-follow="{{ $kind }}" href="{{ route('login') }}">{{ __('account.follow_login') }}</a>
    @elseif ($user->isActive())
        <form method="post" action="{{ $kind === 'keyword' ? route('follow.keyword') : route('follow', [$kind, $target]) }}" class="follow-form" style="display:inline">
            @csrf
            <input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
            @if ($kind === 'keyword')<input type="hidden" name="keyword" value="{{ $keyword }}"><input type="hidden" name="toggle" value="1">@endif
            <button type="submit" class="btn btn--small follow-btn{{ $following ? ' is-on' : '' }}" data-follow="{{ $kind }}" aria-pressed="{{ $following ? 'true' : 'false' }}">{{ $following ? '✓ ' : '+ ' }}{{ $label }}</button>
        </form>
    @endif
@endif
