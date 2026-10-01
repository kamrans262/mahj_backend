@extends('admin.layout')

@section('title', 'User Reports')
@section('subtitle', 'Review and resolve safety reports submitted by Mahj users.')

@section('content')
<section class="stats">
    <div class="stat"><div class="label">Total reports</div><div class="value">{{ $totalReports }}</div><div class="note">All submitted user reports</div></div>
    <div class="stat"><div class="label">Pending</div><div class="value">{{ $pendingReports }}</div><div class="note">Awaiting moderation</div></div>
    <div class="stat"><div class="label">Closed</div><div class="value">{{ $closedReports }}</div><div class="note">Resolved by an admin</div></div>
</section>

<div class="toolbar">
    <div>
        <div class="card-title">Report activity</div>
        <div class="card-copy">Review submitted reasons and notes, then close or reopen a report as needed.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.reports') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search reports">
        <select class="select" name="status" style="max-width:140px">
            <option value="">All statuses</option>
            <option value="pending" @selected($status === 'pending')>Pending</option>
            <option value="closed" @selected($status === 'closed')>Closed</option>
        </select>
        <button class="btn" type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Reported user</th><th>Reported by</th><th>Reason</th><th>Notes</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
            <tbody>
            @forelse($reports as $report)
                <tr>
                    <td>
                        @if($report->reportedUser)
                            <a href="{{ route('admin.users.show', $report->reportedUser) }}"><b>{{ $report->reportedUser->name }}</b></a>
                            <div style="color:#667085;font-size:10px;margin-top:3px">{{ $report->reportedUser->email }}</div>
                        @else
                            <span>Deleted user</span>
                        @endif
                    </td>
                    <td>
                        @if($report->reporter)
                            <a href="{{ route('admin.users.show', $report->reporter) }}"><b>{{ $report->reporter->name }}</b></a>
                            <div style="color:#667085;font-size:10px;margin-top:3px">{{ $report->reporter->email }}</div>
                        @else
                            <span>Deleted user</span>
                        @endif
                    </td>
                    <td>{{ str($report->reason)->replace('_', ' ')->title() }}</td>
                    <td style="max-width:360px;white-space:normal;line-height:1.5">{{ filled($report->notes) ? \Illuminate\Support\Str::limit($report->notes, 180) : '—' }}</td>
                    <td>
                        <span class="badge {{ $report->status === 'pending' ? 'warning' : 'success' }}">{{ ucfirst($report->status) }}</span>
                        @if($report->reviewed_at)
                            <div style="color:#667085;font-size:10px;margin-top:5px">{{ $report->reviewed_at->format('M j, Y · g:i A') }}</div>
                        @endif
                    </td>
                    <td>{{ $report->created_at?->format('M j, Y · g:i A') }}</td>
                    <td>
                        <form method="post" action="{{ route('admin.reports.update', $report) }}">
                            @csrf @method('patch')
                            <input type="hidden" name="status" value="{{ $report->status === 'pending' ? 'closed' : 'pending' }}">
                            <button class="btn {{ $report->status === 'pending' ? '' : 'btn-secondary' }}" type="submit">
                                {{ $report->status === 'pending' ? 'Close' : 'Reopen' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No user reports found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
