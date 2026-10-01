@extends('admin.layout')

@section('title', 'Completed & Scores')
@section('subtitle', 'Review completed matches and submitted player scores.')

@section('content')
<div class="stats">
    <div class="stat">
        <div class="label">Completed matches</div>
        <div class="value">{{ $completedMatches }}</div>
        <div class="note">All completed matches.</div>
    </div>
    <div class="stat">
        <div class="label">Scores submitted</div>
        <div class="value">{{ $scoredMatches }}</div>
        <div class="note">Completed matches with score records.</div>
    </div>
    <div class="stat">
        <div class="label">Awaiting scores</div>
        <div class="value">{{ $pendingScoreMatches }}</div>
        <div class="note">Completed matches with no scores yet.</div>
    </div>
    <div class="stat">
        <div class="label">Visible results</div>
        <div class="value">{{ $matches->count() }}</div>
        <div class="note">Current filtered result set.</div>
    </div>
</div>

<div class="toolbar">
    <div>
        <div class="card-title">Completed match history</div>
        <div class="card-copy">Open a match to inspect players, completion time and submitted scores.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.matches.completed') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search completed matches">
        <select class="select" name="scores" style="max-width:165px">
            <option value="">All score states</option>
            <option value="submitted" @selected($scoreState === 'submitted')>Scores submitted</option>
            <option value="pending" @selected($scoreState === 'pending')>Awaiting scores</option>
        </select>
        <button class="btn" type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Match</th>
                    <th>Host</th>
                    <th>Players</th>
                    <th>Score state</th>
                    <th>Completed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($matches as $match)
                <tr>
                    <td>
                        <b>{{ $match->sport?->name ?? $match->custom_sport_name ?? $match->name }}</b>
                        <div style="color:#667085;font-size:10px;margin-top:3px">{{ $match->venue_name ?: $match->location_address }}</div>
                    </td>
                    <td>
                        <b>{{ $match->host->name }}</b>
                        <div style="color:#667085;font-size:10px;margin-top:3px">{{ $match->host->email }}</div>
                    </td>
                    <td>{{ $match->players_count }}/{{ $match->max_players }}</td>
                    <td>
                        @if($match->scores_count > 0)
                            <span class="badge success">Submitted · {{ $match->scores_count }} scores</span>
                        @else
                            <span class="badge warning">Awaiting scores</span>
                        @endif
                    </td>
                    <td>{{ $match->completed_at?->format('M j, Y · g:i A') ?? '—' }}</td>
                    <td><a class="btn btn-secondary" href="{{ route('admin.matches.show',$match) }}">View scores</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No completed matches found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
