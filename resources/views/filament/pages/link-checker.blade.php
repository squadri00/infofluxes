<x-filament-panels::page>
    @php $c = $this->counts(); @endphp

    <div @if ($sweeping) wire:poll.2s="step" @endif style="display:grid;gap:1rem">
        <p style="max-width:52rem">
            Every source link of every article is checked again about once a week, a few at a time, by itself.
            A <strong>broken</strong> link never hides an article. It only shows here, so a person can find a new link.
            Some websites (for example Sunnah.com) refuse automatic checks. They show as <strong>blocked</strong>: open the link yourself, and if it is fine, press "I opened it: it is fine".
        </p>

        <div style="display:flex;flex-wrap:wrap;gap:.8rem;align-items:center">
            <x-filament::button wire:click="startSweep" wire:loading.attr="disabled" wire:target="startSweep" :disabled="$sweeping">Check all links now</x-filament::button>
            <span style="opacity:.85">
                {{ $c['total'] }} links &middot;
                <strong style="color:#2d9d5a">{{ $c['ok'] }} open</strong> &middot;
                <strong style="color:#d92d20">{{ $c['broken'] }} broken</strong> &middot;
                <strong style="color:#b07800">{{ $c['blocked'] }} blocked</strong> &middot;
                {{ $c['new'] }} not checked yet
            </span>
        </div>

        @if ($sweeping)
            <div style="max-width:40rem">
                <div style="height:.7rem;border-radius:999px;background:rgba(128,128,128,.25);overflow:hidden">
                    <div style="height:100%;width:{{ $this->progressPercent() }}%;background:#d97706;transition:width .4s"></div>
                </div>
                <div style="margin-top:.3rem;opacity:.85">{{ $this->progressText() }} Please keep this page open.</div>
            </div>
        @endif
    </div>

    {{ $this->table }}
</x-filament-panels::page>
