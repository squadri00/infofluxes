<x-filament-panels::page>
    <p style="max-width:48rem;margin:0 0 1rem">{{ __('admin.create_intro') }}</p>

    @foreach (['article' => __('admin.create_group_article'), 'story' => __('admin.create_group_story')] as $group => $heading)
        @php $choices = collect(\App\Filament\Pages\CreateContent::options())->where('group', $group); @endphp
        @if ($choices->isNotEmpty())
            <h2 style="font-size:1.1rem;font-weight:700;margin:1.2rem 0 .6rem">{{ $heading }}</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:1rem">
                @foreach ($choices as $choice)
                    <a href="{{ $choice['url'] }}" data-create="{{ $choice['key'] }}" style="display:block;padding:1rem 1.1rem;border:1px solid rgba(128,128,128,.4);border-radius:.7rem;text-decoration:none;color:inherit">
                        <strong style="display:block;font-size:1.05rem;margin-bottom:.3rem">{{ $choice['title'] }}</strong>
                        <span style="opacity:.8">{{ $choice['help'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach
</x-filament-panels::page>
