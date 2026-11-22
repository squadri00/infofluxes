{{-- The account drop-down of a logged-in person, like a portal: their picture (or first letter) and name; the panel has the account pages and Log out.
     $staff: may this person open the admin? (The bell stays outside the menu so the unread number is always in view.) --}}
@php
    $me = auth()->user();
    $unreadMenu = \App\Support\Notify::unread($me);
@endphp
<li class="menu__item account-menu__item">
    <details class="menu__drop account-menu" data-account-menu>
        <summary aria-label="{{ __('account.menu') }}: {{ $me->name }}">
            @if ($me->photoUrl())
                <img class="account-menu__pic" src="{{ $me->photoUrl() }}" alt="">
            @else
                <span class="account-menu__pic account-menu__pic--blank" aria-hidden="true">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
            @endif
            <span class="account-menu__name">{{ $me->name }}</span>
            @if ($unreadMenu > 0)<span class="account-menu__dot" aria-hidden="true"></span>@endif
        </summary>
        <div class="menu__sub account-menu__panel">
            <div class="account-menu__who"><strong>{{ $me->name }}</strong><small>{{ $me->email }}</small></div>
            <ul class="account-menu__list">
                <li><a href="{{ route('account') }}">{{ __('site.my_account') }}</a></li>
                <li><a href="{{ route('account.profile') }}">{{ __('account.nav_profile') }}</a></li>
                <li><a href="{{ route('account.security') }}">{{ __('account.nav_security') }}</a></li>
                <li><a href="{{ route('account.scores') }}">{{ __('account.nav_scores') }}</a></li>
                @if (\App\Support\Following::on())
                    <li><a href="{{ route('account.following') }}">{{ __('account.nav_following') }}</a></li>
                @endif
                @if (\App\Support\Notify::on())
                    <li><a href="{{ route('account.notifications') }}">{{ __('account.nav_notifications') }}@if ($unreadMenu > 0) <span class="bell__count">{{ $unreadMenu > 99 ? '99+' : $unreadMenu }}</span>@endif</a></li>
                @endif
                <li><a href="{{ route('submit') }}">{{ __('site.submit_article') }}</a></li>
                @if ($staff)
                    <li class="account-menu__sep" role="separator"></li>
                    <li><a href="{{ url('/admin') }}">{{ __('account.menu_admin') }}</a></li>
                @endif
                <li class="account-menu__sep" role="separator"></li>
                <li>
                    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="account-menu__logout">{{ __('site.logout') }}</button></form>
                </li>
            </ul>
        </div>
    </details>
</li>
