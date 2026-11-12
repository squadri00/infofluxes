{{-- The slide viewer of an article that has story slides. Needs $article and $items (see App\Support\StoryItems).
     It sits inside an element with data-story. The slides are all in the page, one after the other; story.js shows one at a time. --}}
@php
    $total = count($items);
    $chapters = $article->chapters->filter(fn ($c) => $c->slides->isNotEmpty())->values();
    $protect = (string) \App\Models\Setting::get('story_protect_images', '1') === '1';
    $logoPath = (string) \App\Models\Setting::get('story_watermark_on', '1') === '1' ? \App\Models\Setting::get('logo') : null;
    $markName = $logoPath !== null || (string) \App\Models\Setting::get('story_watermark_on', '1') === '1'
        ? (string) \App\Models\Setting::get('site_name', config('app.name')) : null;
    if ((string) \App\Models\Setting::get('story_watermark_name', '1') !== '1') {
        $markName = null;
    }
    $defaultCredit = (string) \App\Models\Setting::get('story_default_credit', 'AI Generated');
    $coverImage = filled($article->main_image) ? [
        'url' => asset('storage/'.$article->main_image), 'alt' => $article->main_image_alt, 'credit' => $article->main_image_credit,
    ] : null;
@endphp
<div class="{{ \App\Support\ViewerStyle::classes() }}" style="{{ \App\Support\ViewerStyle::style() }}" {!! \App\Support\ViewerStyle::data() !!} data-viewer>
    @if ($total > 0)
        <p class="story__counter" aria-live="polite" aria-atomic="true"><span data-story-current>1</span> / <span>{{ $total }}</span></p>
    @endif

    @if ($chapters->count() > 0)
        {{-- The chapter buttons are switched on by story.js. Without it, the slides simply follow each other. --}}
        @php $chapterMenu = \App\Support\ViewerStyle::chaptersAsMenu($chapters); @endphp
        <nav class="story__chapters {{ $chapterMenu ? 'story__chapters--menu' : '' }}" data-story-chapters aria-label="{{ __('site.story_chapters') }}" hidden>
            @if (($items[0]['type'] ?? null) === 'cover')
                <button type="button" class="story__chapter story__home" data-story-home>&#8962; {{ __('site.story_home') }}</button>
            @endif
            @if ($chapterMenu)
                <div class="story__menu" data-story-menu>
                    <button type="button" class="story__chapter story__menu-btn" data-story-menu-btn aria-haspopup="true" aria-expanded="false" data-default="{{ __('site.story_chapters_menu') }}">
                        <span data-story-menu-label>{{ __('site.story_chapters_menu') }}</span> <span aria-hidden="true">&#9662;</span>
                    </button>
                    <ul class="story__menu-list" data-story-menu-list hidden>
                        @foreach ($article->chapters as $c => $chapter)
                            @continue($chapter->slides->isEmpty())
                            <li><button type="button" class="story__menu-item" data-chapter="{{ $c }}">{{ $chapter->title }}</button></li>
                        @endforeach
                    </ul>
                </div>
            @else
                @foreach ($article->chapters as $c => $chapter)
                    @continue($chapter->slides->isEmpty())
                    <button type="button" class="story__chapter" data-chapter="{{ $c }}">{{ $chapter->title }}</button>
                @endforeach
            @endif
        </nav>
    @endif

@if ($total === 0)
    <p class="empty">{{ __('site.story_empty') }}</p>
@else
    <div class="story__stage-wrap">
        <button type="button" class="story__arrow story__arrow--prev" data-story-arrow-prev aria-label="{{ __('site.story_prev') }}" hidden>
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
        </button>
    <div class="story__stage" data-story-stage>
        <div class="story__track" data-story-track>
        @foreach ($items as $item)
            @php
                $slide = $item['type'] === 'slide' ? $item['slide'] : null;
                $isCover = $item['type'] === 'cover';
                $pic = null;
                if ($slide && $slide->hasImage()) {
                    $pic = ['url' => $slide->imageUrl(), 'alt' => $slide->image_alt, 'credit' => $slide->image_credit];
                } elseif ($isCover && $coverImage) {
                    $pic = $coverImage;
                }
            @endphp
            <section class="slide {{ $pic ? '' : 'slide--text' }}" id="slide-{{ $item['n'] }}" data-slide="{{ $item['n'] }}" data-chapter="{{ $item['chapter'] }}"
                aria-roledescription="slide" aria-label="{{ str_replace([':n', ':total'], [$item['n'], $total], __('site.story_slide_label')) }}">
                @if ($pic)
                    <figure class="slide__media {{ $protect ? 'is-protected' : '' }}">
                        <img src="{{ $pic['url'] }}" alt="{{ $pic['alt'] }}" width="1200" height="{{ \App\Support\StoryImage::heightFor(1200) }}" loading="{{ $item['n'] <= 2 ? 'eager' : 'lazy' }}" decoding="async" draggable="false">
                        @if ($protect)<span class="slide__shield" aria-hidden="true"></span>@endif
                        @if ($logoPath || $markName)
                            <span class="slide__mark" aria-hidden="true">
                                @if ($logoPath)<img src="{{ asset('storage/'.$logoPath) }}" alt="" draggable="false">@endif
                                @if ($markName)<span class="slide__mark-name">{{ $markName }}</span>@endif
                            </span>
                        @endif
                        <figcaption class="slide__caption">
                            @if (filled($pic['alt']))<span class="slide__caption-text">{{ $pic['alt'] }}</span>@endif
                            <span class="slide__caption-credit">{{ __('site.story_picture_credit') }} {{ $pic['credit'] ?: $defaultCredit }}</span>
                        </figcaption>
                    </figure>
                @endif

                <div class="slide__text">
                    @if ($isCover)
                        <h2 class="slide__heading slide__heading--cover">{{ $article->title }}</h2>
                        @if (filled($article->short_detail))<p class="slide__lead">{{ trim(strip_tags((string) $article->short_detail)) }}</p>@endif
                        @if (count($item['index']) > 0 && \App\Support\ViewerStyle::coverContents())
                            <h3 class="slide__index-title">{{ __('site.story_contents') }}</h3>
                            <ol class="slide__index">
                                @foreach ($item['index'] as $entry)
                                    <li>
                                        <a href="#slide-{{ $entry['n'] }}" data-story-goto="{{ $entry['n'] }}">{{ $entry['title'] }}</a>
                                        @if (count($entry['slides'] ?? []) > 0)
                                            <ul class="slide__index-sub">
                                                @foreach ($entry['slides'] as $sub)
                                                    <li><a href="#slide-{{ $sub['n'] }}" data-story-goto="{{ $sub['n'] }}">{{ $sub['heading'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    @else
                        @if (filled($item['chapterTitle'] ?? null))<p class="slide__chapter">{{ $item['chapterTitle'] }}</p>@endif
                        <h2 class="slide__heading">{{ $slide->heading }}</h2>
                        <div class="slide__body">{!! \App\Support\Html::fromPlain($slide->body) !!}</div>
                    @endif
                </div>
            </section>
        @endforeach
        </div>
    </div>
        <button type="button" class="story__arrow story__arrow--next" data-story-arrow-next aria-label="{{ __('site.story_next') }}" hidden>
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    {{-- The dots are made by story.js. --}}
    <div class="story__dots" data-story-dots role="group" aria-label="{{ __('site.story_dots') }}"></div>
@endif
</div>
