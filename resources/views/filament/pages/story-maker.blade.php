<x-filament-panels::page>
    <style>
        .sm-sel, .sm-sel option { background-color: #ffffff !important; color: #111827 !important; }
        .dark .sm-sel, .dark .sm-sel option { background-color: #18181b !important; color: #f4f4f5 !important; }
        .sm-cat-list { background: #ffffff; color: #111827; }
        .dark .sm-cat-list { background: #18181b; color: #f4f4f5; }
        .sm-cat-list button:hover { background: rgba(31, 95, 139, .18) !important; }
    </style>
    @php
        $box = 'width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent';
        $mini = 'cursor:pointer;border:1px solid rgba(128,128,128,.6);border-radius:.3rem;padding:.1rem .6rem';
    @endphp

    <p style="max-width:52rem">
        This tool makes <strong>stories in pictures</strong>. 1: build the prompt and copy it into an AI chat. 2: paste the answer here and press <strong>Check</strong>.
        3: press <strong>Save as a draft</strong>. Then add the pictures, and publish the story yourself. Nothing is published from this screen.
        Every run is kept under <a href="#saved-runs" style="text-decoration:underline">Saved runs</a>, so you can come back to a prompt or an answer later.
        This tool is separate from the AI import and the AI prompts of the articles.
    </p>

    {{-- Step 1: the prompt --}}
    <x-filament::section>
        <x-slot name="heading">Step 1. Make the prompt for the story (English)</x-slot>
        <div style="display:grid;gap:.9rem;max-width:60rem">
            @if ($runId)
                <div style="padding:.45rem .7rem;border:1px dashed rgba(128,128,128,.6);border-radius:.4rem">
                    You are working on a saved run (#{{ $runId }}). <button type="button" wire:click="newRun" style="{{ $mini }}">Start a new story</button>
                </div>
            @endif
            <label>
                <strong>The subject of the story</strong> <span style="opacity:.7">(the idea or the title, in a few words)</span><br>
                <input type="text" wire:model.live.debounce.400ms="topic" maxlength="300" style="{{ $box }}" placeholder="For example: The night Prophet Muhammad ﷺ travelled to Jerusalem">
            </label>

            <div x-data="{
                    options: @js(collect($this->categoryChoices())->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()),
                    query: '', open: false,
                    get shown() { const q = this.query.trim().toLowerCase(); const list = q === '' ? this.options : this.options.filter(o => o.label.toLowerCase().includes(q)); return list.slice(0, 60); },
                    get total() { const q = this.query.trim().toLowerCase(); return q === '' ? this.options.length : this.options.filter(o => o.label.toLowerCase().includes(q)).length; },
                    pick(o) { this.query = ''; this.open = false; $wire.set('categoryId', o.id); },
                    clear() { this.query = ''; $wire.set('categoryId', null); },
                    current() { const id = $wire.categoryId; const o = this.options.find(x => String(x.id) === String(id)); return o ? o.label : ''; }
                }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" style="position:relative">
                <strong>Category</strong> <span style="opacity:.7">(where the story will be saved)</span><br>
                <div x-show="current() !== ''" x-cloak style="display:flex;gap:.5rem;align-items:center;margin:.3rem 0;padding:.45rem .6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem">
                    <span x-text="current()" style="flex:1"></span>
                    <button type="button" x-on:click="clear()" style="{{ $mini }}">Change</button>
                </div>
                <input type="text" x-show="current() === ''" x-model="query" x-on:focus="open = true" x-on:input="open = true" autocomplete="off" placeholder="Type a word to find the category" style="{{ $box }}">
                <div class="sm-cat-list" x-show="open && current() === ''" x-cloak style="position:absolute;z-index:30;left:0;right:0;max-height:18rem;overflow-y:auto;margin-top:.2rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem;box-shadow:0 8px 24px rgba(0,0,0,.35)">
                    <template x-for="o in shown" :key="o.id">
                        <button type="button" x-on:click="pick(o)" x-text="o.label" style="display:block;width:100%;text-align:left;padding:.45rem .7rem;cursor:pointer;border:0;border-bottom:1px solid rgba(128,128,128,.25);background:transparent;color:inherit;font-size:.9rem"></button>
                    </template>
                    <div x-show="shown.length === 0" style="padding:.6rem .7rem;opacity:.7">No category matches. Try another word.</div>
                    <div x-show="total > 60" style="padding:.4rem .7rem;opacity:.7;font-size:.85rem" x-text="'Showing 60 of ' + total + '. Type more letters to narrow the list.'"></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:minmax(8rem,12rem) 1fr;gap:.9rem">
                <label>
                    <strong>Slides</strong> <span style="opacity:.7">(as many as the story needs)</span><br>
                    <input type="number" wire:model.live.debounce.600ms="slides" min="{{ \App\Support\StoryMaker\Prompt::MIN_SLIDES }}" max="{{ \App\Support\StoryMaker\Prompt::MAX_SLIDES }}" style="{{ $box }}">
                </label>
                <label>
                    <strong>Look of the pictures</strong> <span style="opacity:.7">(one line, the same for every picture of this story)</span><br>
                    <input type="text" wire:model.live.debounce.600ms="style" maxlength="400" style="{{ $box }}">
                </label>
            </div>
            <label>
                <strong>Notes for the AI</strong> <span style="opacity:.7">(optional: a point to include, a part to leave out)</span><br>
                <textarea wire:model.live.debounce.600ms="notes" rows="3" maxlength="1500" style="{{ $box }}"></textarea>
            </label>

            <div x-data="{ copied: false }">
                <textarea x-ref="prompt" readonly rows="10" style="width:100%;padding:.6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent;font-family:monospace;font-size:.85rem">{{ $this->prompt() }}</textarea>
                <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-top:.5rem">
                    <button type="button" class="fi-btn" style="padding:.45rem .9rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem;cursor:pointer"
                            x-on:click="navigator.clipboard.writeText($refs.prompt.value).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                        <span x-show="!copied">Copy the prompt</span><span x-show="copied" x-cloak>Copied</span>
                    </button>
                    <x-filament::button color="gray" wire:click="saveRun">Save this run</x-filament::button>
                    <span style="opacity:.7">The rules for stories are already inside the prompt. Saving keeps your choices, the prompt and the answer you paste below.</span>
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
                <span style="opacity:.7">Check saves no story, but it keeps this run.</span>
            </div>
        </div>
    </x-filament::section>

    {{-- Step 3: what was found --}}
    @if ($checked && $result !== [])
        @php
            $errors = $result['errors'] ?? [];
            $warnings = $result['warnings'] ?? [];
            $story = $result['story'] ?? [];
        @endphp
        <x-filament::section>
            <x-slot name="heading">Step 3. What was found</x-slot>
            <div style="display:grid;gap:.8rem;max-width:60rem">
                <div style="padding:.7rem .9rem;border:1px solid {{ $errors ? '#d92d20' : ($warnings ? '#dc9b00' : '#2d9d5a') }};border-radius:.5rem">
                    <strong>{{ $errors ? '✗' : '✓' }} {{ $story['title'] ?: '(no title)' }}</strong>
                    <span style="opacity:.75"> &middot; {{ $story['slide_count'] ?? 0 }} slides in {{ count($story['chapters'] ?? []) }} chapters</span>
                    @foreach ($errors as $error)<div style="color:#d92d20">Problem: {{ $error }}</div>@endforeach
                    @foreach ($warnings as $warning)<div style="color:#b07800">Note: {{ $warning }}</div>@endforeach
                    @if (! $errors && ! $warnings)<div style="color:#2d9d5a">Nothing to fix.</div>@endif

                    @if (! empty($story['short']))<p style="margin:.6rem 0 0;opacity:.85">{{ $story['short'] }}</p>@endif

                    @foreach (($story['chapters'] ?? []) as $ci => $chapter)
                        <details style="margin-top:.5rem" @if ($loop->last) open @endif>
                            <summary style="cursor:pointer"><strong>Chapter {{ $ci + 1 }}: {{ $chapter['title'] }}</strong> <span style="opacity:.7">({{ count($chapter['slides']) }} slide{{ count($chapter['slides']) === 1 ? '' : 's' }})</span></summary>
                            <ol style="margin:.3rem 0 0 1.3rem;list-style:decimal;display:grid;gap:.5rem">
                                @foreach ($chapter['slides'] as $slide)
                                    <li>
                                        <strong>{{ $slide['heading'] }}</strong>
                                        <div style="white-space:pre-line">{{ $slide['body'] }}</div>
                                        @if ($slide['picture'] !== '')<div style="opacity:.7;font-size:.88rem">Picture: {{ $slide['picture'] }}</div>@endif
                                    </li>
                                @endforeach
                            </ol>
                        </details>
                    @endforeach

                    @if (! empty($result['check']))
                        <details style="margin-top:.6rem" open>
                            <summary style="cursor:pointer"><strong>Please check ({{ count($result['check']) }})</strong></summary>
                            <ul style="margin:.3rem 0 0 1.2rem;list-style:disc">@foreach ($result['check'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                        </details>
                    @endif
                </div>

                @if ($done)
                    <div style="padding:.7rem .9rem;border:1px solid #2d9d5a;border-radius:.5rem">
                        <strong>{{ $done['updated'] ? 'Updated' : 'Saved' }}: the draft story {{ $done['code'] }}, {{ $done['title'] }}</strong>
                        ({{ $done['slides'] }} slides). It is a <strong>draft</strong>: add its pictures, read it, and publish it yourself.
                        <a href="{{ $done['url'] }}" style="text-decoration:underline;font-weight:600">Open the story &rarr;</a> &middot; <a href="#pictures" style="text-decoration:underline;font-weight:600">Add the pictures (Step 4) &darr;</a> &middot; <a href="{{ \App\Filament\Pages\StoryTranslations::getUrl(['story' => $run?->story_id ?? $this->run()?->story_id]) }}" style="text-decoration:underline;font-weight:600">Translate it (step 2) &rarr;</a>
                    </div>
                @else
                    <div>
                        <x-filament::button wire:click="saveStory" wire:loading.attr="disabled" :disabled="! empty($errors) || ! $categoryId">Save as a draft</x-filament::button>
                        <span style="margin-left:.6rem;opacity:.7">
                            @if ($errors) Fix the problems first.
                            @elseif (! $categoryId) Choose the category in Step 1 first.
                            @else Saving again updates the same draft while it is still a draft. @endif
                        </span>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif

    {{-- Step 4: the pictures of the story (bulk upload) --}}
    @php
        $pictureStory = $this->pictureStory();
    @endphp
    @if ($pictureStory)
        @php
            $plan = $this->picturePlan();
            $files = $this->orderedPictures();
            $slots = \App\Support\StoryMaker\Pictures::slots($pictureStory);
            $haveCount = collect($slots)->where('has', true)->count();
        @endphp
        <div id="pictures">
        <x-filament::section>
            <x-slot name="heading">Step 4. Pictures for {{ $pictureStory->code }}: {{ $pictureStory->mainTitle() }}</x-slot>
            <div style="display:grid;gap:.9rem;max-width:60rem">
                <div style="opacity:.85">
                    The story has a cover and {{ count($slots) - 1 }} slide(s). {{ $haveCount }} of {{ count($slots) }} already have a picture.
                    Choose <strong>all the pictures at once</strong>. They are put in order by file name (so name them 01, 02, 03 …, or let your image maker number them), and each goes to its place below.
                    <a href="{{ \App\Filament\Resources\Stories\StoryResource::getUrl('edit', ['record' => $pictureStory]) }}" style="text-decoration:underline">Open the story</a>
                    &middot; <button type="button" wire:click="closePictures" style="{{ $mini }}">Close</button>
                </div>

                <div x-data="{ copied: false }">
                    <strong>Picture lines to give to your image maker</strong>
                    <textarea x-ref="lines" readonly rows="6" style="width:100%;margin-top:.3rem;padding:.6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent;font-size:.85rem">{{ $this->pictureLines() }}</textarea>
                    <button type="button" class="fi-btn" style="margin-top:.4rem;padding:.35rem .8rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem;cursor:pointer"
                            x-on:click="navigator.clipboard.writeText($refs.lines.value).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                        <span x-show="!copied">Copy the picture lines</span><span x-show="copied" x-cloak>Copied</span>
                    </button>
                    <span style="opacity:.7;margin-left:.5rem">The cover has no line: use any picture that shows the whole story.</span>
                </div>

                <div style="padding:.7rem .9rem;border:1px dashed rgba(128,128,128,.6);border-radius:.5rem;display:grid;gap:.6rem">
                    <label><strong>Choose the pictures</strong> <span style="opacity:.7">(JPG, PNG, WebP or GIF, each up to 12 MB; they are made light and cut to the shape of the story)</span><br>
                        <input type="file" wire:model="pictures" multiple accept="image/jpeg,image/png,image/webp,image/gif" style="margin-top:.3rem">
                    </label>
                    <div wire:loading wire:target="pictures" style="opacity:.8">Uploading the pictures …</div>
                    @if ($pictureError = collect($this->getErrorBag()->all())->first())<div style="color:#d92d20">{{ $pictureError }}</div>@endif
                    <label style="display:flex;gap:.5rem;align-items:flex-start"><input type="checkbox" wire:model.live="firstIsCover" style="margin-top:.3rem"> <span>The <strong>first</strong> picture is the cover.</span></label>
                    <label style="display:flex;gap:.5rem;align-items:flex-start"><input type="checkbox" wire:model.live="replacePictures" style="margin-top:.3rem"> <span>Also replace the pictures that are already on the slides. <span style="opacity:.7">(Off: the pictures go to the slides that have none, in order.)</span></span></label>
                </div>

                @if ($plan)
                    <div>
                        <strong>Where each picture goes</strong> <span style="opacity:.7">({{ count($files) }} picture(s) chosen)</span>
                        <div style="margin-top:.4rem;display:grid;gap:.35rem">
                            @foreach ($plan['rows'] as $row)
                                @php $file = $row['file'] !== null ? ($files[$row['file']] ?? null) : null; @endphp
                                <div wire:key="row-{{ $loop->index }}" style="display:flex;gap:.7rem;align-items:center;padding:.35rem .5rem;border:1px solid rgba(128,128,128,.3);border-radius:.4rem">
                                    <strong style="min-width:5rem">{{ $row['label'] }}</strong>
                                    @if ($file)
                                        @php try { $thumb = $file->temporaryUrl(); } catch (\Throwable $e) { $thumb = null; } @endphp
                                        @if ($thumb)<img src="{{ $thumb }}" alt="" style="width:64px;height:40px;object-fit:cover;border-radius:.3rem">@endif
                                        <span style="flex:1;min-width:8rem">{{ $file->getClientOriginalName() }}
                                            <span style="opacity:.65">{{ $row['state'] === 'replace' ? '(replaces its picture)' : '' }}</span></span>
                                        <button type="button" wire:click="movePicture({{ $row['file'] }}, -1)" style="{{ $mini }}" title="Move up" @disabled($row['file'] === 0)>↑</button>
                                        <button type="button" wire:click="movePicture({{ $row['file'] }}, 1)" style="{{ $mini }}" title="Move down" @disabled($row['file'] === count($files) - 1)>↓</button>
                                        <button type="button" wire:click="removePicture({{ $row['file'] }})" style="{{ $mini }}" title="Take this picture out">✕</button>
                                    @elseif ($row['state'] === 'kept')
                                        <span style="opacity:.7;flex:1">keeps its picture</span>
                                    @elseif ($row['state'] === 'skip')
                                        <span style="opacity:.7;flex:1">{{ $row['has'] ? 'keeps its cover' : 'no cover picture' }}</span>
                                    @else
                                        <span style="color:#b07800;flex:1">no picture yet</span>
                                    @endif
                                    @if ($row['type'] === 'slide' && $row['picture'] !== '')<span style="opacity:.55;font-size:.82rem;max-width:22rem">{{ \Illuminate\Support\Str::limit($row['picture'], 70) }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                        @if ($plan['extra'])
                            <div style="margin-top:.4rem;color:#b07800">{{ count($plan['extra']) }} picture(s) have no place and are not used: {{ collect($plan['extra'])->map(fn ($i) => $files[$i]->getClientOriginalName())->take(6)->implode(', ') }}{{ count($plan['extra']) > 6 ? ' …' : '' }}</div>
                        @endif
                        @if ($files && $plan['empty'])
                            <div style="margin-top:.4rem;color:#b07800">No picture for: {{ implode(', ', $plan['empty']) }}. You can add them later.</div>
                        @endif
                    </div>
                    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
                        <x-filament::button wire:click="applyPictures" wire:loading.attr="disabled" wire:target="applyPictures" :disabled="count($files) === 0">Put the pictures on the story</x-filament::button>
                        @if ($files)<x-filament::button color="gray" wire:click="clearPictures">Clear the choice</x-filament::button>@endif
                        <span wire:loading wire:target="applyPictures" style="opacity:.8">Making the pictures … this can take a little while.</span>
                    </div>
                @endif

                @if ($pictureReport)
                    <div style="padding:.7rem .9rem;border:1px solid {{ $pictureReport['problems'] ? '#dc9b00' : '#2d9d5a' }};border-radius:.5rem">
                        <strong>Done.</strong>
                        {{ $pictureReport['cover'] ? 'The cover is set. ' : '' }}{{ $pictureReport['slides'] }} slide picture(s) were added{{ $pictureReport['replaced'] ? ' ('.$pictureReport['replaced'].' replaced)' : '' }}.
                        @foreach ($pictureReport['problems'] as $problem)<div style="color:#b07800">Problem: {{ $problem }}</div>@endforeach
                        @if ($pictureReport['extra'])<div style="color:#b07800">Not used (no place): {{ implode(', ', $pictureReport['extra']) }}</div>@endif
                        @if ($pictureReport['empty'])<div style="color:#b07800">Still without a picture: {{ implode(', ', $pictureReport['empty']) }}</div>@endif
                        <div style="margin-top:.3rem"><a href="{{ \App\Filament\Resources\Stories\StoryResource::getUrl('edit', ['record' => $pictureStory]) }}" style="text-decoration:underline;font-weight:600">Open the story to look at it &rarr;</a></div>
                    </div>
                @endif
            </div>
        </x-filament::section>
        </div>
    @endif
    {{-- Saved runs --}}
    <div id="saved-runs">
        <x-filament::section :collapsed="$runId !== null || $paste !== ''" collapsible>
            <x-slot name="heading">Saved runs ({{ $this->runs()->count() }})</x-slot>
            <div style="max-width:60rem;display:grid;gap:.4rem">
                @forelse ($this->runs() as $r)
                    <div wire:key="run-{{ $r->id }}" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;padding:.45rem .6rem;border:1px solid rgba(128,128,128,.35);border-radius:.4rem">
                        <span style="flex:1;min-width:12rem"><strong>{{ $r->topic !== '' ? \Illuminate\Support\Str::limit($r->topic, 80) : '(no subject)' }}</strong>
                            <span style="opacity:.7"> &middot; {{ \App\Models\StoryRun::STATUSES[$r->status] ?? $r->status }} &middot; {{ $r->updated_at->diffForHumans() }}@if ($r->story) &middot; {{ $r->story->code }}@endif</span></span>
                        <button type="button" wire:click="openRun({{ $r->id }})" style="{{ $mini }}">Open</button>
                        @if ($r->story)<button type="button" wire:click="openPictures({{ $r->id }})" style="{{ $mini }}">Pictures</button>@endif
                        <button type="button" wire:click="deleteRun({{ $r->id }})" wire:confirm="Delete this saved run? The story it made (if any) is not deleted." style="{{ $mini }}">Delete</button>
                    </div>
                @empty
                    <p style="opacity:.7">Nothing yet. A run is saved when you press "Save this run", "Check" or "Save as a draft".</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>

    {{-- The rules (super user) --}}
    @if (auth()->user()->isSuperuser())
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Rules of the Story maker (super user)</x-slot>
            <div style="max-width:60rem;display:grid;gap:.7rem">
                <p style="margin:0;opacity:.8">These rules are put at the top of every story prompt. They are not the rules of the article prompts. "Reset to the original" brings back the first words.</p>
                <label><strong>The usual look of the pictures</strong>
                    <input type="text" wire:model="ruleStyle" maxlength="400" style="{{ $box }}">
                </label>
                <textarea wire:model="ruleText" rows="22" style="width:100%;padding:.6rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent;font-family:monospace;font-size:.85rem"></textarea>
                <div style="display:flex;gap:.6rem;align-items:center">
                    <x-filament::button wire:click="saveRules">Save the rules</x-filament::button>
                    <x-filament::button color="gray" wire:click="resetRules" wire:confirm="Put the original rules back?">Reset to the original</x-filament::button>
                    @if (\App\Support\StoryMaker\Rules::isChanged())<span style="opacity:.75">The rules were changed from the original.</span>@endif
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
