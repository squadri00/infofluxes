{{-- One subject of the Index view. Needs $line (code, title, link, article, also, copy, category_id) and $trail.
     A subject that is also in this category (its main category is elsewhere) is a copy and carries a small note. --}}
<li class="qi__item" data-qi-item id="a-{{ $line['article']->id }}{{ $line['copy'] ? '-c'.$line['category_id'] : '' }}" data-text="{{ mb_strtolower($line['code'].' '.$line['article']->code.' '.$line['title'].' '.$trail.' '.($line['also'] ?? '')) }}">
    <span class="qi__code">{{ $line['code'] }}</span>
    @if ($line['link'])
        <a href="{{ route('article.show', $line['article']->slug) }}">{{ $line['title'] }}</a>
        <span class="qi__read">{{ __('site.index_read') }}</span>
    @else
        {{ $line['title'] }}
        <span class="qi__soon">{{ __('site.index_soon') }}</span>
    @endif
    @if ($line['also'])<span class="qi__also">{{ str_replace(':name', $line['also'], __('site.index_also_in')) }}</span>@endif
</li>
