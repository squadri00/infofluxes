{{-- Step 1: the address. The hidden "website_url" box is for robots; people never see it. --}}
@php $w = fn (string $key) => \App\Support\Newsletter::words($key); @endphp
<form class="form newsletter-form" method="post" action="{{ route('newsletter.subscribe') }}" data-newsletter-email>
    @csrf
    <p class="field">
        <label for="nl-email">{{ $w('newsletter_email_label') }}</label>
        <input type="email" id="nl-email" name="email" value="{{ old('email') }}" maxlength="190" required autocomplete="email" inputmode="email">
    </p>
    <p class="newsletter-trap" aria-hidden="true"><label>Website <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></p>
    <p class="newsletter-fine">{{ $w('newsletter_consent') }}</p>
    <p><button type="submit" class="btn btn--primary">{{ $w('newsletter_send_code') }}</button></p>
</form>
