{{-- Who made it, with a small picture, and when. $person (Person or null), $date (published date or null), $class (an extra word). --}}
@php
    $person = $person ?? null;
    $date = $date ?? null;
@endphp
@if ($person || $date)
    <span class="byline {{ $class ?? '' }}">
        @if ($person)
            @if ($person->photo)
                <img class="byline__pic" src="{{ asset('storage/'.$person->photo) }}" alt="" loading="lazy">
            @else
                <span class="byline__pic byline__pic--letter" style="--h: {{ crc32($person->name) % 360 }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}</span>
            @endif
            <span class="byline__who">
                @if ($personUrl = $person->pageUrl())<a class="byline__name" href="{{ $personUrl }}">{{ $person->name }}</a>@else<span class="byline__name">{{ $person->name }}</span>@endif @if ($person->role_title)<span class="byline__role">{{ $person->role_title }}</span>@endif
            </span>
        @endif
        @if ($date)
            <span class="byline__dates"><time datetime="{{ $date->toDateString() }}">{{ __('site.published') }} {{ $date->format('j M Y') }}</time></span>
        @endif
    </span>
@endif
