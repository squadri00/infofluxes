<x-filament-panels::page>
    @php
        $status = $this->status();
        $report = $this->report();
        $box = 'border:1px solid rgba(128,128,128,.35);border-radius:.6rem;padding:.9rem 1rem';
        $muted = 'opacity:.7;font-size:.85rem';
    @endphp

    {{-- Setup: shown until the connection works --}}
    @if (! $report['ok'] || ! $this->configured())
        <div style="{{ $box }};max-width:56rem;display:grid;gap:.8rem">
            @if (! empty($report['error']))
                <div style="padding:.7rem .9rem;border:1px solid #d92d20;border-radius:.5rem;color:#d92d20">
                    <strong>Google Analytics could not be read.</strong> {{ $report['error'] }}
                </div>
            @endif
            <p style="font-weight:700;font-size:1.05rem;margin:0">Connect Google Analytics (one time, about 10 minutes)</p>
            <p style="margin:0">This page shows the numbers of your Google Analytics in plain words. It only reads; it can never change anything in Google Analytics.</p>
            <ol style="margin:0 0 0 1.2rem;list-style:decimal;display:grid;gap:.5rem">
                <li>Open <a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank" rel="noopener" style="text-decoration:underline">Google Cloud: Google Analytics Data API</a>, pick (or create) a project, and press <strong>Enable</strong>.</li>
                <li>In that project open <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" rel="noopener" style="text-decoration:underline">Service accounts</a>, press <strong>Create service account</strong>, give it a name, and finish. Then open it, go to <strong>Keys &rarr; Add key &rarr; Create new key &rarr; JSON</strong>. A file is downloaded.</li>
                <li>Open the downloaded file in Notepad and find <code>client_email</code>: it looks like <em>name@project.iam.gserviceaccount.com</em>. In <a href="https://analytics.google.com/" target="_blank" rel="noopener" style="text-decoration:underline">Google Analytics</a> go to <strong>Admin &rarr; Property access management &rarr; +</strong> and add that email with the role <strong>Viewer</strong>.</li>
                <li>In Google Analytics go to <strong>Admin &rarr; Property details</strong> and copy the <strong>Property ID</strong>. It is a number (like 123456789), not the <em>G-...</em> code.</li>
                <li>Type the number below, choose the downloaded JSON file, and press <strong>Save</strong>.</li>
            </ol>
        </div>
    @endif

    {{-- The connection box (always there, small once it works) --}}
    <details @if (! $this->configured()) open @endif style="{{ $box }};max-width:56rem">
        <summary style="cursor:pointer;font-weight:600">Connection
            @if ($this->configured()) <span style="color:#16a34a">&#10003; connected</span> @else <span style="color:#d92d20">not connected</span> @endif
        </summary>
        <form wire:submit="saveSetup" style="display:grid;gap:.7rem;margin-top:.8rem">
            <label style="display:grid;gap:.25rem"><span style="font-weight:600">Property ID (a number)</span>
                <input type="text" wire:model="propertyId" inputmode="numeric" placeholder="123456789" style="padding:.5rem .7rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent;max-width:18rem">
            </label>
            <label style="display:grid;gap:.25rem"><span style="font-weight:600">Key file (JSON) {{ $status['key'] ? '– already saved, choose a file only to replace it' : '' }}</span>
                <input type="file" wire:model="keyFile" accept=".json,application/json">
                @error('keyFile')<span style="color:#d92d20">{{ $message }}</span>@enderror
            </label>
            @if ($status['email'])
                <p style="margin:0;{{ $muted }}">Service account: <strong>{{ $status['email'] }}</strong> (this email must be a Viewer in Google Analytics). The key itself is stored on the server, never shown and never in the public folder.</p>
            @endif
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <x-filament::button type="submit">Save</x-filament::button>
                @if ($status['key'])
                    <x-filament::button color="danger" outlined wire:click="removeKey" wire:confirm="Delete the key file from the server?">Delete the key file</x-filament::button>
                @endif
            </div>
        </form>
    </details>

    @if ($report['ok'])
        @php
            $d = $report['data'];
            $n = $d['now'];
            $b = $d['before'];
            $fmtDate = fn ($s) => \Carbon\Carbon::parse($s)->format('j M');
        @endphp

        {{-- Range + refresh --}}
        <div style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;justify-content:space-between">
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
                @foreach (['7' => 'Last 7 days', '28' => 'Last 28 days', '90' => 'Last 90 days'] as $v => $label)
                    <x-filament::button size="sm" :color="$this->range === (string) $v ? 'primary' : 'gray'" wire:click="$set('range', '{{ $v }}')">{{ $label }}</x-filament::button>
                @endforeach
                <span style="{{ $muted }}">{{ $fmtDate($d['from']) }} – {{ $fmtDate($d['to']) }}, compared with the {{ $d['days'] }} days before</span>
            </div>
            <div style="display:flex;gap:.6rem;align-items:center">
                <span style="{{ $muted }}">Numbers from {{ \Carbon\Carbon::parse($d['fetched_at'])->diffForHumans() }}</span>
                <x-filament::button size="sm" color="gray" wire:click="refreshNumbers" icon="heroicon-m-arrow-path">Refresh</x-filament::button>
            </div>
        </div>

        {{-- Right now --}}
        <div style="{{ $box }};display:grid;gap:.5rem">
            <div style="display:flex;align-items:center;gap:.7rem;flex-wrap:wrap">
                <span style="display:inline-block;width:.7rem;height:.7rem;border-radius:50%;background:#16a34a;box-shadow:0 0 0 4px rgba(22,163,74,.25)"></span>
                @if ($report['live'])
                    <span style="font-size:1.6rem;font-weight:700">{{ $this->num($report['live']['users']) }}</span>
                    <span>{{ $report['live']['users'] === 1 ? 'person is' : 'people are' }} on the site right now <span style="{{ $muted }}">(last 30 minutes)</span></span>
                @else
                    <span>Live count not available. {{ $report['live_error'] ?? '' }}</span>
                @endif
            </div>
            @if ($report['live'] && count($report['live']['pages']))
                <ul style="margin:0 0 0 1.2rem;list-style:disc;{{ $muted }}">
                    @foreach ($report['live']['pages'] as $p)<li>{{ $p['name'] }} &middot; {{ $p['value'] }}</li>@endforeach
                </ul>
            @endif
        </div>

        {{-- The big numbers --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:.8rem">
            @foreach ([
                ['Visitors', 'People who came to the site (counted once each).', $n['activeUsers'], $b['activeUsers'], $this->num($n['activeUsers'])],
                ['New visitors', 'People who came for the first time.', $n['newUsers'], $b['newUsers'], $this->num($n['newUsers'])],
                ['Visits', 'Times people came. One person can visit more than once.', $n['sessions'], $b['sessions'], $this->num($n['sessions'])],
                ['Pages opened', 'How many pages were opened in total.', $n['screenPageViews'], $b['screenPageViews'], $this->num($n['screenPageViews'])],
                ['Time on the site', 'Average time of one visit.', $n['averageSessionDuration'], $b['averageSessionDuration'], $this->duration($n['averageSessionDuration'])],
                ['Engaged visits', 'Visits where people stayed, read or clicked (not left at once).', $n['engagementRate'], $b['engagementRate'], round($n['engagementRate'] * 100).'%'],
            ] as [$label, $help, $now, $before, $shown])
                @php $c = $this->change((float) $now, (float) $before); @endphp
                <div style="{{ $box }};display:grid;gap:.2rem" title="{{ $help }}">
                    <span style="{{ $muted }}">{{ $label }}</span>
                    <span style="font-size:1.9rem;font-weight:700;line-height:1.1">{{ $shown }}</span>
                    @if ($c)
                        <span style="font-size:.85rem;font-weight:600;color:{{ $c['dir'] === 'up' ? '#16a34a' : ($c['dir'] === 'down' ? '#d92d20' : 'inherit') }}">
                            {{ $c['dir'] === 'up' ? '▲' : ($c['dir'] === 'down' ? '▼' : '■') }} {{ $c['text'] }} <span style="font-weight:400;opacity:.7">before</span>
                        </span>
                    @else
                        <span style="{{ $muted }}">nothing to compare yet</span>
                    @endif
                    <span style="{{ $muted }}">{{ $help }}</span>
                </div>
            @endforeach
        </div>

        {{-- Visitors each day --}}
        @php
            $pts = $d['per_day'];
            $count = max(1, count($pts) - 1);
            $max = max(1, max(array_column($pts, 'visitors')));
            $W = 640; $H = 170; $padL = 8; $padT = 10; $padB = 6;
            $x = fn ($i) => $padL + ($W - $padL * 2) * ($i / $count);
            $y = fn ($v) => $padT + ($H - $padT - $padB) * (1 - $v / $max);
            $line = '';
            foreach ($pts as $i => $p) { $line .= ($i ? 'L' : 'M').round($x($i), 1).' '.round($y($p['visitors']), 1).' '; }
            $area = $line.'L'.round($x(count($pts) - 1), 1).' '.($H - $padB).' L'.round($x(0), 1).' '.($H - $padB).' Z';
        @endphp
        <div style="{{ $box }}">
            <p style="font-weight:700;margin:0 0 .1rem">Visitors each day</p>
            <p style="margin:0 0 .6rem;{{ $muted }}">The higher the line, the more people came that day. The highest day had {{ $this->num($max) }} {{ $max === 1 ? 'visitor' : 'visitors' }}.</p>
            @if (array_sum(array_column($pts, 'visitors')) === 0)
                <p style="margin:0;{{ $muted }}">No visitors were counted in this time yet.</p>
            @else
                <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="Visitors each day" style="width:100%;height:auto;max-height:14rem;color:var(--primary-600, #d97706)">
                    <line x1="{{ $padL }}" y1="{{ $H - $padB }}" x2="{{ $W - $padL }}" y2="{{ $H - $padB }}" stroke="currentColor" stroke-opacity=".25"/>
                    <line x1="{{ $padL }}" y1="{{ $y($max / 2) }}" x2="{{ $W - $padL }}" y2="{{ $y($max / 2) }}" stroke="currentColor" stroke-opacity=".12" stroke-dasharray="4 4"/>
                    <path d="{{ $area }}" fill="currentColor" fill-opacity=".14"/>
                    <path d="{{ $line }}" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>
                    @foreach ($pts as $i => $p)
                        <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($p['visitors']), 1) }}" r="{{ count($pts) > 40 ? 2 : 3.4 }}" fill="currentColor"><title>{{ \Carbon\Carbon::parse($p['date'])->format('D j M') }}: {{ $p['visitors'] }} visitors, {{ $p['views'] }} pages opened</title></circle>
                    @endforeach
                </svg>
                <div style="display:flex;justify-content:space-between;{{ $muted }}"><span>{{ $fmtDate($pts[0]['date']) }}</span><span>{{ $fmtDate($pts[intdiv(count($pts), 2)]['date']) }}</span><span>{{ $fmtDate($pts[count($pts) - 1]['date']) }}</span></div>
            @endif
        </div>

        {{-- Most read pages --}}
        <div style="{{ $box }}">
            <p style="font-weight:700;margin:0 0 .1rem">Most read pages</p>
            <p style="margin:0 0 .6rem;{{ $muted }}">The pages people opened most in this time.</p>
            @if (empty($d['pages']))
                <p style="margin:0;{{ $muted }}">Nothing yet.</p>
            @else
                @php $topViews = max(1, max(array_column($d['pages'], 'views'))); @endphp
                <div style="display:grid;gap:.45rem">
                    @foreach ($d['pages'] as $p)
                        <div>
                            <div style="display:flex;justify-content:space-between;gap:1rem">
                                <a href="{{ url($p['path']) }}" target="_blank" rel="noopener" style="text-decoration:underline;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $p['title'] ?: $p['path'] }}</a>
                                <span style="white-space:nowrap"><strong>{{ $this->num($p['views']) }}</strong> <span style="{{ $muted }}">opened by {{ $this->num($p['visitors']) }}</span></span>
                            </div>
                            <div style="height:.3rem;border-radius:1rem;background:rgba(128,128,128,.2);margin-top:.2rem"><div style="height:100%;width:{{ round($p['views'] / $topViews * 100) }}%;border-radius:1rem;background:var(--primary-600, #d97706)"></div></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Where from, who, what device --}}
        @php
            $lists = [
                ['How people found the site', 'Which road brought them here.', array_map(fn ($r) => ['name' => \App\Support\GoogleAnalytics::channelName($r['name']), 'value' => $r['value']], $d['channels']), 'visits'],
                ['Websites that sent people', 'The exact website or app (for example google, facebook).', array_map(fn ($r) => ['name' => $r['name'] === '(direct)' ? 'No website (typed or bookmark)' : $r['name'], 'value' => $r['value']], $d['sources']), 'visits'],
                ['Countries', 'Where your visitors are.', $d['countries'], 'visitors'],
                ['Computer, phone or tablet', 'What people read on.', array_map(fn ($r) => ['name' => \App\Support\GoogleAnalytics::deviceName($r['name']), 'value' => $r['value']], $d['devices']), 'visitors'],
            ];
        @endphp
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(20rem,1fr));gap:.8rem">
            @foreach ($lists as [$heading, $help, $rows, $unit])
                @php $total = max(1, array_sum(array_column($rows, 'value'))); @endphp
                <div style="{{ $box }}">
                    <p style="font-weight:700;margin:0 0 .1rem">{{ $heading }}</p>
                    <p style="margin:0 0 .6rem;{{ $muted }}">{{ $help }}</p>
                    @if (empty($rows))
                        <p style="margin:0;{{ $muted }}">Nothing yet.</p>
                    @else
                        <div style="display:grid;gap:.45rem">
                            @foreach ($rows as $row)
                                <div>
                                    <div style="display:flex;justify-content:space-between;gap:1rem"><span>{{ $row['name'] ?: 'Not known' }}</span><span style="white-space:nowrap"><strong>{{ $this->num($row['value']) }}</strong> <span style="{{ $muted }}">{{ round($row['value'] / $total * 100) }}%</span></span></div>
                                    <div style="height:.3rem;border-radius:1rem;background:rgba(128,128,128,.2);margin-top:.2rem"><div style="height:100%;width:{{ round($row['value'] / $total * 100) }}%;border-radius:1rem;background:var(--primary-600, #d97706)"></div></div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <p style="{{ $muted }};max-width:56rem">Numbers come from Google Analytics and can differ a little from other tools. Visitors who say "Only what is needed" in the cookie notice are not counted, and Google may need a few hours to show the newest visits. Today's numbers are not final until tomorrow.</p>
    @endif
</x-filament::page>
