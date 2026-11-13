<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $story->title }}</title>
<style>
    @if ($font)
    @font-face { font-family: 'SalutationFont'; src: url('{{ $font }}') format('truetype'); }
    @endif
    @page { margin: 0; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #1e2733; font-size: 11pt; line-height: 1.55; }
    .page { width: 742pt; height: 505pt; padding: 40pt 50pt; page-break-after: always; position: relative; overflow: hidden; }
    .page:last-child { page-break-after: auto; }
    .cover, .opener { width: 842pt; height: 595pt; padding: 0; }
    .cover { background: #14213d; color: #fff; }
    .cover table { width: 842pt; height: 595pt; border-collapse: collapse; }
    .cover td { vertical-align: middle; }
    .cover__text { padding: 0 50pt 0 46pt; width: 46%; }
    .cover__pic { width: 54%; padding: 0; }
    .cover__pic img { width: 100%; display: block; }
    .cover__brand { font-size: 10pt; letter-spacing: 3pt; text-transform: uppercase; color: #8ecae6; margin-bottom: 26pt; }
    .cover__brand img { height: 26pt; vertical-align: middle; margin-right: 8pt; }
    .cover h1 { font-size: 30pt; line-height: 1.15; margin: 0 0 14pt; color: #fff; }
    .cover .period { color: #8ecae6; font-size: 11pt; margin: 0 0 14pt; }
    .cover .lead { font-size: 12.5pt; line-height: 1.55; color: #e5ecf4; margin: 0; }
    .cover__credit { position: absolute; right: 12pt; bottom: 8pt; font-size: 7.5pt; color: #fff; background: #14213d; padding: 2pt 6pt; }
    .contents h2 { font-size: 24pt; color: #14213d; margin: 0 0 6pt; }
    .rule { height: 3pt; width: 60pt; background: #2e86ab; margin: 0 0 22pt; }
    .contents ol { margin: 0; padding: 0; list-style: none; }
    .contents li.ch { margin: 0 0 12pt; }
    .contents .ch__title { font-size: 14pt; font-weight: bold; color: #14213d; }
    .contents .ch__no { color: #2e86ab; margin-right: 6pt; }
    .contents ul { margin: 3pt 0 0 24pt; padding: 0; color: #4a5565; font-size: 10pt; }
    .opener { background: #14213d; color: #fff; text-align: left; }
    .opener .in { padding: 150pt 50pt 0; }
    .opener .no { font-size: 70pt; color: #2e86ab; line-height: 1; margin: 0 0 6pt; }
    .opener h2 { font-size: 30pt; color: #fff; margin: 0; }
    .opener .small { color: #8ecae6; margin-top: 10pt; font-size: 11pt; }
    .slide-head { font-size: 8.5pt; letter-spacing: 1.5pt; text-transform: uppercase; color: #2e86ab; margin: 0 0 14pt; }
    .slide table { width: 100%; border-collapse: collapse; }
    .slide td { vertical-align: top; }
    .slide__pic { width: 52%; padding-right: 30pt; }
    .slide__pic img { width: 100%; display: block; }
    .slide__cap { font-size: 8.5pt; color: #4a5565; margin-top: 5pt; line-height: 1.4; }
    .slide__cap b { color: #1e2733; }
    .slide h3 { font-size: 20pt; line-height: 1.2; margin: 0 0 12pt; color: #14213d; }
    .slide__body p { margin: 0 0 9pt; }
    .slide__body ul, .slide__body ol { margin: 0 0 9pt 18pt; padding: 0; }
    .slide__src { margin-top: 14pt; padding-top: 7pt; border-top: 0.6pt solid #cfd6df; font-size: 8pt; color: #5b6676; line-height: 1.4; }
    .foot { position: absolute; left: 50pt; bottom: 20pt; font-size: 8pt; color: #7a8494; }
    .opener .foot, .cover .foot { display: none; }
    .sal { font-family: 'SalutationFont'; }
    /* the picture fills the whole A4 page, the words are on a panel over it */
    .page.is-full { width: 842pt; height: 595pt; padding: 0; background: #14213d; }
    .bleed { position: absolute; left: 0; top: 0; width: 842pt; height: 595pt; }
    .full__panel { position: absolute; right: 0; top: 0; width: 318pt; height: 595pt; padding: 34pt 26pt 30pt 28pt; background: rgba(20, 33, 61, 0.88); color: #f0f4fa; overflow: hidden; }
    .full__panel .slide-head { color: #8ecae6; margin-bottom: 10pt; }
    .full__panel h3 { color: #ffffff; font-size: 19pt; margin-bottom: 10pt; }
    .full__panel .slide__body p { margin: 0 0 7pt; }
    .full__panel .slide__src { border-top: 0.6pt solid #5b6b85; color: #c9d3e3; margin-top: 10pt; }
    .full__cap { position: absolute; left: 0; bottom: 0; width: 524pt; padding: 22pt 18pt 12pt; font-size: 8.5pt; line-height: 1.4; color: #ffffff; background: rgba(0, 0, 0, 0.45); }
    .cover__panel { position: absolute; left: 0; bottom: 0; width: 742pt; padding: 34pt 50pt 40pt; background: rgba(20, 33, 61, 0.86); color: #ffffff; }
    .cover__panel h1 { font-size: 30pt; margin: 0 0 8pt; color: #ffffff; line-height: 1.15; }
    .cover__panel .lead { max-width: 640pt; }
    .end { text-align: center; }
    .end .box { margin-top: 150pt; }
    .end h2 { font-size: 22pt; color: #14213d; margin: 0 0 8pt; }
    .end p { margin: 0 0 6pt; color: #4a5565; }
</style>
</head>
<body>

{{-- The cover --}}
@php
    $full = ($layout ?? 'full') === 'full';
    $coverFull = $full && $coverPath ? \App\Support\StoryBook::data($coverPath, [842, 595], 1500) : null;
@endphp
@if ($coverFull)
<div class="page cover is-full">
    <img class="bleed" src="{{ $coverFull }}" alt="">
    <div class="cover__panel">
        <div class="cover__brand" style="margin-bottom: 12pt">@if ($logo)<img src="{{ $logo }}" alt="">@endif {{ $siteName }}</div>
        <h1>{!! \App\Support\StoryBook::t($story->title) !!}</h1>
        @if ($story->short_detail)<p class="lead">{!! \App\Support\StoryBook::t($story->short_detail) !!}</p>@endif
    </div>
    <div class="cover__credit">{{ __('site.story_picture_credit') }} {{ $story->main_image_credit ?: $defaultCredit }}</div>
</div>
@else
<div class="page cover">
    <table>
        <tr>
            <td class="cover__text">
                <div class="cover__brand">@if ($logo)<img src="{{ $logo }}" alt="">@endif {{ $siteName }}</div>
                <h1>{!! \App\Support\StoryBook::t($story->title) !!}</h1>
                @if ($story->short_detail)<p class="lead">{!! \App\Support\StoryBook::t($story->short_detail) !!}</p>@endif
            </td>
            @if ($cover)
                <td class="cover__pic" valign="middle"><img src="{{ $cover }}" alt=""></td>
            @endif
        </tr>
    </table>
    @if ($cover)<div class="cover__credit">{{ __('site.story_picture_credit') }} {{ $story->main_image_credit ?: $defaultCredit }}</div>@endif
</div>
@endif

{{-- The contents --}}
<div class="page contents">
    <h2>{{ __('site.story_book_contents') }}</h2>
    <div class="rule"></div>
    <ol>
        @foreach ($chapters as $c => $chapter)
            <li class="ch">
                <div class="ch__title"><span class="ch__no">{{ $c + 1 }}.</span>{!! \App\Support\StoryBook::t($chapter->title) !!}</div>
                <ul>
                    @foreach ($chapter->slides as $slide)
                        <li>{!! \App\Support\StoryBook::t($slide->heading) !!}</li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ol>
</div>

{{-- A page for each chapter, then a page for each slide --}}
@foreach ($chapters as $c => $chapter)
    <div class="page opener"><div class="in">
        <div class="no">{{ $c + 1 }}</div>
        <h2>{!! \App\Support\StoryBook::t($chapter->title) !!}</h2>
        <div class="small">{!! \App\Support\StoryBook::t($story->title) !!}</div>
    </div></div>

    @foreach ($chapter->slides as $slide)
        @php
            $picFull = $full ? \App\Support\StoryBook::data($slide->image, [842, 595], 1500) : null;
            $pic = $picFull ? null : \App\Support\StoryBook::data($slide->image);
            $words = str_word_count(strip_tags((string) $slide->body));
            $size = $words > 170 ? '8.6pt' : ($words > 120 ? '9.6pt' : ($words > 80 ? '10.4pt' : '11pt'));
        @endphp
        @if ($picFull)
        <div class="page slide is-full">
            <img class="bleed" src="{{ $picFull }}" alt="">
            <div class="full__cap">
                @if ($slide->image_alt)<b>{!! \App\Support\StoryBook::t($slide->image_alt) !!}</b><br>@endif
                {{ __('site.story_picture_credit') }} {{ $slide->image_credit ?: $defaultCredit }}@if ($slide->image_licence) · {{ $slide->image_licence }}@endif
            </div>
            <div class="full__panel" style="font-size: {{ $size }}; line-height: 1.45">
                <p class="slide-head">{{ __('site.story_book_chapter') }} {{ $c + 1 }} · {!! \App\Support\StoryBook::t($chapter->title) !!}</p>
                <h3>{!! \App\Support\StoryBook::t($slide->heading) !!}</h3>
                <div class="slide__body">{!! \App\Support\StoryBook::body($slide->body) !!}</div>
            </div>
        </div>
        @else
        <div class="page slide">
            <p class="slide-head">{{ __('site.story_book_chapter') }} {{ $c + 1 }} · {!! \App\Support\StoryBook::t($chapter->title) !!}</p>
            <table>
                <tr>
                    @if ($pic)
                        <td class="slide__pic">
                            <img src="{{ $pic }}" alt="">
                            <div class="slide__cap">
                                @if ($slide->image_alt)<b>{!! \App\Support\StoryBook::t($slide->image_alt) !!}</b><br>@endif
                                {{ __('site.story_picture_credit') }} {{ $slide->image_credit ?: $defaultCredit }}@if ($slide->image_licence) · {{ $slide->image_licence }}@endif
                            </div>
                        </td>
                    @endif
                    <td>
                        <h3>{!! \App\Support\StoryBook::t($slide->heading) !!}</h3>
                        <div class="slide__body">{!! \App\Support\StoryBook::body($slide->body) !!}</div>
                    </td>
                </tr>
            </table>
            <div class="foot">{!! \App\Support\StoryBook::t($story->title) !!} · {{ $siteName }}</div>
        </div>
        @endif
    @endforeach
@endforeach

{{-- The last page --}}
<div class="page end">
    <div class="box">
        <h2>{{ $siteName }}</h2>
        <p>{{ \App\Models\Setting::get('tagline', __('site.tagline')) }}</p>
        <p>{{ $siteUrl }}</p>
        <p>{{ __('site.story_book_made') }} {{ now()->format('j F Y') }}</p>
    </div>
</div>

</body>
</html>
