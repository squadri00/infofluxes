{{-- Step 2: the 6-digit code that was sent to the address. --}}
@php $w = fn (string $key) => \App\Support\Newsletter::words($key); @endphp
<form class="form newsletter-form" method="post" action="{{ route('newsletter.confirm') }}" data-newsletter-code>
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">
    <p class="field">
        <label for="nl-code">{{ $w('newsletter_code_label') }}</label>
        <input type="text" id="nl-code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="9" required autocomplete="one-time-code" class="newsletter-code-input">
    </p>
    <p><button type="submit" class="btn btn--primary">{{ $w('newsletter_confirm') }}</button></p>
</form>
<form method="post" action="{{ route('newsletter.subscribe') }}" class="newsletter-resend" data-newsletter-resend>
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">
    <button type="submit" class="linklike">{{ $w('newsletter_resend') }}</button>
</form>
