<x-filament-panels::page>
    <div x-data="{ q: '', open: null, hit(el) { return this.q.trim() === '' || el.innerText.toLowerCase().includes(this.q.trim().toLowerCase()); } }" style="display:grid;gap:1rem;max-width:56rem">
        <p>This guide explains every screen you may use, in simple words. Type a word to find a topic.</p>

        <input type="search" x-model="q" placeholder="Search the help (for example: publish, link, backup)" autocomplete="off"
               style="width:100%;padding:.6rem .8rem;border:1px solid rgba(128,128,128,.5);border-radius:.5rem;background:transparent">

        @foreach ($this->groups() as $group => $entries)
            <section x-show="[...$el.querySelectorAll('[data-topic]')].some(t => hit(t))" x-cloak>
                <h2 style="font-size:1.15rem;font-weight:700;margin:.6rem 0">{{ $group }}</h2>
                <div style="display:grid;gap:.5rem">
                    @foreach ($entries as $entry)
                        <details id="topic-{{ $entry['key'] }}" data-topic x-show="hit($el)" x-bind:open="q.trim() !== ''" style="border:1px solid rgba(128,128,128,.35);border-radius:.5rem;padding:.2rem .9rem">
                            <summary style="cursor:pointer;font-weight:600;padding:.5rem 0">{{ $entry['title'] }}</summary>
                            <div style="padding:.2rem 0 .8rem;display:grid;gap:.6rem">
                                <p>{{ $entry['what'] }}</p>
                                @if (! empty($entry['steps']))
                                    <ol style="margin:0 0 0 1.2rem;list-style:decimal;display:grid;gap:.3rem">
                                        @foreach ($entry['steps'] as $step)<li>{{ $step }}</li>@endforeach
                                    </ol>
                                @endif
                                @if (! empty($entry['tips']))
                                    <ul style="margin:0 0 0 1.2rem;list-style:disc;display:grid;gap:.3rem;opacity:.9">
                                        @foreach ($entry['tips'] as $tip)<li><em>Tip:</em> {{ $tip }}</li>@endforeach
                                    </ul>
                                @endif
                                @if ($url = $this->urlOf($entry))
                                    <p><a href="{{ $url }}" style="text-decoration:underline;font-weight:600">Open this screen &rarr;</a></p>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
