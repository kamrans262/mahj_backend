@extends('admin.layout')

@section('title', 'Matches')
@section('subtitle', 'Review and manage matches created in Mahj.')

@section('content')
<div class="toolbar">
    <div>
        <div class="card-title">{{ $totalMatches }} total matches</div>
        <div class="card-copy">Search by venue, location or host and open a match to manage it.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.matches') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search matches">
        <select class="select" name="status" style="max-width:150px">
            <option value="">All statuses</option>
            @foreach(['open' => 'Open', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Match</th><th>Host</th><th>Players</th><th>Status</th><th>Starts</th><th></th></tr></thead>
            <tbody>
            @forelse($matches as $match)
                <tr>
                    <td><b>{{ $match->venue_name ?: 'Mahj Match' }}</b><div style="color:#667085;font-size:10px;margin-top:3px">{{ $match->location_address }}</div></td>
                    <td><b>{{ $match->host->name }}</b><div style="color:#667085;font-size:10px;margin-top:3px">{{ $match->host->email }}</div></td>
                    <td>{{ $match->players_count }}/{{ $match->max_players }}</td>
                    <td><span class="badge {{ $match->status === 'open' ? 'success' : ($match->status === 'cancelled' ? 'danger' : 'orange') }}">{{ ucfirst($match->status) }}</span></td>
                    <td>{{ $match->starts_at->format('M j, Y · g:i A') }}</td>
                    <td><a class="btn btn-secondary" href="{{ route('admin.matches.show',$match) }}">Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No matches found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
