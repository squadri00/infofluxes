@extends('layouts.site')

@php
    $editing = $article !== null;
    $locale = config('infofluxes.default_locale');
    $titleValue = old('title', $editing ? $article->getTranslation('title', $locale) : '');
    $shortValue = old('short_detail', $editing ? $article->getTranslation('short_detail', $locale) : '');
    // The long detail is kept as paragraphs; the form shows it as plain text again.
    $longValue = old('long_detail', $editing ? trim(html_entity_decode(strip_tags(preg_replace(['~<br\s*/?>~i', '~</p>\s*<p>~i'], ["\n", "\n\n"], (string) $article->getTranslation('long_detail', $locale))))) : '');
    $wordsValue = old('search_words', $editing ? implode(', ', (array) $article->search_words) : '');
@endphp

@section('title', ($editing ? __('site.edit_article') : __('site.submit_article')).' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    <h1 class="topic__title">{{ $editing ? __('site.edit_article') : __('site.submit_article') }}</h1>
    <form class="auth-form auth-form--wide" method="post" action="{{ $editing ? route('submit.edit', $article->id) : route('submit') }}">
        @csrf
        <label class="field"><span>{{ __('site.title') }}</span><input type="text" name="title" value="{{ $titleValue }}" required maxlength="200"></label>
        @error('title')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field"><span>{{ __('site.category') }}</span>
            <select name="category_id" required>
                <option value="">{{ __('site.choose_category') }}</option>
                @foreach ($categories as $id => $name)
                    <option value="{{ $id }}" @selected((int) old('category_id', $editing ? $article->category_id : 0) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </label>
        @error('category_id')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field"><span>{{ __('site.short_detail') }}</span><textarea name="short_detail" rows="3" maxlength="600">{{ $shortValue }}</textarea></label>
        @error('short_detail')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field"><span>{{ __('site.long_detail') }}</span><textarea name="long_detail" rows="12" maxlength="30000">{{ $longValue }}</textarea></label>
        @error('long_detail')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field"><span>{{ __('site.source') }}</span><input type="text" name="source_line" value="{{ old('source_line', $editing ? $article->source_line : '') }}" maxlength="500"></label>
        <label class="field"><span>{{ __('site.search_words') }}</span><input type="text" name="search_words" value="{{ $wordsValue }}" maxlength="600"></label>
        <button class="btn btn--small" type="submit">{{ $editing ? __('site.save') : __('site.send') }}</button>
    </form>
</div>
@endsection
