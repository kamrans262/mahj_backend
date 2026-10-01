@extends('admin.layout')

@section('title', 'Notifications')
@section('subtitle', 'Review M7 in-app notifications and push-device registration.')

@section('content')
<section class="stats">
    <div class="stat"><div class="label">Total notifications</div><div class="value">{{ $totalNotifications }}</div><div class="note">All M7 notification records</div></div>
    <div class="stat"><div class="label">Unread</div><div class="value">{{ $unreadNotifications }}</div><div class="note">Still unread by users</div></div>
    <div class="stat"><div class="label">Read</div><div class="value">{{ $readNotifications }}</div><div class="note">Opened by users</div></div>
    <div class="stat"><div class="label">Push devices</div><div class="value">{{ $registeredDevices }}</div><div class="note">Registered Android / iOS FCM tokens</div></div>
</section>

<div class="toolbar">
    <div>
        <div class="card-title">Notification activity</div>
        <div class="card-copy">Read-only M7 oversight for in-app notifications and push registration.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.notifications') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search user, title, message or match">
        <select class="select" name="type" style="max-width:190px">
            <option value="">All types</option>
            @foreach($types as $notificationType)
                <option value="{{ $notificationType }}" @selected($type === $notificationType)>
                    {{ ucwords(str_replace('_', ' ', $notificationType)) }}
                </option>
            @endforeach
        </select>
        <select class="select" name="status" style="max-width:130px">
            <option value="">All status</option>
            <option value="unread" @selected($status === 'unread')>Unread</option>
            <option value="read" @selected($status === 'read')>Read</option>
        </select>
        <button class="btn" type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>User</th><th>Type</th><th>Notification</th><th>Match / related user</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            @forelse($notifications as $notification)
                <tr>
                    <td>
                        <b>{{ $notification->user?->name ?? 'Deleted user' }}</b>
                        <div style="color:#667085;font-size:10px;margin-top:3px">{{ $notification->user?->email }}</div>
                    </td>
                    <td><span class="badge orange">{{ ucwords(str_replace('_', ' ', $notification->type)) }}</span></td>
                    <td style="max-width:430px;white-space:normal;line-height:1.5">
                        <b>{{ $notification->title }}</b>
                        <div style="color:#667085;font-size:10px;margin-top:5px">{{ mb_strimwidth($notification->message, 0, 180, '…') }}</div>
                    </td>
                    <td>
                        @if($notification->relatedMatch)
                            <a href="{{ route('admin.matches.show', $notification->relatedMatch) }}">
                                <b>{{ $notification->relatedMatch->venue_name ?: $notification->relatedMatch->name ?: 'Match #'.$notification->relatedMatch->id }}</b>
                            </a>
                        @elseif($notification->relatedUser)
                            <b>{{ $notification->relatedUser->name }}</b>
                            <div style="color:#667085;font-size:10px;margin-top:3px">{{ $notification->relatedUser->email }}</div>
                        @else
                            <span style="color:#98A2B3">—</span>
                        @endif
                    </td>
                    <td>
                        @if($notification->read_at)
                            <span class="badge success">Read</span>
                        @else
                            <span class="badge warning">Unread</span>
                        @endif
                    </td>
                    <td>{{ $notification->created_at?->format('M j, Y · g:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No notifications found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
