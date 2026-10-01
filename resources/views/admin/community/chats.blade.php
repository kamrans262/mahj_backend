@extends('admin.layout')

@section('title', 'Match Chats')
@section('subtitle', 'Review M6 match chat activity and system events.')

@section('content')
<section class="stats">
    <div class="stat"><div class="label">Total messages</div><div class="value">{{ $totalMessages }}</div><div class="note">Player and system messages</div></div>
    <div class="stat"><div class="label">Player messages</div><div class="value">{{ $playerMessages }}</div><div class="note">Messages sent by players</div></div>
    <div class="stat"><div class="label">System messages</div><div class="value">{{ $systemMessages }}</div><div class="note">Join, leave, confirm and reminder events</div></div>
    <div class="stat"><div class="label">Chats with activity</div><div class="value">{{ $activeChats }}</div><div class="note">Matches containing chat records</div></div>
</section>

<div class="toolbar">
    <div>
        <div class="card-title">Chat activity</div>
        <div class="card-copy">Read-only M6 oversight. Search messages, players, venues or matches.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.chats') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search chat activity">
        <select class="select" name="type" style="max-width:150px">
            <option value="">All types</option>
            <option value="message" @selected($type === 'message')>Player messages</option>
            <option value="system" @selected($type === 'system')>System messages</option>
        </select>
        <button class="btn" type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Match</th><th>Sender</th><th>Type</th><th>Message</th><th>Sent</th><th></th></tr></thead>
            <tbody>
            @forelse($messages as $message)
                @php($match = $message->match)
                <tr>
                    <td>
                        <b>{{ $match?->sport?->name ?? $match?->custom_sport_name ?? $match?->name ?? 'Deleted match' }}</b>
                        <div style="color:#667085;font-size:10px;margin-top:3px">{{ $match?->venue_name ?: $match?->location_address }}</div>
                    </td>
                    <td>
                        @if($message->sender)
                            <b>{{ $message->sender->name }}</b>
                            <div style="color:#667085;font-size:10px;margin-top:3px">{{ $message->sender->email }}</div>
                        @else
                            <span class="badge orange">Mahj system</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $message->type === 'system' ? 'orange' : 'success' }}">{{ $message->type === 'system' ? 'System' : 'Player' }}</span></td>
                    <td style="max-width:430px;white-space:normal;line-height:1.5">{{ \Illuminate\Support\Str::limit($message->body, 180) }}</td>
                    <td>{{ $message->created_at?->format('M j, Y · g:i A') }}</td>
                    <td>
                        @if($match)
                            <a class="btn btn-secondary" href="{{ route('admin.matches.show', $match) }}">View match</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No chat activity found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
