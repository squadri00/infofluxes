{{-- Articles laid out as a mosaic, 4 columns wide. The shapes repeat in two blocks:
     block 1 (6 articles): small, tall, tall, small, small, small
     block 2 (4 articles): wide feature, small, tall, small
     With fewer than 4 articles, every article is a tall card. Articles left over after the last whole block are small cards.
     $card names another card partial (the Did You Know? facts use their own); the usual one is the article card. --}}
@php
    $items = $articles->values();
    $total = $items->count();
    $blocks = [['c', 'P', 'P', 'c', 'c', 'c'], ['F', 'c', 'P', 'c']];
    $shapes = [];
    if ($total < 4) {
        $shapes = array_fill(0, $total, 'P');
    } else {
        for ($b = 0; $total - count($shapes) >= count($blocks[$b % 2]); $b++) {
            $shapes = array_merge($shapes, $blocks[$b % 2]);
        }
        $shapes = array_merge($shapes, array_fill(0, $total - count($shapes), 'c'));
    }
    $cardView = $card ?? 'partials.article-card';
@endphp
<ul class="mosaic">
    @foreach ($items as $i => $article)
        @include($cardView, ['article' => $article, 'shape' => $shapes[$i]])
    @endforeach
</ul>
