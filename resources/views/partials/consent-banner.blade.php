{{-- The cookie notice. It stays hidden until the script finds that the visitor has not chosen yet. --}}
@php $policy = trim((string) \App\Models\Setting::get('consent_policy_url')); @endphp
<div class="consent" data-consent-banner role="region" aria-label="{{ __('consent.region') }}" hidden>
    <div class="consent__text">
        <strong>{{ __('consent.title') }}</strong>
        <span>{{ __('consent.text') }}</span>
        @if ($policy !== '')<a href="{{ $policy }}">{{ __('consent.policy') }}</a>@endif
    </div>
    <div class="consent__buttons">
        <button type="button" class="btn btn--small" data-consent-accept>{{ __('consent.accept') }}</button>
        <button type="button" class="btn btn--small btn--ghost" data-consent-reject>{{ __('consent.reject') }}</button>
    </div>
</div>
