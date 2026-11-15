{{-- One menu entry. A top item with sub-items becomes a wide panel that works without JavaScript:
     the sub-categories (icon, name, short description) on the left and a feature card on the right. --}}
<li class="menu__item{{ count($item['children']) ? ' menu__item--mega' : '' }}">
    @if (count($item['children']))
        <details class="menu__drop menu__drop--mega" data-url="{{ $item['url'] }}">
            <summary>{{ $item['label'] }}</summary>
            <div class="menu__sub mega">
                <div class="mega__links">
                    <p class="mega__heading">@if (! empty($item['icon']))<span class="mega__heading-icon" aria-hidden="true">{!! \App\Support\MenuIcons::svg($item['icon'], 18) !!}</span>@endif{{ $item['label'] }}</p>
                    <ul class="mega__list{{ count($item['children']) > 4 ? ' mega__list--two' : '' }}">
                        @foreach ($item['children'] as $child)
                            <li>
                                <a href="{{ $child['url'] }}">
                                    @if ($child['icon'] !== '')<span class="mega__icon" aria-hidden="true">{!! \App\Support\MenuIcons::svg($child['icon']) !!}</span>@endif
                                    <span class="mega__text"><strong>{{ $child['label'] }}</strong>@if ($child['description'] !== '')<small>{{ $child['description'] }}</small>@endif</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <a class="mega__all" href="{{ $item['url'] }}" data-mega-extra>{{ __('site.mega_see_all') }} {{ $item['label'] }} &rarr;</a>
                </div>
                @if (! empty($item['feature']))
                    @php $f = $item['feature']; @endphp
                    <aside class="mega__card">
                        @if ($f['image'])<a class="mega__card-img" href="{{ $f['url'] }}" tabindex="-1" aria-hidden="true" data-mega-extra><img src="{{ $f['image'] }}" alt="{{ $f['alt'] }}" loading="lazy"></a>@endif
                        <div class="mega__card-body">
                            <h3 class="mega__card-title">{{ $f['title'] }}</h3>
                            @if ($f['text'] !== '')<p class="mega__card-text">{{ $f['text'] }}</p>@endif
                            <a class="mega__card-button" href="{{ $f['url'] }}" data-mega-extra>{{ $f['button'] }} <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </aside>
                @endif
            </div>
        </details>
    @else
        <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
    @endif
</li>
