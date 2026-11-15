{{-- The small row under a card: likes, dislikes and views. Shown only when the counts were loaded (home page) and the switch is on. --}}
@php $attrs = $item->getAttributes(); @endphp
@if (array_key_exists('likes_count', $attrs) && \App\Models\Setting::get('card_stats_on', true))
    <p class="cstats" aria-label="{{ __('site.stats_label') }}">
        <span class="cstats__i" title="{{ __('site.stats_likes') }}"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path d="M2 21h4V9H2v12zM22 10c0-1.1-.9-2-2-2h-6.3l1-4.6v-.3c0-.4-.2-.8-.4-1.1L13.2 1 7.6 6.6C7.2 7 7 7.500 7 8v11c0 1.100.9 2 2 2h9c.8 0 1.500-.5 1.800-1.200l3-7c.1-.2.2-.5.2-.8v-2z" fill="currentColor"/></svg>{{ \App\Support\ViewCounter::short((int) $item->likes_count) }}</span>
        <span class="cstats__i" title="{{ __('site.stats_dislikes') }}"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false" style="transform:rotate(180deg)"><path d="M2 21h4V9H2v12zM22 10c0-1.1-.9-2-2-2h-6.3l1-4.6v-.3c0-.4-.2-.8-.4-1.1L13.2 1 7.6 6.6C7.2 7 7 7.500 7 8v11c0 1.100.9 2 2 2h9c.8 0 1.500-.5 1.800-1.200l3-7c.1-.2.2-.5.2-.8v-2z" fill="currentColor"/></svg>{{ \App\Support\ViewCounter::short((int) $item->dislikes_count) }}</span>
        <span class="cstats__i" title="{{ __('site.stats_views') }}"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path d="M12 5C7 5 2.700 8.100 1 12c1.700 3.900 6 7 11 7s9.300-3.100 11-7c-1.700-3.900-6-7-11-7zm0 11.500A4.500 4.500 0 1 1 12 7.500a4.500 4.500 0 0 1 0 9zm0-7.200a2.700 2.700 0 1 0 0 5.400 2.700 2.700 0 0 0 0-5.400z" fill="currentColor"/></svg>{{ \App\Support\ViewCounter::short((int) $item->views) }}</span>
    </p>
@endif
