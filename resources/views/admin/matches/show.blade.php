@extends('admin.layout')

@section('title', 'Manage Match')
@section('subtitle', 'Review match details and control its status.')

@section('content')
<a class="back" href="{{ route('admin.matches') }}">← Back to matches</a>

<div class="grid2">
    <div class="card">
        <div class="card-pad">
            <div class="card-head"><div><div class="card-title">{{ $match->sport?->name ?? $match->custom_sport_name ?? $match->name }}</div><div class="card-copy">{{ $match->venue_name ?: $match->location_address }}</div></div><span class="badge {{ $match->status === 'open' ? 'success' : ($match->status === 'cancelled' ? 'danger' : 'orange') }}">{{ ucfirst($match->status) }}</span></div>
            <div class="meta">
                <div class="meta-row"><span>Host</span><b>{{ $match->host->name }} · {{ $match->host->email }}</b></div>
                <div class="meta-row"><span>Starts</span><b>{{ $match->starts_at->format('M j, Y · g:i A') }}</b></div>
                <div class="meta-row"><span>Players</span><b>{{ $match->players_count }}/{{ $match->max_players }}</b></div>
                <div class="meta-row"><span>Visibility</span><b>{{ $match->is_invite_only ? 'Invite only' : ($match->is_public ? 'Public' : 'Private') }}</b></div>
                <div class="meta-row"><span>Coordinates</span><b>{{ $match->latitude !== null && $match->longitude !== null ? $match->latitude.', '.$match->longitude : 'Not set' }}</b></div>
                <div class="meta-row"><span>Featured</span><b>{{ $match->is_featured ? 'Yes · order '.$match->featured_order : 'No' }}</b></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-pad">
            <div class="card-head"><div><div class="card-title">Match settings</div><div class="card-copy">Edit the core details, map coordinates and operational state of this match.</div></div></div>
            <form method="post" action="{{ route('admin.matches.update',$match) }}">
                @csrf @method('patch')
                <div class="form-grid">
                    <div class="field">
                        <label>Sport</label>
                        <select class="select" name="sport_id">
                            <option value="">Other / custom</option>
                            @foreach($sports as $sport)
                                <option value="{{ $sport->id }}" @selected((string) old('sport_id',$match->sport_id) === (string) $sport->id)>
                                    {{ $sport->name }}{{ $sport->is_active ? '' : ' (inactive)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Custom sport name</label>
                        <input class="input" name="custom_sport_name" value="{{ old('custom_sport_name',$match->custom_sport_name) }}" placeholder="Required only for Other">
                    </div>
                    <div class="span2 field"><label>Location / address</label><input class="input" name="location_address" value="{{ old('location_address',$match->location_address) }}" required></div>
                    <div class="field"><label>Venue name</label><input class="input" name="venue_name" value="{{ old('venue_name',$match->venue_name) }}"></div>
                    <div class="field"><label>Starts at</label><input class="input" type="datetime-local" name="starts_at" value="{{ old('starts_at',$match->starts_at->format('Y-m-d\\TH:i')) }}" required></div>
                    <div class="field">
                        <label>Latitude</label>
                        <input class="input" type="number" step="0.0000001" min="-90" max="90" name="latitude" value="{{ old('latitude',$match->latitude) }}" placeholder="40.7850910">
                        <small>Used for nearby filtering and map placement.</small>
                    </div>
                    <div class="field">
                        <label>Longitude</label>
                        <input class="input" type="number" step="0.0000001" min="-180" max="180" name="longitude" value="{{ old('longitude',$match->longitude) }}" placeholder="-73.9682850">
                        <small>Latitude and longitude must be provided together.</small>
                    </div>
                    <div class="field">
                        <label>Status</label>
                        <select class="select" name="status">
                            @foreach(['open' => 'Open', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
                                <option value="{{ $value }}" @selected($match->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="span2 field"><label>Notes for players</label><textarea class="input" name="notes" style="min-height:90px;padding-top:12px">{{ old('notes',$match->notes) }}</textarea></div>
                    <div class="field switch-row">
                        <div><b>Public match</b><span>Visible in public match discovery.</span></div>
                        <input class="switch" type="checkbox" name="is_public" value="1" @checked($match->is_public)>
                    </div>
                    <div class="span2 switch-row">
                        <div><b>Invite only</b><span>Only invited players should be able to join.</span></div>
                        <input class="switch" type="checkbox" name="is_invite_only" value="1" @checked($match->is_invite_only)>
                    </div>
                    <div class="field switch-row">
                        <div><b>Featured match</b><span>Show this match in the Home featured banner slider.</span></div>
                        <input class="switch" type="checkbox" name="is_featured" value="1" @checked($match->is_featured)>
                    </div>
                    <div class="field">
                        <label>Featured order</label>
                        <input class="input" type="number" min="0" max="65535" name="featured_order" value="{{ old('featured_order',$match->featured_order) }}">
                        <small>Lower numbers appear first in the banner slider.</small>
                    </div>
                    <div class="span2"><button class="btn" type="submit">Save match changes</button></div>
                </div>
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
