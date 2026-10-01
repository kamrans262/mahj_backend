@extends('admin.layout')

@section('title', 'Support Requests')
@section('subtitle', 'Review support and problem reports submitted from the Mahj app.')

@section('content')
<section class="stats">
    <div class="stat"><div class="label">Total requests</div><div class="value">{{ $totalRequests }}</div><div class="note">All support submissions</div></div>
    <div class="stat"><div class="label">Open</div><div class="value">{{ $openRequests }}</div><div class="note">Awaiting a response</div></div>
    <div class="stat"><div class="label">Closed</div><div class="value">{{ $closedRequests }}</div><div class="note">Resolved requests</div></div>
</section>

<div class="toolbar">
    <div>
        <div class="card-title">Support inbox</div>
        <div class="card-copy">Search by user, topic or message and manage request status.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.support.requests') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search support">
        <select class="select" name="status" style="max-width:140px">
            <option value="">All statuses</option>
            <option value="open" @selected($status === 'open')>Open</option>
            <option value="closed" @selected($status === 'closed')>Closed</option>
        </select>
        <button class="btn" type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>User</th><th>Topic</th><th>Message</th><th>Screenshot</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
            <tbody>
            @forelse($requests as $item)
                <tr>
                    <td>
                        @if($item->user)
                            <a href="{{ route('admin.users.show', $item->user) }}"><b>{{ $item->user->name }}</b></a>
                            <div style="color:#667085;font-size:10px;margin-top:3px">{{ $item->user->email }}</div>
                        @else
                            <span>Deleted user</span>
                        @endif
                    </td>
                    <td>{{ str($item->topic)->replace('_', ' ')->title() }}</td>
                    <td style="max-width:380px;white-space:normal;line-height:1.5">{{ $item->message }}</td>
                    <td>
                        @if($item->screenshot_path)
                            <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ asset('storage/'.$item->screenshot_path) }}">View</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $item->status === 'open' ? 'warning' : 'success' }}">{{ ucfirst($item->status) }}</span>
                        @if($item->closed_at)
                            <div style="color:#667085;font-size:10px;margin-top:5px">{{ $item->closed_at->format('M j, Y · g:i A') }}</div>
                        @endif
                    </td>
                    <td>{{ $item->created_at?->format('M j, Y · g:i A') }}</td>
                    <td>
                        <form method="post" action="{{ route('admin.support.requests.update', $item) }}">
                            @csrf @method('patch')
                            <input type="hidden" name="status" value="{{ $item->status === 'open' ? 'closed' : 'open' }}">
                            <button class="btn {{ $item->status === 'open' ? '' : 'btn-secondary' }}" type="submit">
                                {{ $item->status === 'open' ? 'Close' : 'Reopen' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No support requests found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
