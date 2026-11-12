{{-- One category as a tile: picture (or a coloured block with the first letter), name, a short line, and how much is inside. --}}
@php
    $count = $category->articleCount();
    $childCount = $category->children()->where('is_visible', true)->count();
    $name = $category->name;
    $text = trim(strip_tags((string) $category->description));
@endphp
<li class="tile">
    <a class="tile__media" href="{{ route('category.show', $category->slug) }}" tabindex="-1" aria-hidden="true" style="--h: {{ crc32($category->mainName()) % 360 }}">
        @if ($category->image)
            <img src="{{ asset('storage/'.$category->image) }}" alt="" loading="lazy">
        @else
            <span class="tile__initial">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
        @endif
    </a>
    <div class="tile__body">
        <a class="tile__link" href="{{ route('category.show', $category->slug) }}">{{ $name }}</a> @include('partials.id-tag', ['code' => $category->code])
        @if ($text !== '')
            <p class="tile__text">{{ \Illuminate\Support\Str::limit($text, 90) }}</p>
        @endif
        <p class="tile__meta">
            {{ $count === 1 ? __('site.one_article') : str_replace(':n', $count, __('site.n_articles')) }}
            @if ($childCount > 0)
                &middot; {{ $childCount === 1 ? __('site.one_sub') : str_replace(':n', $childCount, __('site.n_subs')) }}
            @endif
        </p>
    </div>
</li>
