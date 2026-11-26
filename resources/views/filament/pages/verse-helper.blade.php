<x-filament-panels::page>
    @php
        $box = 'width:100%;padding:.55rem .7rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent;color:inherit';
        $r = $this->result();
        $choices = $this->translationChoices();
    @endphp

    <p style="max-width:52rem">
        Type the verses you want, one reference on each line, for example <strong>30:30</strong>, <strong>Surah Ar-Rum 30:30</strong> or <strong>52:35-36</strong>.
        You get the <strong>verified Arabic</strong>, the English of the translation you choose, and a ready <code>[QURAN]</code> block to copy into an article
        or into the text you give to the AI import. Nothing here is typed from memory: the words come from the Quran text stored on this site.
    </p>

    @if (! $r['ready'])
        <x-filament::section>
            <p><strong>The Quran text is not on this site yet.</strong> An administrator imports it once with <code>php artisan quran:import</code>.</p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">The verses</x-slot>
            <div style="display:grid;gap:.8rem;max-width:44rem">
                <label>
                    <strong>References</strong> <span style="opacity:.7">(one on each line, or several separated by commas)</span><br>
                    <textarea wire:model.live.debounce.500ms="refs" rows="4" placeholder="30:30&#10;52:35-36" style="{{ $box }}"></textarea>
                </label>
                @if (count($choices) > 0)
                    <label>
                        <strong>Translation</strong><br>
                        <select wire:model.live="translation" style="{{ $box }};max-width:24rem">
                            @foreach ($choices as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <p style="opacity:.8">No translation is imported yet, so only the Arabic and the reference are given.</p>
                @endif
            </div>
        </x-filament::section>

        @if (count($r['items']) > 0)
            <x-filament::section>
                <x-slot name="heading">What the verses say</x-slot>
                <div style="display:grid;gap:1rem;max-width:52rem">
                    @foreach ($r['items'] as $item)
                        <div wire:key="v-{{ $loop->index }}" style="border:1px solid rgba(128,128,128,.4);border-radius:.5rem;padding:.8rem 1rem">
                            <strong>{{ $item['ref'] }}</strong>
                            @if (! empty($item['problem']))
                                <p style="color:#c0392b;margin:.4rem 0 0">{{ $item['problem'] }}</p>
                            @endif
                            @if (! empty($item['arabic']))
                                <p dir="rtl" lang="ar" style="font-size:1.6rem;line-height:2.2;margin:.6rem 0">{{ $item['arabic'] }}</p>
                            @endif
                            @if (! empty($item['english']))
                                <p style="margin:.2rem 0 0">{{ $item['english'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            @if ($r['blocks'] !== '')
                <x-filament::section>
                    <x-slot name="heading">The block to copy</x-slot>
                    <div x-data="{ done: false }" style="display:grid;gap:.5rem;max-width:52rem">
                        <textarea x-ref="blocks" readonly rows="{{ min(18, 4 + 5 * substr_count($r['blocks'], '[QURAN]')) }}" style="{{ $box }};font-family:monospace;font-size:.85rem">{{ $r['blocks'] }}</textarea>
                        <div>
                            <button type="button" x-on:click="navigator.clipboard.writeText($refs.blocks.value); done = true; setTimeout(() => done = false, 1800)" style="padding:.4rem 1rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem;font-weight:600;cursor:pointer">
                                <span x-show="! done">Copy the block</span><span x-show="done" x-cloak>Copied</span>
                            </button>
                        </div>
                        <p style="opacity:.75;font-size:.88rem;margin:0">Paste it into the text of your article, on its own lines, just after the sentence that explains the verse.</p>
                    </div>
                </x-filament::section>
            @endif
        @endif

        @if ($this->arabicNotice() !== '')
            <details style="max-width:52rem;opacity:.85">
                <summary style="cursor:pointer">Source and license of the Arabic text</summary>
                <pre style="white-space:pre-wrap;font-size:.8rem;margin:.6rem 0 0">{{ $this->arabicNotice() }}</pre>
                <p style="font-size:.85rem">The Arabic must be shown with its source: <a href="https://tanzil.net" target="_blank" rel="noopener" style="text-decoration:underline">Tanzil.net</a>. The reference lines made here already say so.</p>
            </details>
        @endif
    @endif
</x-filament-panels::page>
