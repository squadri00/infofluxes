<x-filament-panels::page>
    <style>
        .ai-sel, .ai-sel option { background-color: #ffffff !important; color: #111827 !important; }
        .dark .ai-sel, .dark .ai-sel option { background-color: #18181b !important; color: #f4f4f5 !important; }
        .ai-cat-list { background: #ffffff; color: #111827; }
        .dark .ai-cat-list { background: #18181b; color: #f4f4f5; }
        .ai-cat-list button:hover { background: rgba(31, 95, 139, .18) !important; }
    </style>
    <p style="max-width:50rem">
        Three steps. 1: copy a prompt into an AI chat. 2: paste its answer here and press <strong>Check</strong>.
        3: press <strong>Save as drafts</strong>. Everything is saved as a <strong>draft</strong>. Nothing is ever published from this screen,
        and every article keeps a mark "written with AI help": a person must write their name in <strong>Reviewer</strong> before it can be published.
    </p>

    {{-- Step 1: the prompt --}}
    <x-filament::section>
        <x-slot name="heading">Step 1. Make the prompt</x-slot>
        <div style="display:grid;gap:.9rem;max-width:60rem">
            <label>
                <strong>What do you want?</strong><br>
                <select class="ai-sel" wire:model.live="kind" style="padding:.5rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent">
                    @foreach ($this->kinds() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <strong>{{ $kind === 'categories' ? 'The area to plan' : ($kind === 'fact' ? 'The fact to check' : ($kind === 'research' ? 'The title or the question' : 'The subject of the article')) }}</strong><br>
                <input type="text" wire:model.live.debounce.400ms="topic" maxlength="300" style="width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent" placeholder="Write it in a few words">
            </label>
            @if ($kind === 'research')
                @php $box = 'width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent'; @endphp
                {{-- A blank index entry to fill (optional): the article goes into that entry and keeps its index ID. --}}
                <div>
                    <strong>Index entry to fill</strong> <span style="opacity:.7">(optional: the article is saved into a blank entry of your index and keeps its index ID)</span><br>
                    @if ($this->entry())
                        @php $chosen = $this->entry(); @endphp
                        <div style="display:flex;gap:.5rem;align-items:center;margin:.3rem 0;padding:.45rem .6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem">
                            <span style="flex:1"><strong>{{ \App\Support\IndexEntries::label($chosen) }}</strong> &middot; {{ $chosen->mainTitle() }} <span style="opacity:.7">({{ $chosen->category?->mainName() }})</span></span>
                            <button type="button" wire:click="clearEntry" style="cursor:pointer;border:1px solid rgba(128,128,128,.6);border-radius:.3rem;padding:.1rem .5rem">No entry</button>
                        </div>
                    @else
                        <input type="text" wire:model.live.debounce.400ms="entryQuery" autocomplete="off" placeholder="Type an index ID (AL-2.1.001), a code (A-723) or words of the title" style="{{ $box }}">
                        @if (trim($entryQuery) !== '')
                            <div class="ai-cat-list" style="max-height:14rem;overflow-y:auto;margin-top:.2rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem">
                                @forelse ($this->entryResults() as $found)
                                    <button type="button" wire:click="selectEntry({{ $found->id }})" wire:key="entry-{{ $found->id }}" style="display:block;width:100%;text-align:left;padding:.45rem .7rem;cursor:pointer;border:0;border-bottom:1px solid rgba(128,128,128,.25);background:transparent;color:inherit;font-size:.9rem">
                                        <strong>{{ \App\Support\IndexEntries::label($found) }}</strong> &middot; {{ $found->mainTitle() }} <span style="opacity:.7">({{ $found->category?->mainName() }})</span>
                                    </button>
                                @empty
                                    <div style="padding:.6rem .7rem;opacity:.7">No blank index entry matches. (An entry that already has text is not offered.)</div>
                                @endforelse
                            </div>
                        @endif
                    @endif
                </div>
                {{-- An existing article to replace (optional): the new text goes into it; its address, category and status stay. --}}
                <div data-replace-chooser>
                    <strong>Article to replace</strong> <span style="opacity:.7">(optional: the new text goes into an article that already exists, even a published one. It keeps its ID and web address. The old text stays in the History.)</span><br>
                    @if ($this->replaceTarget())
                        @php $target = $this->replaceTarget(); @endphp
                        <div style="margin:.3rem 0;padding:.5rem .6rem;border:1px solid {{ $target->status === 'published' ? '#d92d20' : 'rgba(128,128,128,.5)' }};border-radius:.4rem">
                            <div style="display:flex;gap:.5rem;align-items:center">
                                <span style="flex:1"><strong>{{ $target->code }}</strong> &middot; {{ $target->mainTitle() }} <span style="opacity:.7">({{ $target->category?->mainName() }} &middot; {{ \App\Models\Article::STATUSES[$target->status] ?? $target->status }})</span></span>
                                <button type="button" wire:click="clearReplace" style="cursor:pointer;border:1px solid rgba(128,128,128,.6);border-radius:.3rem;padding:.1rem .5rem">Do not replace</button>
                            </div>
                            @if ($target->status === 'published')
                                <div style="margin-top:.4rem;color:#d92d20">This article is <strong>published</strong>. It stays published, so visitors see the new text as soon as you press Save.</div>
                            @endif
                        </div>
                    @else
                        <input type="text" wire:model.live.debounce.400ms="replaceQuery" autocomplete="off" placeholder="Type a code (A-723) or words of the title" style="{{ $box }}">
                        @if (trim($replaceQuery) !== '')
                            <div class="ai-cat-list" style="max-height:14rem;overflow-y:auto;margin-top:.2rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem">
                                @forelse ($this->replaceResults() as $found)
                                    <button type="button" wire:click="selectReplace({{ $found->id }})" wire:key="replace-{{ $found->id }}" style="display:block;width:100%;text-align:left;padding:.45rem .7rem;cursor:pointer;border:0;border-bottom:1px solid rgba(128,128,128,.25);background:transparent;color:inherit;font-size:.9rem">
                                        <strong>{{ $found->code }}</strong> &middot; {{ $found->mainTitle() }} <span style="opacity:.7">({{ $found->category?->mainName() }} &middot; {{ \App\Models\Article::STATUSES[$found->status] ?? $found->status }})</span>
                                    </button>
                                @empty
                                    <div style="padding:.6rem .7rem;opacity:.7">No article that you may replace matches.</div>
                                @endforelse
                            </div>
                        @endif
                    @endif
                </div>
                {{-- A search box with a short scrolling list: the list of categories is very long, so a plain drop-down would not fit the screen. --}}
                <div x-data="{
                        options: @js(collect($this->categoryChoices())->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()),
                        query: '', open: false,
                        get shown() { const q = this.query.trim().toLowerCase(); const list = q === '' ? this.options : this.options.filter(o => o.label.toLowerCase().includes(q)); return list.slice(0, 60); },
                        get total() { const q = this.query.trim().toLowerCase(); return q === '' ? this.options.length : this.options.filter(o => o.label.toLowerCase().includes(q)).length; },
                        pick(o) { this.query = ''; this.open = false; $wire.set('categoryId', o.id); },
                        clear() { this.query = ''; $wire.set('categoryId', null); },
                        current() { const id = $wire.categoryId; const o = this.options.find(x => String(x.id) === String(id)); return o ? o.label : ''; }
                    }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" style="position:relative">
                    <strong>Category</strong> <span style="opacity:.7">(where the article will be saved)</span><br>
                    <div x-show="current() !== ''" x-cloak style="display:flex;gap:.5rem;align-items:center;margin:.3rem 0;padding:.45rem .6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem">
                        <span x-text="current()" style="flex:1"></span>
                        <button type="button" x-on:click="clear()" style="cursor:pointer;border:1px solid rgba(128,128,128,.6);border-radius:.3rem;padding:.1rem .5rem">Change</button>
                    </div>
                    <input type="text" x-show="current() === ''" x-model="query" x-on:focus="open = true" x-on:input="open = true" autocomplete="off"
                           placeholder="Type a word to find the category (for example: tawhid, hadith, Bible)" style="{{ $box }}">
                    <div class="ai-cat-list" x-show="open && current() === ''" x-cloak style="position:absolute;z-index:30;left:0;right:0;max-height:18rem;overflow-y:auto;margin-top:.2rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem;box-shadow:0 8px 24px rgba(0,0,0,.35)">
                        <template x-for="o in shown" :key="o.id">
                            <button type="button" x-on:click="pick(o)" x-text="o.label" style="display:block;width:100%;text-align:left;padding:.45rem .7rem;cursor:pointer;border:0;border-bottom:1px solid rgba(128,128,128,.25);background:transparent;color:inherit;font-size:.9rem"
                                   ></button>
                        </template>
                        <div x-show="shown.length === 0" style="padding:.6rem .7rem;opacity:.7">No category matches. Try another word.</div>
                        <div x-show="total > 60" style="padding:.4rem .7rem;opacity:.7;font-size:.85rem" x-text="'Showing 60 of ' + total + '. Type more letters to narrow the list.'"></div>
                    </div>
                </div>
                <label>
                    <strong>Kind of research</strong> <span style="opacity:.7">(chosen for you from the category; change it if it does not fit)</span><br>
                    <select class="ai-sel" wire:model.live="specialist" style="{{ $box }}">
                        <option value="">None (master rules only)</option>
                        @foreach ($this->specialists() as $slug => $title)
                            <option value="{{ $slug }}">{{ $title }}</option>
                        @endforeach
                    </select>
                </label>
                <div style="display:grid;grid-template-columns:minmax(8rem,12rem) 1fr;gap:.9rem">
                    <label>
                        <strong>Words</strong><br>
                        <input type="number" wire:model.live.debounce.600ms="words" min="300" max="8000" step="100" style="{{ $box }}">
                    </label>
                    <label>
                        <strong>How detailed</strong><br>
                        <select class="ai-sel" wire:model.live="depth" style="{{ $box }}">
                            @foreach ($this->depths() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <details @if (trim($ownText) !== '') open @endif style="border:1px solid rgba(128,128,128,.4);border-radius:.4rem;padding:.5rem .7rem">
                    <summary style="cursor:pointer"><strong>I already have an article text</strong> <span style="opacity:.7">(my own writing, or from my own prompt: put it into the shape of an article)</span></summary>
                    <div style="display:grid;gap:.4rem;margin-top:.5rem">
                        <textarea wire:model.live.debounce.700ms="ownText" rows="8" placeholder="Paste your article text here. The prompt below then asks the AI to put this text into the shape of an article (headings, sources, verse blocks) without changing the facts, the verses or the wording." style="{{ $box }}"></textarea>
                        @if ($this->ownTextLeftovers())
                            <div style="border:1px solid #d97706;border-radius:.4rem;padding:.5rem .7rem;background:rgba(217,119,6,.1)">
                                <strong>Check the end of your text.</strong> These lines look like the talk of an AI chat, not like your article. If they are not yours, delete them, or they will be put into the article:
                                <ul style="margin:.3rem 0 0 1.2rem;list-style:disc">
                                    @foreach ($this->ownTextLeftovers() as $chat)<li>{{ $chat }}</li>@endforeach
                                </ul>
                            </div>
                        @endif
                        @if (trim($ownText) !== '')
                            <span style="opacity:.8">The prompt below is now the "put my text into shape" prompt ({{ number_format(mb_strlen($ownText)) }} letters). <button type="button" wire:click="$set('ownText', '')" style="cursor:pointer;border:1px solid rgba(128,128,128,.6);border-radius:.3rem;padding:.1rem .6rem">Clear my text</button></span>
                        @endif
                    </div>
                </details>
                <label>
                    <strong>Comments for the AI</strong> <span style="opacity:.7">(optional: a point to cover, a view to include, a source to use)</span><br>
                    <textarea wire:model.live.debounce.600ms="comments" rows="3" maxlength="1500" style="{{ $box }}"></textarea>
                </label>
                <label style="display:flex;gap:.5rem;align-items:flex-start">
                    <input type="checkbox" wire:model.live="explainers" style="margin-top:.3rem">
                    <span><strong>Also ask for word notes</strong> <span style="opacity:.7">(explainers: the AI picks 3 to 8 hard words, names or places and writes a short note for each. They are saved as drafts and shown to readers only after you publish them.)</span></span>
                </label>
                @if ($words > \App\Support\Prompts::PARTS_ABOVE)
                    <div style="opacity:.8">A long article is written in parts: the AI first gives an outline, then you ask it for each part.</div>
                @endif
            @endif
            <div x-data="{ copied: false }">
                <textarea x-ref="prompt" readonly rows="10" style="width:100%;padding:.6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent;font-family:monospace;font-size:.85rem">{{ $this->prompt() }}</textarea>
                <button type="button" class="fi-btn" style="margin-top:.5rem;padding:.45rem .9rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem;cursor:pointer"
                        x-on:click="navigator.clipboard.writeText($refs.prompt.value).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                    <span x-show="!copied">Copy the prompt</span><span x-show="copied" x-cloak>Copied</span>
                </button>
                <span style="margin-left:.6rem;opacity:.7">{{ $kind === 'research' ? 'The master rules, the kind of research and your choices are already inside the prompt.' : 'The list of your present categories is already inside the prompt.' }}</span>
            </div>
        </div>
    </x-filament::section>

    {{-- Step 2: paste --}}
    <x-filament::section>
        <x-slot name="heading">Step 2. Paste the answer</x-slot>
        <div style="max-width:60rem">
            <textarea wire:model="paste" rows="12" style="width:100%;padding:.6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent;font-family:monospace;font-size:.85rem" placeholder="Paste the whole answer of the AI here"></textarea>
            <div style="margin-top:.6rem">
                <x-filament::button wire:click="check" wire:loading.attr="disabled">Check</x-filament::button>
                <span style="margin-left:.6rem;opacity:.7">Check saves nothing.</span>
            </div>
        </div>
    </x-filament::section>

    {{-- Step 3: the result of the check --}}
    @if ($checked)
        <x-filament::section>
            <x-slot name="heading">Step 3. What was found ({{ count($items) }})</x-slot>
            <div style="display:grid;gap:.8rem;max-width:60rem">
                @foreach ($items as $i => $item)
                    <div wire:key="item-{{ $i }}" style="padding:.7rem .9rem;border:1px solid {{ $item['errors'] ? '#d92d20' : ($item['warnings'] ? '#dc9b00' : '#2d9d5a') }};border-radius:.5rem">
                        <strong>{{ $item['errors'] ? '✗' : '✓' }} {{ ['article' => 'Article', 'fact' => 'Fact', 'categories' => 'Categories'][$item['kind']] ?? $item['kind'] }}: {{ $item['title'] }}</strong>
                        @if (! empty($item['data']['entry_label']))
                            <div style="margin:.2rem 0;padding:.35rem .6rem;border-radius:.4rem;background:rgba(45,157,90,.15)">Will fill the index entry: <strong>{{ $item['data']['entry_label'] }}</strong></div>
                        @endif
                        @if (! empty($item['data']['replace_label']))
                            <div data-replace-banner style="margin:.2rem 0;padding:.45rem .6rem;border-radius:.4rem;background:rgba(220,155,0,.18)">
                                Will <strong>replace</strong> the article <strong>{{ $item['data']['replace_label'] }}</strong>
                                ({{ \App\Models\Article::STATUSES[$item['data']['replace_status']] ?? $item['data']['replace_status'] }}):
                                the old text has {{ $item['data']['replace_old_words'] }} words, the new text has {{ str_word_count(strip_tags((string) ($item['data']['long'] ?? ''))) }} words.
                                Its web address, category and status stay. The reviewer's name is cleared, because the text is new.
                            </div>
                        @endif
                        @foreach (($item['data']['index_suggestions'] ?? []) as $sg)
                            <div style="margin:.3rem 0;padding:.45rem .6rem;border:1px dashed #dc9b00;border-radius:.4rem">
                                A blank index entry looks like this subject: <strong>{{ $sg['code'] }}</strong> &middot; {{ $sg['title'] }}
                                <button type="button" wire:click="useEntry({{ $sg['id'] }})" style="margin-left:.5rem;cursor:pointer;border:1px solid rgba(128,128,128,.6);border-radius:.3rem;padding:.1rem .6rem">Fill this entry instead</button>
                            </div>
                        @endforeach
                        @if (! empty($item['data']['category_label']))
                            <div style="opacity:.75">In: {{ $item['data']['category_label'] }}</div>
                        @endif
                        @if ($item['kind'] === 'categories' && ! empty($item['data']['paths']))
                            <ul style="margin:.4rem 0 0 1.2rem;list-style:disc">
                                @foreach (array_slice($item['data']['paths'], 0, 40) as $line)
                                    <li>{{ implode(' > ', $line['names']) }}@if ($line['description'] !== '') <span style="opacity:.7">| {{ $line['description'] }}</span>@endif</li>
                                @endforeach
                            </ul>
                        @endif
                        @foreach ($item['errors'] as $error)
                            <div style="color:#d92d20">Problem: {{ $error }}</div>
                        @endforeach
                        @foreach ($item['warnings'] as $warning)
                            <div style="color:#b07800">Note: {{ $warning }}</div>
                        @endforeach
                        @if ($item['kind'] === 'article' && $item['title'] !== '(no title)' && filled($item['data']['long'] ?? null))
                            @php
                                $pvDoc = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><base target="_blank">'
                                    .'<link rel="stylesheet" href="'.e(asset('css/site.css')).'"></head><body style="margin:0;padding:1rem 1.2rem">'
                                    .'<article class="topic-inner" style="max-width:46rem;margin:0 auto"><h1 class="topic__title">'.e($item['data']['title'] ?? $item['title']).'</h1>'
                                    .(filled($item['data']['short'] ?? null) ? '<div class="topic__hook"><p>'.e($item['data']['short']).'</p></div>' : '')
                                    .'<div class="article__body">'.\App\Support\Html::fromMarked($item['data']['long']).'</div></article></body></html>';
                            @endphp
                            <details data-article-preview style="margin:.6rem 0">
                                <summary style="cursor:pointer;display:inline-block;padding:.4rem .9rem;border:1px solid #2d9d5a;border-radius:.4rem;font-weight:700">&#128065; Preview: see how this article will look on the website</summary>
                                <p style="margin:.4rem 0;opacity:.75;font-size:.9rem">The title, the short text and the article text in the website's own style. Nothing is saved yet. The picture, the sources box and the explainer cards are added when it is saved.</p>
                                <iframe wire:ignore sandbox="allow-popups" srcdoc="{{ $pvDoc }}" title="Article preview" style="width:100%;height:34rem;border:1px solid rgba(128,128,128,.4);border-radius:.5rem;background:#fff"></iframe>
                            </details>
                        @endif
                        @if (in_array($item['kind'], ['article', 'fact'], true) && $item['title'] !== '(no title)')
                            @php
                                $pvTitle = $item['data']['meta_title'] ?? ($item['data']['title'] ?? $item['title']);
                                $pvText = $item['data']['meta_description'] ?? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) (($item['data']['short'] ?? '') ?: ($item['data']['long'] ?? ''))))), 160);
                                $own = ! empty($item['data']['meta_title']) || ! empty($item['data']['meta_description']);
                            @endphp
                            <details style="margin-top:.5rem"><summary style="cursor:pointer">How it will look in Google @if (! $own)<span style="opacity:.7">(no META lines: the page uses its own title and short text)</span>@endif</summary>
                                <div style="margin-top:.4rem;padding:.6rem .8rem;border:1px solid rgba(128,128,128,.35);border-radius:.5rem;max-width:40rem">
                                    <div style="opacity:.7;font-size:.8rem">{{ url('/') }} &rsaquo; ...</div>
                                    <div style="color:#1a0dab;font-size:1.1rem;line-height:1.3">{{ \Illuminate\Support\Str::limit($pvTitle, 60, '…') }}</div>
                                    <div style="font-size:.9rem;opacity:.85">{{ \Illuminate\Support\Str::limit($pvText, 160, '…') }}</div>
                                </div>
                            </details>
                        @endif
                        @if (! empty($item['data']['outline']))
                            <details style="margin-top:.5rem"><summary style="cursor:pointer">Parts of the article ({{ count($item['data']['outline']) }})</summary>
                                <ul style="margin:.3rem 0 0 1.2rem;list-style:disc">
                                    @foreach ($item['data']['outline'] as $h)
                                        <li style="margin-left:{{ ($h['level'] - 2) * 1.2 }}rem">{{ $h['title'] }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                        @if (! empty($item['data']['sources']))
                            <details style="margin-top:.4rem" @if (collect($item['data']['sources'])->contains(fn ($x) => ($x['link'] ?? null) === 'broken')) open @endif><summary style="cursor:pointer">Sources ({{ count($item['data']['sources']) }})</summary>
                                <ul style="margin:.3rem 0 0 1.2rem;list-style:disc">
                                    @foreach ($item['data']['sources'] as $src)
                                        <li>
                                            @if (($src['link'] ?? null) === 'ok') <span style="color:#2d9d5a">✓ opens</span>
                                            @elseif (($src['link'] ?? null) === 'broken') <span style="color:#d92d20">✗ did not open</span>
                                            @elseif (($src['link'] ?? null) === 'blocked') <span style="color:#b07800">⚠ open it yourself (the site blocks checks)</span>
                                            @elseif (($src['link'] ?? null) === 'skipped') <span style="opacity:.7">not checked</span>
                                            @else <span style="opacity:.7">no link</span> @endif
                                            · {{ ucfirst($src['type']) }}: {{ $src['ref'] }}
                                            @if (! empty($src['url'])) <span style="opacity:.7">({{ \Illuminate\Support\Str::limit($src['url'], 70) }})</span> @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                        @if (! empty($item['data']['explainers']))
                            <details style="margin-top:.4rem"><summary style="cursor:pointer">Word notes ({{ count($item['data']['explainers']) }})</summary>
                                <ul style="margin:.3rem 0 0 1.2rem;list-style:disc">
                                    @foreach ($item['data']['explainers'] as $note)
                                        <li>
                                            <strong>{{ $note['term'] }}</strong> <span style="opacity:.7">({{ $note['kind'] }})</span>
                                            @if (! empty($note['exists'])) <span style="color:#b07800">already exists: {{ $note['exists'] }}, it is reused</span>
                                            @elseif ($note['found']) <span style="color:#2d9d5a">found in the text, a new draft note</span>
                                            @else <span style="color:#b07800">not in the text, a new draft note</span> @endif
                                            <div style="opacity:.85">{{ $note['text'] }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                        @if (! empty($item['data']['please_check']))
                            <details style="margin-top:.4rem" open><summary style="cursor:pointer"><strong>Please check ({{ count($item['data']['please_check']) }})</strong></summary>
                                <ul style="margin:.3rem 0 0 1.2rem;list-style:disc">
                                    @foreach ($item['data']['please_check'] as $point)<li>{{ $point }}</li>@endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                @endforeach

                <div>
                    @if ($this->okCount() > 0)
                        @php $liveTarget = $this->replaceTarget(); $liveTarget = $liveTarget && $liveTarget->status === 'published' ? $liveTarget : null; @endphp
                        @if ($liveTarget)
                            <label style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem;color:#d92d20;font-weight:600">
                                <input type="checkbox" wire:model.live="confirmReplace"> I understand: {{ $liveTarget->code }} is published, and visitors will see the new text at once.
                            </label>
                        @endif
                        <x-filament::button wire:click="save" wire:confirm="{{ $this->replaceTarget() ? 'Replace the text of '.$this->replaceTarget()->code.' with the new text? The old text stays in the History.' : 'Save '.$this->okCount().' item(s) as drafts? Items with a problem are skipped.' }}">{{ $this->replaceTarget() ? 'Replace the article' : 'Save as drafts' }} ({{ $this->okCount() }})</x-filament::button>
                        @if ($this->okCount() < count($items))
                            <span style="margin-left:.6rem;color:#d92d20">{{ count($items) - $this->okCount() }} item(s) with a problem will be skipped.</span>
                        @endif
                    @else
                        <strong style="color:#d92d20">Nothing can be saved yet. Fix the problems, paste again and press Check.</strong>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endif

    @if ($done)
        <x-filament::section>
            <x-slot name="heading">Saved</x-slot>
            <p>{{ count($done['articles']) - count($done['replaced'] ?? []) }} draft article(s) were made{{ ! empty($done['replaced']) ? ', and '.count($done['replaced']).' existing article(s) got the new text (the old text is kept in the History, so it can be put back)' : '' }}. Find them in <a href="{{ \App\Filament\Resources\Articles\ArticleResource::getUrl('index') }}" style="text-decoration:underline">Articles</a>, read them, write your name in <strong>Reviewer</strong>, and publish.</p>
            @php $savedArticles = \App\Models\Article::query()->whereIn('id', $done['articles'])->orderBy('id')->get(); @endphp
            @if ($savedArticles->isNotEmpty())
                <ul style="list-style:none;margin:.6rem 0;padding:0;display:grid;gap:.4rem">
                    @foreach ($savedArticles as $saved)
                        <li style="display:flex;flex-wrap:wrap;gap:.4rem .9rem;align-items:center;border:1px solid rgba(128,128,128,.35);border-radius:.4rem;padding:.4rem .7rem">
                            <strong>{{ $saved->code }}</strong>
                            <span style="flex:1 1 14rem">{{ $saved->mainTitle() }}</span>
                            <a href="{{ route('article.show', $saved->slug) }}" target="_blank" rel="noopener" style="text-decoration:underline;font-weight:600">Preview on the website &#8599;</a>
                            <a href="{{ \App\Filament\Resources\Articles\ArticleResource::getUrl('edit', ['record' => $saved->id]) }}" style="text-decoration:underline">Modify</a>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($done['categories'])
                <p>{{ count($done['categories']) }} new categor{{ count($done['categories']) === 1 ? 'y was' : 'ies were' }} made <strong>hidden</strong>. Look at them in <a href="{{ \App\Filament\Resources\Categories\CategoryResource::getUrl('index') }}" style="text-decoration:underline">Categories</a> and switch on "Show on the site".</p>
            @endif
            @if ($done['skipped'])<p>{{ $done['skipped'] }} item(s) were skipped because of a problem.</p>@endif
        </x-filament::section>
    @endif

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Recent imports</x-slot>
        @forelse ($this->recent() as $log)
            <div style="padding:.35rem 0;border-bottom:1px solid rgba(128,128,128,.2)">
                {{ $log->created_at->format('j M Y H:i') }} · {{ $log->user?->name ?? '—' }} · {{ $log->kind }} ·
                {{ count($log->result['articles'] ?? []) }} article(s), {{ count($log->result['categories'] ?? []) }} categor{{ count($log->result['categories'] ?? []) === 1 ? 'y' : 'ies' }}, {{ $log->result['skipped'] ?? 0 }} skipped
            </div>
        @empty
            <p style="opacity:.7">Nothing imported yet.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
