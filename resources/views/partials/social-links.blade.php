{{-- The site's social buttons. Each address is a setting; a network with no address shows nothing. --}}
@php
    $networks = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'];
    $links = [];
    foreach ($networks as $key => $label) {
        $url = (string) \App\Models\Setting::get('social_'.$key);
        if (preg_match('~^https?://~i', $url)) {
            $links[$key] = [$label, $url];
        }
    }
@endphp
@if ($links)
    <ul class="social {{ $class ?? '' }}" aria-label="Social networks">
        @foreach ($links as $key => [$label, $url])
            <li><a class="social__btn social__btn--{{ $key }}" href="{{ $url }}" target="_blank" rel="noopener noreferrer me" aria-label="{{ $label }}" title="{{ $label }}"><span aria-hidden="true">{{ mb_strtoupper(mb_substr($label, 0, 1)) }}</span></a></li>
        @endforeach
    </ul>
@endif
