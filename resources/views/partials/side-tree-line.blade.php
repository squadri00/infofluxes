{{-- One topic in the side tree. A planned subject (no article yet) is shown as plain words. --}}
@php $isCurrent = (int) $line['article']->id === (int) $current->id; @endphp
<li class="side-tree__item @if ($isCurrent) is-current @endif">
    @if ($line['link'])
        <a href="{{ route('article.show', $line['article']->slug) }}" @if ($isCurrent) aria-current="page" @endif>{{ $line['title'] }}</a>
    @else
        <span class="side-tree__soon">{{ $line['title'] }}</span>
    @endif
</li>
