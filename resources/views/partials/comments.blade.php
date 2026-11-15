{{-- The discussion under an article. $article and $comments (top-level, each with its replies) are required. --}}
@php
    $open = (bool) \App\Models\Setting::get('comments_open', true);
    $me = auth()->user();
    $bodyOf = fn ($c) => nl2br(e($c->body));
@endphp
<section class="comments" id="comments" aria-labelledby="comments-title">
    <h2 class="block__title" id="comments-title">{{ __('site.discussion') }}</h2>

    @if ($comments->isEmpty())
        <p class="empty">{{ __('site.no_comments') }}</p>
    @endif

    @foreach ($comments as $comment)
        <article class="comment" id="comment-{{ $comment->id }}">
            <header class="comment__head">
                <strong>{{ $comment->user?->name ?? '—' }}</strong>
                @if ($comment->user?->isStaff())<span class="comment__badge">{{ __('site.staff') }}</span>@endif
                <time datetime="{{ $comment->created_at->toDateString() }}">{{ $comment->created_at->format('j M Y') }}</time>
                @if ($comment->status === 'pending')<span class="comment__wait">{{ __('site.comment_waiting') }}</span>@endif
            </header>
            <div class="comment__body">{!! $bodyOf($comment) !!}</div>

            @foreach ($comment->replies as $reply)
                <article class="comment comment--reply" id="comment-{{ $reply->id }}">
                    <header class="comment__head">
                        <strong>{{ $reply->user?->name ?? '—' }}</strong>
                        @if ($reply->user?->isStaff())<span class="comment__badge">{{ __('site.staff') }}</span>@endif
                        <time datetime="{{ $reply->created_at->toDateString() }}">{{ $reply->created_at->format('j M Y') }}</time>
                        @if ($reply->status === 'pending')<span class="comment__wait">{{ __('site.comment_waiting') }}</span>@endif
                    </header>
                    <div class="comment__body">{!! $bodyOf($reply) !!}</div>
                </article>
            @endforeach

            @if ($open && $me && $comment->status === 'approved')
                <details class="comment__reply">
                    <summary>{{ __('site.reply') }}</summary>
                    <form method="post" action="{{ route('comments.store') }}" class="auth-form">
                        @csrf
                        <input type="hidden" name="article_id" value="{{ $article->id }}">
                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                        <label class="field"><span class="visually-hidden">{{ __('site.reply') }}</span><textarea name="body" rows="3" maxlength="2000" required></textarea></label>
                        <button class="btn btn--small" type="submit">{{ __('site.post_comment') }}</button>
                    </form>
                </details>
            @endif
        </article>
    @endforeach

    @if (! $open)
        <p class="empty">{{ __('site.comments_closed') }}</p>
    @elseif (! $me)
        <p><a href="{{ route('login') }}">{{ __('site.comment_login') }}</a></p>
    @elseif (! $me->hasVerifiedEmail())
        <p><a href="{{ route('verification.notice') }}">{{ __('site.verify_needed') }}</a></p>
    @else
        <form method="post" action="{{ route('comments.store') }}" class="auth-form auth-form--wide">
            @csrf
            <input type="hidden" name="article_id" value="{{ $article->id }}">
            <label class="field"><span>{{ __('site.write_comment') }}</span><textarea name="body" rows="4" maxlength="2000" required>{{ old('body') }}</textarea></label>
            @error('body')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            <button class="btn btn--small" type="submit">{{ __('site.post_comment') }}</button>
        </form>
    @endif
</section>
