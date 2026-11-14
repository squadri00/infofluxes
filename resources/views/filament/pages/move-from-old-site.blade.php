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
    @if (\App\Filament\Pages\MoveFromOldSite::storiesAvailable())
        <x-filament::section>
            <x-slot name="heading">The old slide stories</x-slot>
            <p style="max-width:52rem">
                The old "Stories in pictures" that have chapters and slides become <strong>Visual Stories</strong> (Content &gt; Visual Stories), with their pictures.
                The cards (claims with sources) and the fact files stay in the old site. A story whose title is already a story or an article here is left alone.
                A story that was published and read by a person is published; any other is a draft.
            </p>
            <label style="display:block;margin:.8rem 0;max-width:52rem">
                <strong>Put the stories in this category</strong><br>
                <select wire:model="storyCategory" style="width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent">
                    <option value="">Choose a category</option>
                    @foreach ($this->categoryChoices() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <x-filament::button wire:click="checkStories" color="gray">Check the stories</x-filament::button>
            @if ($storyResult && ! $storyDone && $storyResult['total']['stories_new'] > 0)
                <x-filament::button wire:click="moveStories" wire:confirm="Move {{ $storyResult['total']['stories_new'] }} stories now?">Move the stories now</x-filament::button>
            @endif

            @if ($storyResult)
                @php $t = $storyResult['total']; @endphp
                <ul style="margin:.8rem 0 0 1.2rem;list-style:disc">
                    <li><strong>{{ $storyDone ? 'Moved' : 'Would move' }}:</strong> {{ $t['stories_new'] }} stories, {{ $t['chapters'] }} chapters, {{ $t['slides'] }} slides</li>
                    <li>Pictures {{ $storyDone ? 'copied' : 'to copy' }}: {{ $t['images_copied'] }}@if ($t['images_missing'] > 0), <strong>missing in the old site: {{ $t['images_missing'] }}</strong>@endif</li>
                    <li>Published: {{ $t['published'] }}. Drafts: {{ $t['drafts'] }}</li>
                    <li>Already moved before: {{ $t['stories_existing'] }}</li>
                    @if ($t['stories_same_title'] > 0)
                        <li>Left alone because an article with the same title is already here ({{ $t['stories_same_title'] }}): {{ implode('; ', $storyResult['same_title']) }}</li>
                    @endif
                </ul>
            @endif

            <hr style="margin:1rem 0;border-color:rgba(128,128,128,.3)">
            <p style="max-width:52rem"><strong>Audio files.</strong> The audio of the old stories (for the Listen button) is copied into the matching slides. Every file stays <strong>Draft</strong> until a person listens and sets it to Approved. A slide that already has audio is left alone.</p>
            <x-filament::button wire:click="checkAudio" color="gray">Check the audio</x-filament::button>
            @if ($audioResult && ! $audioDone && $audioResult['total']['audio_copied'] > 0)
                <x-filament::button wire:click="moveAudio" wire:confirm="Copy {{ $audioResult['total']['audio_copied'] }} audio files now?">Bring the audio</x-filament::button>
            @endif
            @if ($audioResult)
                <ul style="margin:.8rem 0 0 1.2rem;list-style:disc">
                    <li><strong>{{ $audioDone ? 'Copied' : 'Would copy' }}:</strong> {{ $audioResult['total']['audio_copied'] }} audio files @if ($audioResult['total']['audio_missing'] > 0), <strong>missing in the old site: {{ $audioResult['total']['audio_missing'] }}</strong>@endif</li>
                    <li>Stories matched: {{ $audioResult['total']['stories_matched'] }}. Not moved yet: {{ $audioResult['total']['stories_not_moved'] }}. Slides matched: {{ $audioResult['total']['slides_matched'] }}. Not matched: {{ $audioResult['total']['slides_unmatched'] }}</li>
                </ul>
            @endif
        </x-filament::section>
    @endif
    @if (\App\Filament\Pages\MoveFromOldSite::heroAvailable())
        <x-filament::section>
            <x-slot name="heading">Your home page slider</x-slot>
            <p style="max-width:52rem">
                The sets of words, the slider pictures, and the banner settings (on or off, the seconds, the home headline, introduction and message) of the old home page.
                What is here already is not changed or made twice. You manage it all afterwards in <a href="{{ \App\Filament\Pages\SlideViewerSettings::getUrl() }}" style="text-decoration:underline">Sliders</a>.
            </p>
            <x-filament::button wire:click="checkHero" color="gray">Check the home slider</x-filament::button>
            @if ($heroResult && ! $heroDone && ($heroResult['total']['messages_new'] + $heroResult['total']['slides_new'] + $heroResult['total']['settings_changed']) > 0)
                <x-filament::button wire:click="bringHero">Bring the home slider</x-filament::button>
            @endif
            @if ($heroResult)
                @php $h = $heroResult['total']; @endphp
                <ul style="margin:.8rem 0 0 1.2rem;list-style:disc">
                    <li><strong>{{ $heroDone ? 'Brought' : 'Would bring' }}:</strong> {{ $h['messages_new'] }} sets of words, {{ $h['slides_new'] }} pictures, {{ $h['settings_changed'] }} settings</li>
                    <li>Already here: {{ $h['messages_existing'] }} sets of words, {{ $h['slides_existing'] }} pictures</li>
                    <li>Picture files {{ $heroDone ? 'copied' : 'to copy' }}: {{ $h['images_copied'] }} @if ($h['images_missing'] > 0)(<strong>missing in the old site: {{ $h['images_missing'] }}</strong>)@endif</li>
                </ul>
            @endif
        </x-filament::section>
    @endif
    @if (\App\Filament\Pages\MoveFromOldSite::peopleAvailable())
        <x-filament::section>
            <x-slot name="heading">Your contributors</x-slot>
            <p style="max-width:52rem">
                The people of the old site (name, role, short description, photo, public page). The people who asked for the old newsletter are not brought: they must confirm again with a code.
                What is here already is not changed or made twice. You manage them afterwards in <a href="{{ \App\Filament\Resources\People\PersonResource::getUrl() }}" style="text-decoration:underline">Contributors</a>.
            </p>
            <x-filament::button wire:click="checkPeople" color="gray">Check the contributors</x-filament::button>
            @if ($peopleResult && ! $peopleDone && ($peopleResult['total']['new'] + $peopleResult['total']['credits']) > 0)
                <x-filament::button wire:click="bringPeople">Bring the contributors</x-filament::button>
            @endif
            @if ($peopleResult)
                @php $pp = $peopleResult['total']; @endphp
                <ul style="margin:.8rem 0 0 1.2rem;list-style:disc">
                    <li><strong>{{ $peopleDone ? 'Brought' : 'Would bring' }}:</strong> {{ $pp['new'] }} people @if ($pp['names'])({{ implode(', ', $pp['names']) }})@endif, {{ $pp['photos'] }} photos</li>
                    <li>Credits on articles ({{ $peopleDone ? 'given' : 'to give' }}): {{ $pp['credits'] }} (an article of the same title as an old topic that named a contributor)</li>
                    <li>Already here: {{ $pp['existing'] }}</li>
                </ul>
            @endif
        </x-filament::section>
    @endif
    @if (\App\Filament\Pages\MoveFromOldSite::socialAvailable())
        <x-filament::section>
            <x-slot name="heading">Your social media and footer text</x-slot>
            <p style="max-width:52rem">
                The social accounts of the old site and the "about" text of the footer. The old addresses were only placeholders (the home page of each network), so replace them with your own in
                <a href="{{ \App\Filament\Resources\Social\SocialLinkResource::getUrl() }}" style="text-decoration:underline">Social media</a>.
                What is here already is not changed or made twice.
            </p>
            <x-filament::button wire:click="checkSocial" color="gray">Check social media</x-filament::button>
            @if ($socialResult && ! $socialDone && ($socialResult['total']['new'] + $socialResult['total']['footer_text']) > 0)
                <x-filament::button wire:click="bringSocial">Bring social media</x-filament::button>
            @endif
            @if ($socialResult)
                @php $s = $socialResult['total']; @endphp
                <ul style="margin:.8rem 0 0 1.2rem;list-style:disc">
                    <li><strong>{{ $socialDone ? 'Brought' : 'Would bring' }}:</strong> {{ $s['new'] }} accounts, {{ $s['footer_text'] ? 'the footer text' : 'no footer text' }}</li>
                    <li>Already here: {{ $s['existing'] }} accounts</li>
                </ul>
            @endif
        </x-filament::section>
    @endif
    @if (\App\Filament\Pages\MoveFromOldSite::pagesAvailable())
        <x-filament::section>
            <x-slot name="heading">Your website pages (About, Contact, Privacy...)</x-slot>
            <p style="max-width:52rem">
                The pages of the old site with their words as written. The pages that only held a test are not brought (the tests have their own pages).
                What is here already is not changed or made twice. You edit them afterwards in <a href="{{ \App\Filament\Resources\Pages\PageResource::getUrl() }}" style="text-decoration:underline">Pages</a>.
            </p>
            <x-filament::button wire:click="checkPages" color="gray">Check the pages</x-filament::button>
            @if ($pagesResult && ! $pagesDone && $pagesResult['total']['new'] > 0)
                <x-filament::button wire:click="bringPages">Bring the pages</x-filament::button>
            @endif
            @if ($pagesResult)
                @php $p = $pagesResult['total']; @endphp
                <ul style="margin:.8rem 0 0 1.2rem;list-style:disc">
                    <li><strong>{{ $pagesDone ? 'Brought' : 'Would bring' }}:</strong> {{ $p['new'] }} pages @if ($p['titles'])({{ implode(', ', $p['titles']) }})@endif</li>
                    <li>Already here: {{ $p['existing'] }}. Test pages left out: {{ $p['skipped_tests'] }}.</li>
                </ul>
            @endif
        </x-filament::section>
    @endif
    @if (\App\Filament\Pages\MoveFromOldSite::challengeAvailable())
        <x-filament::section>
            <x-slot name="heading">Your tests (Challenge yourself)</x-slot>
            <p style="max-width:52rem">
                The Arabic passages, the scripture passages, the fact statements, the quotes, the 114 chapters, the saved scores and the test settings (on or off, how many questions).
                What is here already is not changed or made twice. The questions are managed afterwards in the Challenge yourself group of the menu.
            </p>
            <x-filament::button wire:click="checkChallenge" color="gray">Check the tests</x-filament::button>
            @if ($challengeResult && ! $challengeDone)
                <x-filament::button wire:click="bringChallenge">Bring the tests</x-filament::button>
            @endif
            @if ($challengeResult)
                @php $c = $challengeResult['total']; @endphp
                <ul style="margin:.8rem 0 0 1.2rem;list-style:disc">
                    <li><strong>{{ $challengeDone ? 'Brought' : 'Would bring' }}:</strong>
                        {{ $c['quiz_passages_new'] }} Arabic passages, {{ $c['scripture_passages_new'] }} scripture passages, {{ $c['fact_statements_new'] }} fact statements,
                        {{ $c['quote_passages_new'] }} quotes, {{ $c['quran_chapters_new'] }} chapters, {{ $c['attempts_new'] }} saved scores, {{ $c['settings_changed'] }} settings</li>
                    <li>Already here: {{ $c['quiz_passages_existing'] }} Arabic passages, {{ $c['scripture_passages_existing'] }} scripture passages, {{ $c['fact_statements_existing'] }} fact statements, {{ $c['quote_passages_existing'] }} quotes, {{ $c['quran_chapters_existing'] }} chapters</li>
                </ul>
            @endif
        </x-filament::section>
    @endif
    @if (\App\Filament\Pages\MoveFromOldSite::viewerAvailable())
        <x-filament::section>
            <x-slot name="heading">Your slide viewer choices</x-slot>
            <p style="max-width:52rem">
                The old site keeps the choices you made for the slide viewer (the box width, the picture shape, the text size, the dots, and so on).
                This brings them across, so the story slider looks the same here. You can change any of them later in <a href="{{ \App\Filament\Pages\SlideViewerSettings::getUrl() }}" style="text-decoration:underline">Slide viewer</a>.
            </p>
            <x-filament::button wire:click="checkViewer" color="gray">Check the choices</x-filament::button>
            @if (is_array($viewerChanges) && count($viewerChanges) > 0)
                <x-filament::button wire:click="bringViewer" wire:confirm="Bring {{ count($viewerChanges) }} choices across?">Bring them across</x-filament::button>
            @endif
            @if ($viewerDone)
                <p style="margin-top:.7rem">Done. The slider now follows your old choices.</p>
            @elseif (is_array($viewerChanges))
                @if (count($viewerChanges) === 0)
                    <p style="margin-top:.7rem">Nothing to bring: the choices here are already the same.</p>
                @else
                    <table style="margin-top:.8rem;border-collapse:collapse;max-width:60rem">
                        <thead><tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.4)"><th style="padding:.3rem .6rem">Control</th><th style="padding:.3rem .6rem">Now</th><th style="padding:.3rem .6rem">Becomes</th></tr></thead>
                        <tbody>
                            @foreach ($viewerChanges as $key => $c)
                                <tr style="border-bottom:1px solid rgba(128,128,128,.2)"><td style="padding:.3rem .6rem">{{ \Illuminate\Support\Str::limit($c['label'], 70) }}</td><td style="padding:.3rem .6rem">{{ $c['now'] }}</td><td style="padding:.3rem .6rem"><strong>{{ $c['old'] }}</strong></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
