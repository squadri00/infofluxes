{{-- The hero banner: words on the left (a different set on every page load), a picture slider on the right. --}}
@php
    $message = $hero['message'];
    $slides = $hero['slides'];
    $total = $slides->count();
    $full = ($hero['layout'] ?? 'split') === 'full' && $total > 0;
    $ratios = $full ? $slides->map(fn ($sl) => \App\Support\Hero::ratio($sl->image)) : collect();
@endphp
<section class="{{ implode(' ', array_filter(['hero', ! $message ? 'hero--media-only' : null, ($hero['layout'] ?? 'split') === 'full' && $slides->isNotEmpty() ? 'hero--full' : null, $slides->isEmpty() ? 'hero--text-only' : null])) }}" aria-label="{{ \App\Models\Setting::get('site_name', config('app.name')) }}">
    @if ($message)
        <div class="hero__text">
            <h1 class="hero__title">{{ $message->title }}</h1>
            @if (filled($message->subtitle))
                <p class="hero__subtitle">{{ $message->subtitle }}</p>
            @endif
            @if (filled($message->description))
                <p class="hero__description">{{ $message->description }}</p>
            @endif
        </div>
    @else
        <h1 class="visually-hidden">{{ \App\Models\Setting::get('site_name', config('app.name')) }}</h1>
    @endif

    @if ($total > 0)
        <div class="hero__media" data-hero @if ($full) style="aspect-ratio: {{ $ratios->first() }}" @endif data-hero-seconds="{{ $total > 1 ? $hero['seconds'] : 0 }}"
            role="region" aria-roledescription="carousel" aria-label="{{ __('site.hero_region') }}"
            data-label-prev="{{ __('site.hero_prev') }}" data-label-next="{{ __('site.hero_next') }}"
            data-label-goto="{{ __('site.hero_goto') }}" data-label-pause="{{ __('site.hero_pause') }}" data-label-play="{{ __('site.hero_play') }}">
            <div class="hero__slides">
                @foreach ($slides as $i => $slide)
                    @php $link = \App\Support\Hero::link($slide->button_url); @endphp
                    <figure class="hero__slide {{ $i === 0 ? 'is-active' : '' }}" data-hero-slide @if ($full) data-ratio="{{ $ratios[$i] }}" @endif role="group" aria-roledescription="slide"
                        aria-label="{{ str_replace([':n', ':total'], [$i + 1, $total], __('site.hero_slide_of')) }}"
                        @if ($i > 0) aria-hidden="true" @endif>
                        <span class="hero__backdrop" style="background-image: url('{{ asset('storage/'.$slide->image) }}')" aria-hidden="true"></span>
                        <img src="{{ asset('storage/'.$slide->image) }}" alt="{{ $slide->image_alt }}" @if ($i > 0) loading="lazy" @endif>
                        @if (filled($slide->heading) || filled($slide->text) || ($link && filled($slide->button_label)))
                            <figcaption class="hero__caption">
                                @if (filled($slide->heading))<h2 class="hero__heading">{{ $slide->heading }}</h2>@endif
                                @if (filled($slide->text))<p class="hero__text-over">{{ $slide->text }}</p>@endif
                                @if ($link && filled($slide->button_label))
                                    <a class="hero__button" href="{{ $link }}" @if ($slide->new_tab) target="_blank" rel="noopener noreferrer" @endif @if ($i > 0) tabindex="-1" @endif>{{ $slide->button_label }}</a>
                                @endif
                            </figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
            @if ($total > 1)
                {{-- Switched on by hero.js. Without it the first picture simply stays. --}}
                <div class="hero__controls" data-hero-controls hidden></div>
            @endif
        </div>
    @endif
</section>
