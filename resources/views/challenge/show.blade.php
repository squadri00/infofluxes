@extends('layouts.site')

@section('title', $test['title'].' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('description', $test['text'])
@section('canonical', route('challenge.show', $test['key']))

@section('content')
<div class="wrap wrap--wide">
    <nav class="path" aria-label="Path"><ol><li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li><li><a href="{{ route('challenge') }}">{{ __('site.challenge') }}</a></li><li aria-current="page">{{ $test['title'] }}</li></ol></nav>
    <p class="tests-back"><a href="{{ route('challenge') }}">&larr; {{ \App\Support\Words::get('tests_back') }}</a></p>

    @include($test['view'])
</div>
@endsection

@push('scripts')
    @php $script = ['quran' => 'quiz', 'scripture' => 'scripture', 'facts' => 'facts', 'who' => 'who', 'order' => 'order'][$test['key']]; @endphp
    <script src="{{ asset('js/'.$script.'.js') }}?v={{ @filemtime(public_path('js/'.$script.'.js')) ?: 1 }}" defer></script>
@endpush
