{{-- One subject of the Index view. Needs $line (code, title, link, article) and $trail. --}}
<li class="qi__item" data-qi-item id="a-{{ $line['article']->id }}" data-text="{{ mb_strtolower($line['code'].' '.$line['article']->code.' '.$line['title'].' '.$trail) }}">
    <span class="qi__code">{{ $line['code'] }}</span>
    @if ($line['link'])
        <a href="{{ route('article.show', $line['article']->slug) }}">{{ $line['title'] }}</a>
        <span class="qi__read">{{ __('site.index_read') }}</span>
    @else
        {{ $line['title'] }}
        <span class="qi__soon">{{ __('site.index_soon') }}</span>
    @endif
</li>