{{-- Visual Stories as a gallery of picture cards. Needs $stories (a list of stories). --}}
<ul class="scards">
    @foreach ($stories as $story)
        @include('partials.story-card', ['story' => $story])
    @endforeach
</ul>
