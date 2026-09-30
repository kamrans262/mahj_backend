@extends('admin.layout')

@section('title', 'Invitations')
@section('subtitle', 'Review match invitations and their current response status.')

@section('content')
<section class="stats">
    <div class="stat"><div class="label">Total invitations</div><div class="value">{{ $totalInvitations }}</div><div class="note">All invitation records</div></div>
    <div class="stat"><div class="label">Pending</div><div class="value">{{ $pendingInvitations }}</div><div class="note">Waiting for a response</div></div>
    <div class="stat"><div class="label">Accepted</div><div class="value">{{ $acceptedInvitations }}</div><div class="note">Accepted invitations</div></div>
    <div class="stat"><div class="label">Declined</div><div class="value">{{ $declinedInvitations }}</div><div class="note">Declined invitations</div></div>
</section>

<div class="toolbar">
    <div>
        <div class="card-title">Invitation activity</div>
        <div class="card-copy">Search by player, host, match, venue or location and filter by invitation status.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.invitations') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search invitations">
        <select class="select" name="status" style="max-width:150px">
            <option value="">All statuses</option>
            @foreach(['pending' => 'Pending', 'accepted' => 'Accepted', 'declined' => 'Declined'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
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
                    <th>Inviter</th>
                    <th>Invitee</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Responded</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($invitations as $invitation)
                @php
                    $match = $invitation->match;
                    $statusClass = $invitation->status === 'accepted'
                        ? 'success'
                        : ($invitation->status === 'declined' ? 'danger' : 'warning');
                @endphp
                <tr>
                    <td>
                        <b>{{ $match?->sport?->name ?? $match?->custom_sport_name ?? $match?->name ?? 'Deleted match' }}</b>
                        <div style="color:#667085;font-size:10px;margin-top:3px">{{ $match?->venue_name ?: $match?->location_address }}</div>
                    </td>
                    <td>
                        <b>{{ $invitation->inviter?->name ?? 'Unknown user' }}</b>
                        <div style="color:#667085;font-size:10px;margin-top:3px">{{ $invitation->inviter?->email }}</div>
                    </td>
                    <td>
                        <b>{{ $invitation->invitee?->name ?? 'Unknown user' }}</b>
                        <div style="color:#667085;font-size:10px;margin-top:3px">{{ $invitation->invitee?->email }}</div>
                    </td>
                    <td><span class="badge {{ $statusClass }}">{{ ucfirst($invitation->status) }}</span></td>
                    <td>{{ $invitation->created_at?->format('M j, Y · g:i A') }}</td>
                    <td>{{ $invitation->responded_at?->format('M j, Y · g:i A') ?? '—' }}</td>
                    <td>
                        @if($match)
                            <a class="btn btn-secondary" href="{{ route('admin.matches.show', $match) }}">View match</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No invitations found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
