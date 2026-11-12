<x-filament-panels::page>
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
                <select wire:model.live="kind" style="padding:.5rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent">
                    @foreach ($this->kinds() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <strong>{{ $kind === 'categories' ? 'The area to plan' : ($kind === 'fact' ? 'The fact to check' : 'The subject of the article') }}</strong><br>
                <input type="text" wire:model.live.debounce.400ms="topic" maxlength="300" style="width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent" placeholder="Write it in a few words">
            </label>
            <div x-data="{ copied: false }">
                <textarea x-ref="prompt" readonly rows="10" style="width:100%;padding:.6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent;font-family:monospace;font-size:.85rem">{{ $this->prompt() }}</textarea>
                <button type="button" class="fi-btn" style="margin-top:.5rem;padding:.45rem .9rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem;cursor:pointer"
                        x-on:click="navigator.clipboard.writeText($refs.prompt.value).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                    <span x-show="!copied">Copy the prompt</span><span x-show="copied" x-cloak>Copied</span>
                </button>
                <span style="margin-left:.6rem;opacity:.7">The list of your present categories is already inside the prompt.</span>
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
                    </div>
                @endforeach

                <div>
                    @if ($this->okCount() > 0)
                        <x-filament::button wire:click="save" wire:confirm="Save {{ $this->okCount() }} item(s) as drafts? Items with a problem are skipped.">Save as drafts ({{ $this->okCount() }})</x-filament::button>
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
            <p>{{ count($done['articles']) }} draft article(s) were made. Find them in <a href="{{ \App\Filament\Resources\Articles\ArticleResource::getUrl('index') }}" style="text-decoration:underline">Articles</a>, read them, write your name in <strong>Reviewer</strong>, and publish.</p>
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
