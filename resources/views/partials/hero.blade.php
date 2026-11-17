{{-- The hero banner: words on the left (a different set on every page load), a picture slider on the right. --}}
@php
    $message = $hero['message'];
    $slides = $hero['slides'];
    $total = $slides->count();
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
        <div class="hero__media" data-hero data-hero-seconds="{{ $total > 1 ? $hero['seconds'] : 0 }}"
            role="region" aria-roledescription="carousel" aria-label="{{ __('site.hero_region') }}"
            data-label-prev="{{ __('site.hero_prev') }}" data-label-next="{{ __('site.hero_next') }}"
            data-label-goto="{{ __('site.hero_goto') }}" data-label-pause="{{ __('site.hero_pause') }}" data-label-play="{{ __('site.hero_play') }}"
            data-label-sound-on="{{ __('site.hero_sound_on') }}" data-label-sound-off="{{ __('site.hero_sound_off') }}">
            <div class="hero__slides">
                @foreach ($slides as $i => $slide)
                    @php $link = \App\Support\Hero::link($slide->button_url); @endphp
                    <figure class="hero__slide {{ $i === 0 ? 'is-active' : '' }}" data-hero-slide role="group" aria-roledescription="slide"
                        aria-label="{{ str_replace([':n', ':total'], [$i + 1, $total], __('site.hero_slide_of')) }}"
                        @if ($i > 0) aria-hidden="true" @endif>
                        @php $still = filled($slide->image) ? (($hero['layout'] ?? 'split') === 'full' ? \App\Support\Hero::bannerUrl($slide) : asset('storage/'.$slide->image)) : null; @endphp
                        @if ($slide->isVideo())
                            {{-- A clip: it plays by itself, without sound, to its end (then the next slide comes; one single slide loops), with no controls, and fills the whole banner. The words stay on top.
                                 A visitor can turn the sound on with the sound button of the banner (hero.js). --}}
                            <video class="hero__video" data-hero-video muted playsinline disablepictureinpicture disableremoteplayback aria-hidden="true" tabindex="-1"
                                preload="{{ $i === 0 ? 'auto' : 'none' }}" @if ($i === 0) autoplay @endif @if ($still) poster="{{ $still }}" @endif>
                                <source src="{{ asset('storage/'.$slide->video) }}" type="{{ $slide->videoMime() }}">
                            </video>
                        @else
                            <img src="{{ $still }}" alt="{{ $slide->image_alt }}" @if ($i > 0) loading="lazy" @endif>
                        @endif
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
            @if ($total > 1 || $slides->contains(fn ($s) => $s->isVideo()))
                {{-- Switched on by hero.js. Without it the first picture simply stays. --}}
                <div class="hero__controls" data-hero-controls hidden></div>
            @endif
        </div>
    @endif
</section>
