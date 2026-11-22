@php $choices = \App\Filament\Pages\CreateContent::options(); @endphp
@if ($choices !== [])
    <div x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false" data-create-menu style="position:relative;display:inline-block">
        <button type="button" x-on:click="open = ! open" aria-haspopup="true" x-bind:aria-expanded="open" style="display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .9rem;border:0;border-radius:999px;font-weight:700;font-size:.9rem;cursor:pointer;background:var(--primary-600, #d97706);color:#fff">+ {{ __('admin.create') }} &#9662;</button>
        <div x-show="open" x-cloak x-transition style="position:absolute;right:0;top:calc(100% + .4rem);z-index:60;min-width:17rem;padding:.35rem;border:1px solid rgba(128,128,128,.4);border-radius:.6rem;background:var(--gray-900, #18181b);box-shadow:0 8px 24px rgba(0,0,0,.35)">
            @foreach ($choices as $choice)
                <a href="{{ $choice['url'] }}" style="display:block;padding:.5rem .7rem;border-radius:.4rem;text-decoration:none;color:#f4f4f5;font-size:.92rem" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">{{ $choice['title'] }}</a>
            @endforeach
        </div>
    </div>
@endif
