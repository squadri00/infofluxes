{{-- The site's own social network buttons, each in its brand colour. $class is an extra word for the list (header or footer). --}}
@php $links = \App\Support\Social::links(); @endphp
@if ($links->isNotEmpty())
    <ul class="social {{ $class ?? '' }}" aria-label="{{ __('site.social_label') }}">
        @foreach ($links as $link)
            @php $colour = \App\Support\Social::colour($link->platform); @endphp
            <li>
                <a class="social__btn social__btn--{{ $link->platform }}" href="{{ \App\Support\Social::safeUrl($link->url) }}" target="_blank" rel="noopener noreferrer me" aria-label="{{ $link->buttonTitle() }}" title="{{ $link->buttonTitle() }}" style="background: {{ $colour }}">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" style="color: {{ str_starts_with($colour, '#') ? $colour : '#d6249f' }}">{!! \App\Support\Social::drawing($link->platform) !!}</svg>
                </a>
            </li>
        @endforeach
    </ul>
@endif
