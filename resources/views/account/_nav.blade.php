{{-- The small menu of the account area. $active is account, profile, security or privacy. --}}
@php $user = auth()->user(); @endphp
<div class="account-head">
    @if ($user->photoUrl())
        <img class="avatar" src="{{ $user->photoUrl() }}" alt="">
    @else
        <span class="avatar avatar--blank" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
    @endif
    <div>
        <h1 class="topic__title" style="margin:0">{{ $title ?? __('site.my_account') }}</h1>
        <p class="topic__hook" style="margin:2px 0 0">{{ $user->name }} &middot; {{ $user->email }}</p>
    </div>
</div>
<ul class="account-nav">
    @foreach (['account' => ['account', 'nav_overview'], 'profile' => ['account.profile', 'nav_profile'], 'security' => ['account.security', 'nav_security'], 'privacy' => ['account.privacy', 'nav_privacy']] as $key => [$route, $label])
        <li><a href="{{ route($route) }}" @if ($active === $key) aria-current="page" @endif>{{ __('account.'.$label) }}</a></li>
    @endforeach
</ul>
