@extends('admin.layout')

@section('title', 'Manage Match')
@section('subtitle', 'Review match details and control its status.')

@section('content')
<a class="back" href="{{ route('admin.matches') }}">← Back to matches</a>

<div class="grid2">
    <div class="card">
        <div class="card-pad">
            <div class="card-head"><div><div class="card-title">{{ $match->venue_name ?: 'Mahj Match' }}</div><div class="card-copy">{{ $match->location_address }}</div></div><span class="badge {{ $match->status === 'open' ? 'success' : ($match->status === 'cancelled' ? 'danger' : 'orange') }}">{{ ucfirst($match->status) }}</span></div>
            <div class="meta">
                <div class="meta-row"><span>Host</span><b>{{ $match->host->name }} · {{ $match->host->email }}</b></div>
                <div class="meta-row"><span>Starts</span><b>{{ $match->starts_at->format('M j, Y · g:i A') }}</b></div>
                <div class="meta-row"><span>Players</span><b>{{ $match->players_count }}/{{ $match->max_players }}</b></div>
                <div class="meta-row"><span>Visibility</span><b>{{ $match->is_invite_only ? 'Invite only' : ($match->is_public ? 'Public' : 'Private') }}</b></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-pad">
            <div class="card-head"><div><div class="card-title">Match status</div><div class="card-copy">Update the operational state of this match.</div></div></div>
            <form method="post" action="{{ route('admin.matches.update',$match) }}">
                @csrf @method('patch')
                <div class="field">
                    <label>Status</label>
                    <select class="select" name="status">
                        @foreach(['open' => 'Open', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
                            <option value="{{ $value }}" @selected($match->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn" style="margin-top:16px" type="submit">Save match status</button>
            </form>
        </div>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <div class="card-pad">
        <div class="card-head"><div><div class="card-title">Players</div><div class="card-copy">Current participants, including the host.</div></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
                <tbody>
                @foreach($match->players as $player)
                    <tr><td>{{ $player->name }}</td><td>{{ $player->email }}</td><td>{{ $player->id === $match->host_user_id ? 'Host' : 'Player' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top:18px;border-color:#FECDCA">
    <div class="card-pad">
        <div class="card-title" style="color:#B42318">Delete match</div>
        <div class="card-copy">Permanently removes this match and its participant records.</div>
        <form method="post" action="{{ route('admin.matches.delete',$match) }}" onsubmit="return confirm('Permanently delete this match?')">
            @csrf @method('delete')
            <button class="btn btn-danger" style="margin-top:16px" type="submit">Delete match permanently</button>
        </form>
    </div>
</div>
@endsection
