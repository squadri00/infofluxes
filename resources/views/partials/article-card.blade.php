{{-- One article as a card. $shape: c = small card (little picture on the left), P = tall card (picture on top), F = wide feature card.
     An article without a picture becomes a text card of the same shape. --}}
@php
    $shape = $shape ?? 'P';
    $hasPicture = (bool) $article->main_image;
    $url = route('article.show', $article->slug);
    $summary = trim(preg_replace('/\s+/', ' ', strip_tags((string) $article->short_detail)));
    $firstCategory = $article->category;
    $slideCount = (int) ($article->slides_count ?? 0);
@endphp
<li class="pcard pcard--{{ $shape }} {{ $hasPicture ? 'pcard--has-picture' : 'pcard--no-picture' }}">
    @if ($hasPicture)
        <a class="pcard__media" href="{{ $url }}" tabindex="-1" aria-hidden="true">
            <img src="{{ asset('storage/'.$article->main_image) }}" alt="" loading="lazy">
        </a>
    @endif
    <div class="pcard__body">
        <h3 class="pcard__title"><a href="{{ $url }}">{{ $article->title }}</a> @include('partials.id-tag', ['code' => $article->code])</h3>
        @if ($summary !== '')
            <p class="pcard__hook">{{ \Illuminate\Support\Str::limit($summary, $shape === 'F' ? 260 : 150) }}</p>
        @endif
        @if ($article->relationLoaded('contributor') && $article->contributor)
            @include('partials.byline', ['person' => $article->contributor, 'class' => 'byline--card'])
        @endif
        @if ($firstCategory || $slideCount > 0)
            <p class="pcard__meta">
                @if ($firstCategory)<a href="{{ route('category.show', $firstCategory->slug) }}">{{ $firstCategory->name }}</a>@endif
                @if ($slideCount > 0)@if ($firstCategory) &middot; @endif{{ $slideCount === 1 ? __('site.story_one_slide') : str_replace(':n', $slideCount, __('site.story_slides_count')) }}@endif
            </p>
        @endif
    </div>
</li>
