{{-- One menu entry. A top item with sub-items becomes a wide panel that works without JavaScript:
     the sub-categories (icon, name, short description) on the left and a feature card on the right. --}}
@php $compact = ($item['style'] ?? 'wide') === 'compact'; @endphp
<li class="menu__item{{ count($item['children']) ? ' menu__item--mega' : '' }}{{ $compact && count($item['children']) ? ' menu__item--compact' : '' }}">
    @if (count($item['children']))
        <details class="menu__drop menu__drop--mega" data-url="{{ $item['url'] }}">
            <summary>{{ $item['label'] }}</summary>
            <div class="menu__sub mega{{ $compact ? ' mega--compact' : '' }}">
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
                @if (! empty($item['feature']) || ! empty($item['challenge']))
                <div class="mega__cards">
                @if (! empty($item['feature']))
                    @php $f = $item['feature']; $menuClips = \App\Support\MenuClips::next($compact ? 1 : 3); @endphp
                    <aside class="mega__card{{ $compact ? ' mega__card--bold' : '' }}{{ $menuClips ? ' mega__card--clips' : '' }}">
                        @if ($f['image'] && ! $menuClips)<a class="mega__card-img" href="{{ $f['url'] }}" tabindex="-1" aria-hidden="true" data-mega-extra><img src="{{ $f['image'] }}" alt="{{ $f['alt'] }}" loading="lazy"></a>@endif
                        <div class="mega__card-body">
                            <h3 class="mega__card-title">{{ $f['title'] }}</h3>
                            @if ($f['text'] !== '')<p class="mega__card-text">{{ $f['text'] }}</p>@endif
                            <a class="mega__card-button" href="{{ $f['url'] }}" data-mega-extra>{{ $f['button'] }} <span aria-hidden="true">&rarr;</span></a>
                        </div>
                        @if ($menuClips)
                            <div class="mega__clips{{ $compact ? ' mega__clips--big' : '' }}" data-mega-clips>
                                <p class="mega__clips-title">{{ __('site.clips_title') }}</p>
                                @foreach ($menuClips as $mc)
                                    <a class="mega__clip" href="{{ $mc['url'] }}" data-mega-extra>
                                        <span class="mega__clip-media"><video muted playsinline loop preload="none" tabindex="-1" aria-hidden="true" data-src="{{ $mc['src'] }}"></video></span>
                                        <span class="mega__clip-text"><strong>{{ $mc['title'] }}</strong>@if ($mc['blurb'] !== '')<small>{{ $mc['blurb'] }}</small>@endif</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </aside>
                @endif
                @if (! empty($item['challenge']))
                    @php $c = $item['challenge']; @endphp
                    <aside class="mega__card mega__card--challenge">
                        @if ($c['image'])
                            <a class="mega__card-img" href="{{ $c['url'] }}" tabindex="-1" aria-hidden="true" data-mega-extra><img src="{{ $c['image'] }}" alt="" loading="lazy"></a>
                        @else
                            <span class="mega__card-icon" aria-hidden="true">{!! \App\Support\MenuIcons::svg('question', 26) !!}</span>
                        @endif
                        <div class="mega__card-body">
                            <h3 class="mega__card-title">{{ $c['title'] }}</h3>
                            <p class="mega__card-text">{{ $c['text'] }}</p>
                            <a class="mega__card-button" href="{{ $c['url'] }}" data-mega-extra>{{ $c['button'] }} <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </aside>
                @endif
                </div>
                @endif
            </div>
        </details>
    @else
        <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
    @endif
</li>
