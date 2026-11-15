@php
    use App\Support\History\Present;
    use App\Support\History\Undo;
    $subject = $a->subject();
    $changed = $a->event === 'updated' ? $a->changedFields() : [];
    $undo = (array) ($a->custom_data ?? []);
@endphp
<style>
    .hist del { background: rgba(217, 45, 32, .22); color: inherit; text-decoration: line-through; }
    .hist ins { background: rgba(45, 157, 90, .25); color: inherit; text-decoration: none; }
    .hist__box { padding: .6rem .8rem; border: 1px solid rgba(128, 128, 128, .35); border-radius: .5rem; white-space: pre-wrap; word-break: break-word; max-height: 24rem; overflow-y: auto; }
</style>
<div class="hist" style="display:grid;gap:1rem">
    <div style="opacity:.85">
        <strong>{{ $a->who() }}</strong> &middot; {{ $a->created_at?->format('j M Y, H:i') }} &middot; {{ $a->subjectName() }}
        @if ($a->source === 'command') &middot; from a command @endif
        @if (! $subject && $a->event !== 'purged') &middot; <em>the record no longer exists</em> @endif
        @if (! empty($undo['undo_of'])) <br>This change put the old words back from history line #{{ $undo['undo_of'] }}: {{ implode(', ', array_map(fn ($f) => Present::fieldName($f), (array) ($undo['fields'] ?? []))) }}. @endif
    </div>

    @if ($a->event === 'updated')
        @foreach ($changed as $field)
            <div>
                <h3 style="font-weight:700;margin-bottom:.4rem">{{ Present::fieldName($field) }}
                    @if (isset(Undo::fields($a)[$field]) && Undo::changedSince($a, $field))
                        <span style="font-weight:400;opacity:.75;font-size:.85rem">(changed again after this line)</span>
                    @endif
                </h3>
                @foreach (Present::comparison($a, $field) as $part)
                    @if ($part['label'] !== '')<div style="opacity:.75;font-size:.85rem;margin:.3rem 0">Language: {{ $part['label'] }}</div>@endif
                    @if ($part['diff'] !== null)
                        <div class="hist__box">{!! $part['diff'] !!}</div>
                        <div style="opacity:.7;font-size:.8rem;margin-top:.2rem"><del>removed</del> &nbsp; <ins>added</ins></div>
                    @else
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
                            <div><div style="opacity:.75;font-size:.85rem">Before</div><div class="hist__box">{{ $part['old'] }}</div></div>
                            <div><div style="opacity:.75;font-size:.85rem">After</div><div class="hist__box">{{ $part['new'] }}</div></div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    @elseif ($a->event === 'created')
        <p>This record was made: <strong>{{ $a->subject_label }}</strong>.</p>
    @elseif ($a->event === 'deleted')
        <p>This record was moved to the Trash. It can be brought back from the Trash of its list.</p>
    @elseif ($a->event === 'restored')
        <p>This record was brought back from the Trash.</p>
    @else
        <p>This record was removed for good.</p>
    @endif
</div>
