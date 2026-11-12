{{-- One menu entry. A top item with sub-items becomes a drop-down that works without JavaScript. --}}
<li class="menu__item">
    @if (count($item['children']))
        <details class="menu__drop">
            <summary>{{ $item['label'] }}</summary>
            <ul class="menu__sub">
                <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                @foreach ($item['children'] as $child)
                    <li><a href="{{ $child['url'] }}">{{ $child['label'] }}</a></li>
                @endforeach
            </ul>
        </details>
    @else
        <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
    @endif
</li>
