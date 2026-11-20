<x-filament-panels::page>
    @php $c = $this->counts(); @endphp
    <div style="display:flex;flex-wrap:wrap;gap:.75rem 1.5rem;align-items:center">
        <span><strong>{{ number_format($c['free']) }}</strong> free</span>
        <span><strong>{{ number_format($c['mine']) }}</strong> yours</span>
        <span><strong>{{ number_format($c['taken']) }}</strong> taken by other people</span>
    </div>
    <p style="opacity:.8;max-width:50rem;margin:0">
        These are the subjects that still need an article. Press <strong>Take it</strong> on one you will write: it is then yours and the other editors no longer see it as free.
        You can also give a free topic to another person. Tick many and use the menu above the list to give or take them together.
        A topic that someone else has can only be handed back or given on by that person, a moderator, or the super user.
    </p>

    {{ $this->table }}
</x-filament-panels::page>
