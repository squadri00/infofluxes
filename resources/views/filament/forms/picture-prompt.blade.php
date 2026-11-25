@php $full = \App\Support\PicturePrompt::full((string) ($scene ?? '')); @endphp
<div x-data="{ done: false }" style="display:grid;gap:.4rem">
    <strong style="font-size:.9rem">The whole prompt for the image maker</strong>
    <textarea x-ref="prompt" readonly rows="8" style="width:100%;padding:.5rem .7rem;border:1px solid rgba(128,128,128,.45);border-radius:.4rem;background:transparent;color:inherit;font-size:.85rem;line-height:1.45">{{ $full }}</textarea>
    <div style="display:flex;gap:.7rem;align-items:center;flex-wrap:wrap">
        <button type="button" x-on:click="navigator.clipboard.writeText($refs.prompt.value); done = true; setTimeout(() => done = false, 1800)" style="padding:.35rem .9rem;border:1px solid rgba(128,128,128,.6);border-radius:.4rem;font-weight:600;cursor:pointer">
            <span x-show="! done">Copy the prompt</span><span x-show="done" x-cloak>Copied</span>
        </button>
        <span style="opacity:.7;font-size:.82rem">The scene above, then the look of the pictures and the picture rules (4:3, prophets only as light, the faces of the companions as light). Change the scene above if you like; save to keep it.</span>
    </div>
</div>
