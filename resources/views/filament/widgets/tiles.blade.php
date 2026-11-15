{{-- A box of number tiles. Each tile is a link. $heading, $tiles (label, count, url, tone, hint), $empty (shown when there is no tile). --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ $heading }}</x-slot>
        @if (count($tiles) === 0)
            <p style="color:#2d9d5a;font-weight:600">{{ $empty }}</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(13.5rem,1fr));gap:.7rem">
                @foreach ($tiles as $tile)
                    @php
                        $tone = $tile['tone'] ?? 'plain';
                        $color = ['bad' => '#d92d20', 'warn' => '#d97706', 'good' => '#2d9d5a', 'plain' => 'rgba(128,128,128,.6)'][$tone] ?? 'rgba(128,128,128,.6)';
                        $muted = ($tile['count'] ?? 0) === 0 && $tone !== 'plain';
                    @endphp
                    <a href="{{ $tile['url'] }}" style="display:block;padding:.7rem .9rem;border:1px solid rgba(128,128,128,.3);border-left:5px solid {{ $muted ? 'rgba(128,128,128,.35)' : $color }};border-radius:.5rem;text-decoration:none;color:inherit;{{ $muted ? 'opacity:.6' : '' }}">
                        <div style="font-size:1.8rem;font-weight:800;line-height:1.1;{{ $muted ? '' : ($tone === 'plain' ? '' : 'color:'.$color) }}">{{ $tile['display'] ?? number_format($tile['count']) }}</div>
                        <div style="font-weight:600">{{ $tile['label'] }}</div>
                        @if (! empty($tile['hint']))<div style="opacity:.75;font-size:.85rem;margin-top:.15rem">{{ $tile['hint'] }}</div>@endif
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
