@extends('layouts.site')

@section('title', __('account.scores_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap">
    @include('account._nav', ['active' => 'scores', 'title' => __('account.scores_title')])

    <p>{{ __('account.scores_intro') }}</p>

    @if ($history->isEmpty())
        <p class="empty">{{ __('account.scores_none') }}</p>
        <p><a class="btn btn--small" href="{{ route('challenge') }}">{{ __('account.scores_take') }}</a></p>
    @else
        <section class="account-card">
            <h2>{{ __('account.scores_best') }}</h2>
            <div class="table-wrap">
                <table class="scores" data-scores-best>
                    <thead><tr><th>{{ __('account.scores_test') }}</th><th>{{ __('account.scores_score') }}</th><th>{{ __('account.scores_percent') }}</th><th>{{ __('account.scores_when') }}</th></tr></thead>
                    <tbody>
                        @foreach ($best as $quiz => $attempt)
                            <tr><td>{{ \App\Support\ChallengeScores::label($quiz) }}</td><td>{{ $attempt->score }} / {{ $attempt->total }}</td><td>{{ \App\Support\ChallengeScores::percent($attempt) }}%</td><td>{{ $attempt->created_at?->format('j M Y') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="account-card">
            <h2>{{ __('account.scores_history') }}</h2>
            <div class="table-wrap">
                <table class="scores" data-scores-history>
                    <thead><tr><th>{{ __('account.scores_when') }}</th><th>{{ __('account.scores_test') }}</th><th>{{ __('account.scores_score') }}</th><th>{{ __('account.scores_percent') }}</th></tr></thead>
                    <tbody>
                        @foreach ($history as $attempt)
                            <tr><td>{{ $attempt->created_at?->format('j M Y, H:i') }}</td><td>{{ \App\Support\ChallengeScores::label($attempt->quiz) }}</td><td>{{ $attempt->score }} / {{ $attempt->total }}</td><td>{{ \App\Support\ChallengeScores::percent($attempt) }}%</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
