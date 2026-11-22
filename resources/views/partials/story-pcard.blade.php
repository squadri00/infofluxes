{{-- One Visual Story as a card in the same layout as an article card (picture, title, short text, who, counts, category). The mosaic passes the story as $article and $shape.
     c = small card (little picture on the left), P = tall card (picture on top), F = wide feature card. --}}
@php
    $story = $article;
    $shape = $shape ?? 'c';
    $hasPicture = (bool) $story->main_image;
    $url = $story->url();
    $summary = trim(preg_replace('/\s+/', ' ', strip_tags((string) $story->short_detail)));
    $slideCount = (int) ($story->slides_count ?? 0);
@endphp
<li class="pcard pcard--{{ $shape }} {{ $hasPicture ? 'pcard--has-picture' : 'pcard--no-picture' }}">
    @if ($hasPicture)
        <a class="pcard__media" href="{{ $url }}" tabindex="-1" aria-hidden="true">
            <img src="{{ asset('storage/'.$story->main_image) }}" alt="" loading="lazy">
        </a>
    @endif
    <div class="pcard__body">
        <h3 class="pcard__title"><a href="{{ $url }}">{{ $story->mainTitle() }}</a> @include('partials.id-tag', ['code' => $story->code])</h3>
        @if ($summary !== '')
            <p class="pcard__hook">{{ \Illuminate\Support\Str::limit($summary, $shape === 'F' ? 260 : 150) }}</p>
        @endif
        @if ($story->relationLoaded('contributor') && $story->contributor)
            @include('partials.byline', ['person' => $story->contributor, 'class' => 'byline--card'])
        @endif
        @include('partials.card-stats', ['item' => $story])
        <p class="pcard__meta">
            @if ($story->relationLoaded('category') && $story->category)<a href="{{ route('category.show', $story->category->slug) }}">{{ $story->category->name }}</a> &middot; @endif
            @if ($slideCount > 0){{ $slideCount === 1 ? __('site.story_one_slide') : str_replace(':n', $slideCount, __('site.story_slides_count')) }}@else{{ __('site.stories_title') }}@endif
        </p>
    </div>
</li>
