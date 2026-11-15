{{-- Like, dislike, save and share. $item is an Article or a Story. Like, dislike and save need a log in; share is for everyone. --}}
@php
    $type = $item instanceof \App\Models\Story ? 'story' : 'article';
    $itemUrl = $type === 'story' ? $item->url() : route('article.show', $item->slug);
    $itemTitle = $title ?? $item->mainTitle();
    $me = auth()->user();
    $likes = $item->likesCount();
    $mine = $me ? $item->reactions()->where('user_id', $me->id)->value('value') : null;
    $saved = $me ? $item->saves()->where('user_id', $me->id)->exists() : false;
    $shareText = rawurlencode($itemTitle);
    $shareUrl = rawurlencode($itemUrl);
@endphp
<div class="engage" id="engage" aria-label="{{ __('engage.label') }}">
    <div class="engage__row">
        <form method="post" action="{{ route('react', [$type, $item->id]) }}" class="engage__form">
            @csrf
            <input type="hidden" name="value" value="like">
            <button type="submit" class="engage__btn{{ $mine === 1 ? ' is-on' : '' }}" aria-pressed="{{ $mine === 1 ? 'true' : 'false' }}" title="{{ __('engage.like') }}">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M2 10h4v11H2V10zm6 11V10l4.3-7.2c.4-.6 1.2-.8 1.8-.5.5.3.8.8.7 1.4L14 9h6.2c1.3 0 2.2 1.2 1.9 2.4l-1.8 7.5c-.3 1.1-1.2 1.8-2.3 1.8H8z"/></svg>
                <span>{{ __('engage.like') }}</span>
                <span class="engage__count">{{ $likes }}</span>
            </button>
        </form>
        <form method="post" action="{{ route('react', [$type, $item->id]) }}" class="engage__form">
            @csrf
            <input type="hidden" name="value" value="dislike">
            <button type="submit" class="engage__btn{{ $mine === -1 ? ' is-on' : '' }}" aria-pressed="{{ $mine === -1 ? 'true' : 'false' }}" title="{{ __('engage.dislike') }}">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" style="transform:rotate(180deg)"><path fill="currentColor" d="M2 10h4v11H2V10zm6 11V10l4.3-7.2c.4-.6 1.2-.8 1.8-.5.5.3.8.8.7 1.4L14 9h6.2c1.3 0 2.2 1.2 1.9 2.4l-1.8 7.5c-.3 1.1-1.2 1.8-2.3 1.8H8z"/></svg>
                <span>{{ __('engage.dislike') }}</span>
            </button>
        </form>
        <form method="post" action="{{ route('save', [$type, $item->id]) }}" class="engage__form">
            @csrf
            <button type="submit" class="engage__btn{{ $saved ? ' is-on' : '' }}" aria-pressed="{{ $saved ? 'true' : 'false' }}" title="{{ $saved ? __('engage.unsave') : __('engage.save') }}">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="{{ $saved ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/></svg>
                <span>{{ $saved ? __('engage.saved') : __('engage.save') }}</span>
            </button>
        </form>

        <details class="engage__share" data-share data-title="{{ $itemTitle }}" data-url="{{ $itemUrl }}">
            <summary class="engage__btn" title="{{ __('engage.share') }}">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18 16.1c-.8 0-1.5.3-2 .8l-7.1-4.2c.1-.2.1-.5.1-.7s0-.5-.1-.7L16 7.2c.5.5 1.2.8 2 .8 1.7 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3c0 .2 0 .5.1.7L8 9.8C7.500 9.300 6.800 9 6 9c-1.700 0-3 1.300-3 3s1.300 3 3 3c.8 0 1.500-.3 2-.8l7.200 4.200c-.1.200-.1.400-.1.600 0 1.600 1.300 2.900 2.900 2.900s2.900-1.300 2.900-2.900-1.200-2.900-2.900-2.900z"/></svg>
                <span>{{ __('engage.share') }}</span>
            </summary>
            <div class="engage__panel">
                <button type="button" class="engage__link" data-share-copy hidden data-done="{{ __('engage.copied') }}">{{ __('engage.copy_link') }}</button>
                <a class="engage__link" href="https://wa.me/?text={{ $shareText }}%20{{ $shareUrl }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                <a class="engage__link" href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" rel="noopener noreferrer">Facebook</a>
                <a class="engage__link" href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&amp;text={{ $shareText }}" target="_blank" rel="noopener noreferrer">X</a>
                <a class="engage__link" href="mailto:?subject={{ $shareText }}&amp;body={{ $shareUrl }}">{{ __('engage.email') }}</a>
            </div>
        </details>
    </div>
    @auth
        @unless (auth()->user()->hasVerifiedEmail())
            <p class="engage__hint"><a href="{{ route('verification.notice') }}">{{ __('site.verify_needed') }}</a></p>
        @endunless
    @endauth
    @guest
        <p class="engage__hint"><a href="{{ route('login') }}">{{ __('engage.log_in') }}</a> {{ __('engage.log_in_to') }}</p>
    @endguest
</div>
