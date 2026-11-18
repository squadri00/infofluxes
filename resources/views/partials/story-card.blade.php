{{-- One Visual Story as a card: a wide cover picture with the number of slides, the title and a short line.
     Used in lists of stories. $story is the story (some pages pass it as $article). --}}
@php
    $story = $story ?? $article;
    $slideCount = (int) ($story->slides_count ?? $story->slides()->count());
    $storyHref = $story->urlFor($storyLang ?? null);
    $summary = trim(preg_replace('/\s+/', ' ', strip_tags((string) $story->short_detail)));
@endphp
<li class="scard" data-key="{{ $story->id }}">
    <a class="scard__media" href="{{ $storyHref }}" tabindex="-1" aria-hidden="true">
        @if ($story->main_image)
            <img src="{{ asset('storage/'.$story->main_image) }}" alt="" loading="lazy">
        @else
            <span class="scard__blank" aria-hidden="true">{{ mb_strtoupper(mb_substr($story->title, 0, 1)) }}</span>
        @endif
        @if ($story->code)<span class="media-pill" title="{{ __('site.id_title') }}">{{ $story->code }}</span>@endif
        <span class="scard__badge">{{ $slideCount === 1 ? __('site.story_one_slide') : str_replace(':n', $slideCount, __('site.story_slides_count')) }}</span>
    </a>
    <div class="scard__body">
        <h3 class="scard__title"><a href="{{ $storyHref }}">{{ $story->title }}</a></h3>
        @if ($summary !== '')<p class="scard__hook">{{ \Illuminate\Support\Str::limit($summary, 140) }}</p>@endif
        @include('partials.card-stats', ['item' => $story])
        <p class="scard__meta">
            @if ($story->relationLoaded('category') && $story->category)<a href="{{ route('category.show', $story->category->slug) }}">{{ $story->category->name }}</a> &middot; @endif
            <a class="scard__open" href="{{ $storyHref }}">{{ __('site.story_open') }} &rarr;</a>
        </p>
    </div>
</li>
