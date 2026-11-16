<x-filament-panels::page>
    @php
        $box = 'width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent';
        $mini = 'cursor:pointer;border:1px solid rgba(128,128,128,.6);border-radius:.3rem;padding:.1rem .6rem';
        $story = $this->story();
        $languages = $this->languages();
    @endphp

    <p style="max-width:52rem">
        This is step 2 of the <a href="{{ \App\Filament\Pages\StoryMaker::getUrl() }}" style="text-decoration:underline">Story maker</a>.
        Choose a story that is already saved in English. 1: copy the prompt into an AI chat. 2: paste the answer here and press <strong>Check</strong>.
        3: press <strong>Save the languages</strong>. Each language is saved into the story and stays <strong>hidden</strong> until you switch it on.
        English is never changed. Every run is kept under <a href="#saved-translation-runs" style="text-decoration:underline">Saved translation runs</a>.
    </p>

    {{-- Choose the story --}}
    <x-filament::section>
        <x-slot name="heading">The story</x-slot>
        <div style="max-width:60rem;display:grid;gap:.6rem">
            @if ($story)
                <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;padding:.5rem .7rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem">
                    <span style="flex:1;min-width:14rem"><strong>{{ $story->code }}</strong> &middot; {{ $story->mainTitle() }}
                        <span style="opacity:.7">({{ $story->chapters->count() }} chapters, {{ $story->chapters->sum(fn ($c) => $c->slides->count()) }} slides, {{ \App\Models\Story::STATUSES[$story->status] ?? $story->status }})</span></span>
                    <button type="button" wire:click="changeStory" style="{{ $mini }}">Change</button>
                </div>
            @else
                <input type="text" wire:model.live.debounce.300ms="storyQuery" autocomplete="off" placeholder="Type a story code (S-12) or words of its title" style="{{ $box }}">
                <div style="display:grid;gap:.3rem">
                    @forelse ($this->storyResults() as $s)
                        <button type="button" wire:click="chooseStory({{ $s->id }})" wire:key="story-{{ $s->id }}" style="display:block;width:100%;text-align:left;padding:.45rem .7rem;cursor:pointer;border:1px solid rgba(128,128,128,.35);border-radius:.4rem;background:transparent;color:inherit">
                            <strong>{{ $s->code }}</strong> &middot; {{ $s->mainTitle() }} <span style="opacity:.65">({{ \App\Models\Story::STATUSES[$s->status] ?? $s->status }})</span>
                        </button>
                    @empty
                        <div style="opacity:.7">No story with slides matches. Save a story in the Story maker first.</div>
                    @endforelse
                </div>
            @endif
        </div>
    </x-filament::section>

    @if ($story)
        {{-- What the story has now --}}
        <x-filament::section>
            <x-slot name="heading">Languages of this story</x-slot>
            @php $have = \App\Support\StoryMaker\Translations::all($story); @endphp
            <div style="max-width:60rem;display:grid;gap:.4rem">
                <div style="padding:.45rem .6rem;border:1px solid rgba(128,128,128,.3);border-radius:.4rem"><strong>English</strong> <span style="opacity:.7">&middot; the story itself</span></div>
                @foreach ($languages as $code => $l)
                    @php $row = $have[$code] ?? null; @endphp
                    <div wire:key="have-{{ $code }}" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;padding:.45rem .6rem;border:1px solid rgba(128,128,128,.3);border-radius:.4rem">
                        <span style="flex:1;min-width:10rem"><strong>{{ $l['name'] }}</strong> <span style="opacity:.7" dir="auto">{{ $l['native'] }}</span></span>
                        @if ($row)
                            <span style="opacity:.8">{{ $row['status'] === 'on' ? 'Shown on the site' : 'Saved, hidden' }}@if ($row['saved_at']) &middot; {{ \Illuminate\Support\Carbon::parse($row['saved_at'])->diffForHumans() }}@endif</span>
                            <a href="{{ \App\Filament\Pages\StoryTranslationEdit::getUrl(['story' => $story->id, 'lang' => $code]) }}" style="{{ $mini }};text-decoration:none;color:inherit">Edit the text</a>
                            @if ($row['status'] === 'on')
                                <button type="button" wire:click="setStatus('{{ $code }}', 'hidden')" style="{{ $mini }}">Hide</button>
                            @else
                                <button type="button" wire:click="setStatus('{{ $code }}', 'on')" style="{{ $mini }}">Show on the site</button>
                            @endif
                        @else
                            <span style="opacity:.6">not translated yet</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Step 1: the prompt --}}
        <x-filament::section>
            <x-slot name="heading">Step 1. Make the translation prompt</x-slot>
            <div style="display:grid;gap:.9rem;max-width:60rem">
                <div>
                    <strong>Languages to ask for</strong> <span style="opacity:.7">(you can ask for one language again later)</span>
                    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:.3rem">
                        @foreach ($languages as $code => $l)
                            <label wire:key="code-{{ $code }}" style="display:flex;gap:.4rem;align-items:center"><input type="checkbox" value="{{ $code }}" wire:model.live="codes"> {{ $l['name'] }}</label>
                        @endforeach
                    </div>
                </div>
                <div x-data="{ copied: false }">
                    <textarea x-ref="prompt" readonly rows="10" style="width:100%;padding:.6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent;font-family:monospace;font-size:.85rem">{{ $this->prompt() }}</textarea>
                    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-top:.5rem">
                        <button type="button" class="fi-btn" style="padding:.45rem .9rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem;cursor:pointer"
                                x-on:click="navigator.clipboard.writeText($refs.prompt.value).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                            <span x-show="!copied">Copy the prompt</span><span x-show="copied" x-cloak>Copied</span>
                        </button>
                        <x-filament::button color="gray" wire:click="saveRun">Save this run</x-filament::button>
                        <span style="opacity:.7">The English story, the rules and the word lists are already inside the prompt. It is made from the story as it is now.</span>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Step 2: paste --}}
        <x-filament::section>
            <x-slot name="heading">Step 2. Paste the answer</x-slot>
            <div style="max-width:60rem">
                <textarea wire:model="paste" rows="12" style="width:100%;padding:.6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent;font-family:monospace;font-size:.85rem" placeholder="Paste the whole answer of the AI here"></textarea>
                <div style="margin-top:.6rem;display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
                    <x-filament::button wire:click="check" wire:loading.attr="disabled">Check</x-filament::button>
                    <x-filament::button color="gray" wire:click="saveRun">Save my paste for later</x-filament::button>
                    <span style="opacity:.7">Check saves nothing in the story, but it keeps this run.</span>
                </div>
            </div>
        </x-filament::section>

        {{-- Step 3: what was found, language by language --}}
        @if ($checked && $result !== [])
            @php $langs = $result['languages'] ?? []; $okCount = collect($langs)->filter(fn ($l) => ($l['errors'] ?? []) === [])->count(); @endphp
            <x-filament::section>
                <x-slot name="heading">Step 3. What was found</x-slot>
                <div style="display:grid;gap:.8rem;max-width:60rem">
                    @foreach (($result['notes'] ?? []) as $note)<div style="color:#b07800">Note: {{ $note }}</div>@endforeach
                    @forelse ($langs as $code => $lang)
                        @php $errors = $lang['errors']; $warnings = $lang['warnings']; $d = $lang['data']; @endphp
                        <div wire:key="res-{{ $code }}" style="padding:.7rem .9rem;border:1px solid {{ $errors ? '#d92d20' : ($warnings ? '#dc9b00' : '#2d9d5a') }};border-radius:.5rem">
                            <strong>{{ $errors ? '✗' : '✓' }} {{ $languages[$code]['name'] ?? $code }}</strong>
                            <span dir="auto" style="margin-inline-start:.4rem">{{ $d['title'] }}</span>
                            @foreach ($errors as $error)<div style="color:#d92d20">Problem: {{ $error }}</div>@endforeach
                            @foreach ($warnings as $warning)<div style="color:#b07800">Note: {{ $warning }}</div>@endforeach
                            @if (! $errors && ! $warnings)<div style="color:#2d9d5a">Nothing to fix.</div>@endif
                            @if ($d['short'] !== '')<p dir="auto" style="margin:.5rem 0 0;opacity:.85">{{ $d['short'] }}</p>@endif
                            @if (! empty($d['chapters'][0]['slides'][0]))
                                <details style="margin-top:.4rem"><summary style="cursor:pointer">First slide</summary>
                                    <div dir="auto" style="margin-top:.3rem"><strong>{{ $d['chapters'][0]['slides'][0]['heading'] }}</strong><div style="white-space:pre-line">{{ $d['chapters'][0]['slides'][0]['body'] }}</div></div>
                                </details>
                            @endif
                        </div>
                    @empty
                        <div style="color:#d92d20">No language was found in the answer. Each language must start with a line like "LANG: ur".</div>
                    @endforelse

                    @if ($done)
                        <div style="padding:.7rem .9rem;border:1px solid #2d9d5a;border-radius:.5rem">
                            <strong>Saved: {{ implode(', ', array_map(fn ($c) => $languages[$c]['name'] ?? $c, $done['saved'])) }}.</strong>
                            They are <strong>hidden</strong> until you switch them on (see "Languages of this story" above).
                            @foreach ($done['skipped'] as $code => $why)<div style="color:#b07800">Not saved: {{ $languages[$code]['name'] ?? $code }}, because of the problems shown.</div>@endforeach
                        </div>
                    @else
                        <div>
                            <x-filament::button wire:click="saveLanguages" wire:loading.attr="disabled" :disabled="$okCount === 0">Save the languages</x-filament::button>
                            <span style="margin-left:.6rem;opacity:.7">
                                @if ($okCount === 0) Fix the problems first, or ask the AI again.
                                @else {{ $okCount }} language(s) can be saved. A language with a problem is left out; the others are saved. @endif
                            </span>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @endif
    @endif

    {{-- Saved runs --}}
    <div id="saved-translation-runs">
        <x-filament::section collapsible :collapsed="$story !== null">
            <x-slot name="heading">Saved translation runs ({{ $this->runs()->count() }})</x-slot>
            <div style="max-width:60rem;display:grid;gap:.4rem">
                @forelse ($this->runs() as $r)
                    <div wire:key="run-{{ $r->id }}" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;padding:.45rem .6rem;border:1px solid rgba(128,128,128,.35);border-radius:.4rem">
                        <span style="flex:1;min-width:12rem"><strong>{{ \Illuminate\Support\Str::limit($r->topic, 70) }}</strong>
                            <span style="opacity:.7"> &middot; {{ implode(', ', (array) ($r->options['languages'] ?? [])) }} &middot; {{ \App\Models\StoryRun::STATUSES[$r->status] ?? $r->status }} &middot; {{ $r->updated_at->diffForHumans() }}@if ($r->story) &middot; {{ $r->story->code }}@endif</span></span>
                        <button type="button" wire:click="openRun({{ $r->id }})" style="{{ $mini }}">Open</button>
                        <button type="button" wire:click="deleteRun({{ $r->id }})" wire:confirm="Delete this saved run? The translations already saved in the story are not deleted." style="{{ $mini }}">Delete</button>
                    </div>
                @empty
                    <p style="opacity:.7">Nothing yet. A run is saved when you press "Save this run", "Check" or "Save the languages".</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>

    {{-- The rules (super user) --}}
    @if (auth()->user()->isSuperuser())
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Translation rules and word lists (super user)</x-slot>
            <div style="max-width:60rem;display:grid;gap:.8rem">
                <p style="margin:0;opacity:.8">The rules go at the top of every translation prompt. For each language you can change the Quran translation that is asked for and the word list for the salutations and the main words. These are first drafts: check them, especially the name of each Quran translation on Quran.com. "Reset to the original" brings back the first words.</p>
                <label><strong>The rules for translating</strong>
                    <textarea wire:model="ruleText" rows="16" style="{{ $box }};font-family:monospace;font-size:.85rem"></textarea>
                </label>
                @foreach ($languages as $code => $l)
                    <div wire:key="rule-{{ $code }}" style="padding:.6rem .8rem;border:1px solid rgba(128,128,128,.35);border-radius:.4rem;display:grid;gap:.5rem">
                        <strong>{{ $l['name'] }} <span dir="auto" style="opacity:.7">{{ $l['native'] }}</span></strong>
                        <label>Quran translation to use <span style="opacity:.7">(empty: no translation line, the Arabic verse stands alone)</span>
                            <input type="text" wire:model="langEdits.{{ $code }}.quran" maxlength="120" style="{{ $box }}">
                        </label>
                        <label>Word list
                            <textarea wire:model="langEdits.{{ $code }}.words" rows="5" dir="auto" style="{{ $box }};font-size:.9rem"></textarea>
                        </label>
                    </div>
                @endforeach
                <div style="display:flex;gap:.6rem;align-items:center">
                    <x-filament::button wire:click="saveRules">Save the rules</x-filament::button>
                    <x-filament::button color="gray" wire:click="resetRules" wire:confirm="Put the original rules and word lists back?">Reset to the original</x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
