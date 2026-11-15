{{-- The newsletter card: a small card at the bottom left that offers the newsletter, with the email box, the button and the fine print.
     Steps: the address, then the 6-digit code that was emailed, then a thank-you. It appears a few seconds after the page opens (after the
     cookie notice is answered), can be closed, and does not come back for a while. The Subscribe link in the footer opens it too.
     Without JavaScript nothing shows here and the Subscribe link goes to the normal newsletter page. --}}
@php
    $w = fn (string $key) => \App\Support\Newsletter::words($key);
    $nlPages = \App\Models\Page::query()->published()->whereIn('slug', ['privacy', 'terms'])->get()->keyBy('slug');
    $nlPrivacy = $nlPages->has('privacy') ? '<a href="'.e(route('article.show', 'privacy')).'">'.e($nlPages['privacy']->title).'</a>' : null;
    $nlTerms = $nlPages->has('terms') ? '<a href="'.e(route('article.show', 'terms')).'">'.e($nlPages['terms']->title).'</a>' : null;
    $nlLinks = array_filter([$nlPrivacy, $nlTerms]);
@endphp
<aside class="nlc" data-nl-card hidden role="dialog" aria-modal="false" aria-labelledby="nlc-title"
       data-delay="{{ \App\Support\Newsletter::popupDelay() }}" data-days="{{ \App\Support\Newsletter::popupDays() }}">
    <div class="nlc__top">
        <span class="nlc__label">{{ $w('popup_label') }}</span>
        <button type="button" class="nlc__close" data-nl-close aria-label="{{ $w('newsletter_close') }}">&times;</button>
    </div>
    <div class="nlc__body">
        <h2 class="nlc__title" id="nlc-title">{{ $w('popup_title') }}</h2>
        <p class="nlc__text" data-nl-text>{{ $w('popup_text') }}</p>
        <p class="nlc__message" data-nl-message role="status" aria-live="polite" hidden></p>

        {{-- Step 1: the address --}}
        <form class="nlc__form" method="post" action="{{ route('newsletter.subscribe') }}" data-nl-email>
            @csrf
            <input type="email" name="email" required maxlength="190" autocomplete="email" inputmode="email" placeholder="{{ $w('popup_placeholder') }}" aria-label="{{ $w('newsletter_email_label') }}">
            <p class="newsletter-trap" aria-hidden="true"><label>Website <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></p>
            <button type="submit" class="nlc__button">{{ $w('popup_button') }}</button>
        </form>

        {{-- Step 2: the code --}}
        <form class="nlc__form" method="post" action="{{ route('newsletter.confirm') }}" data-nl-code hidden>
            @csrf
            <input type="hidden" name="email" value="">
            <input type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="9" required autocomplete="one-time-code" placeholder="{{ $w('newsletter_code_label') }}" aria-label="{{ $w('newsletter_code_label') }}">
            <button type="submit" class="nlc__button">{{ $w('newsletter_confirm') }}</button>
            <button type="button" class="nlc__resend" data-nl-resend>{{ $w('newsletter_resend') }}</button>
        </form>

        <p class="nlc__fine" data-nl-fine>
            {{ $w('popup_fine') }}
            @if ($nlLinks)
                {!! str_replace([':privacy', ':terms'], [$nlPrivacy ?? '', $nlTerms ?? ''], $w('popup_fine_links_'.count($nlLinks))) !!}
            @endif
        </p>
    </div>
</aside>
