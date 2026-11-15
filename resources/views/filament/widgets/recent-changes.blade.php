<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recently changed</x-slot>
        @if ($lines === [])
            <p style="opacity:.75">Nothing has been changed yet.</p>
        @else
            <ul style="display:grid;gap:.35rem;list-style:none;padding:0;margin:0">
                @foreach ($lines as $line)
                    <li style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:baseline;border-bottom:1px solid rgba(128,128,128,.2);padding-bottom:.3rem">
                        <span style="opacity:.7;min-width:7rem">{{ $line['when'] }}</span>
                        <strong>{{ $line['who'] }}</strong>
                        <span>{{ strtolower($line['what']) }}</span>
                        <span style="opacity:.75">{{ strtolower($line['kind']) }}</span>
                        @if ($line['url'])<a href="{{ $line['url'] }}" style="text-decoration:underline">{{ \Illuminate\Support\Str::limit($line['record'], 70) }}</a>@else<span>{{ \Illuminate\Support\Str::limit($line['record'], 70) }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
        <p style="margin-top:.7rem"><a href="{{ $historyUrl }}" style="text-decoration:underline">Open the whole History</a></p>
    </x-filament::section>
</x-filament-widgets::widget>
