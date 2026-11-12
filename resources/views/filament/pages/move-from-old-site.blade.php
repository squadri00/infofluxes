<x-filament-panels::page>
    <p style="max-width:52rem">
        This brings the <strong>categories</strong> and the <strong>subject indexes</strong> of the old InfoFluxes into this site.
        The headings and the index levels become categories and sub-categories. Each index topic becomes an
        <strong>empty draft article</strong> in its category: your "still to write" list. The old ID (for example AL-1.1.001) is kept as the article's <em>working name</em>.
        Press <strong>Check</strong> first. Check saves nothing.
    </p>
    <ul style="max-width:52rem;list-style:disc;margin-left:1.3rem">
        <li>A category that is already here is used as it is. It is never changed.</li>
        <li>An article is never made twice. You can press Move again later and only what is missing is added.</li>
        <li>If anything goes wrong, nothing is saved.</li>
        <li>The old site is only read, never changed.</li>
    </ul>

    <div>
        <x-filament::button wire:click="check" color="gray" wire:loading.attr="disabled">Check</x-filament::button>
        @if ($result && ! $done && ($result['total']['categories_new'] + $result['total']['articles_new']) > 0)
            <x-filament::button wire:click="move" wire:confirm="Make {{ $result['total']['categories_new'] }} categories and {{ $result['total']['articles_new'] }} draft articles now?">Move now</x-filament::button>
        @endif
    </div>

    @if ($result)
        <x-filament::section>
            <x-slot name="heading">{{ $done ? 'Done' : 'What would be made' }}</x-slot>
            <table style="width:100%;max-width:60rem;border-collapse:collapse">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.4)">
                        <th style="padding:.4rem">Index</th><th>New categories</th><th>Already here</th><th>New draft articles</th><th>Already here</th><th>With text</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['groups'] as $key => $g)
                        <tr style="border-bottom:1px solid rgba(128,128,128,.2)">
                            <td style="padding:.4rem">{{ \App\Filament\Pages\MoveFromOldSite::names()[$key] ?? $key }}</td>
                            <td>{{ $g['categories_new'] }}</td><td>{{ $g['categories_existing'] }}</td>
                            <td>{{ $g['articles_new'] ?? 0 }}</td><td>{{ $g['articles_existing'] ?? 0 }}</td><td>{{ $g['with_text'] ?? 0 }}</td>
                        </tr>
                    @endforeach
                    <tr style="font-weight:700">
                        <td style="padding:.4rem">All</td>
                        <td>{{ $result['total']['categories_new'] }}</td><td>{{ $result['total']['categories_existing'] }}</td>
                        <td>{{ $result['total']['articles_new'] }}</td><td>{{ $result['total']['articles_existing'] }}</td><td>{{ $result['total']['with_text'] }}</td>
                    </tr>
                </tbody>
            </table>
            @if ($result['total']['text_skipped'] > 0)
                <p style="margin-top:.7rem;opacity:.8">{{ $result['total']['text_skipped'] }} old entry has only a short summary and no verses (a test text). Its title is moved, its text is not.</p>
            @endif
            <p style="margin-top:.7rem;opacity:.8">"With text" are old entries that were written up (sections with verses). Their text and verses come across as a <strong>draft</strong>.</p>
            @if ($done)
                <p style="margin-top:.7rem">Look at the result in <a href="{{ \App\Filament\Resources\Categories\CategoryResource::getUrl('index') }}" style="text-decoration:underline">Categories</a> and <a href="{{ \App\Filament\Resources\Articles\ArticleResource::getUrl('index') }}" style="text-decoration:underline">Articles</a>.</p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
