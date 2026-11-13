{{-- One Did You Know? fact in the side tree. Needs $fact and $currentFact (the fact being read, or null). --}}
@php $isCurrent = $currentFact && (int) $fact->id === (int) $currentFact->id; @endphp
<li class="side-tree__item{{ $isCurrent ? ' is-current' : '' }}">
    <a href="{{ $fact->url() }}"{!! $isCurrent ? ' aria-current="page"' : '' !!}>{{ $fact->title }}</a>
</li>
