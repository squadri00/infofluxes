{{-- One Did You Know? fact as a card, laid out like an article card. $article is the fact (the mosaic passes it under that name), $shape its shape. --}}
@php
    $fact = $article;
    $shape = $shape ?? 'P';
    $hasPicture = (bool) $fact->image;
    $url = $fact->url();
    $summary = $fact->summary($shape === 'F' ? 260 : 150);
@endphp
<li class="pcard pcard--{{ $shape }} {{ $hasPicture ? 'pcard--has-picture' : 'pcard--no-picture' }}">
    @if ($hasPicture)
        <a class="pcard__media" href="{{ $url }}" tabindex="-1" aria-hidden="true">
            <img src="{{ asset('storage/'.$fact->image) }}" alt="" loading="lazy">
        </a>
    @endif
    <div class="pcard__body">
        <h3 class="pcard__title"><a href="{{ $url }}">{{ $fact->title }}</a> @include('partials.id-tag', ['code' => $fact->code])</h3>
        @if ($summary !== '')
            <p class="pcard__hook">{{ $summary }}</p>
        @endif
        <p class="pcard__meta">{{ __('site.dyk_title') }}</p>
    </div>
</li>
