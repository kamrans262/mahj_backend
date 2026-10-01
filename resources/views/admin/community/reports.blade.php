@extends('admin.layout')

@section('title', 'User Reports')
@section('subtitle', 'Review reports submitted from M6 player profiles and match chat.')

@section('content')
<section class="stats">
    <div class="stat"><div class="label">Total reports</div><div class="value">{{ $totalReports }}</div><div class="note">All submitted user reports</div></div>
    <div class="stat"><div class="label">Pending</div><div class="value">{{ $pendingReports }}</div><div class="note">Awaiting moderation in the safety milestone</div></div>
    <div class="stat"><div class="label">Reviewed</div><div class="value">{{ $reviewedReports }}</div><div class="note">Reports already reviewed</div></div>
</section>

<div class="toolbar">
    <div>
        <div class="card-title">Report activity</div>
        <div class="card-copy">M6 records are visible here. Resolution and moderation actions remain part of the safety milestone.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.reports') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search reports">
        <button class="btn" type="submit">Search</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Reported user</th><th>Reported by</th><th>Reason</th><th>Notes</th><th>Status</th><th>Submitted</th></tr></thead>
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
                    <td>{{ $report->reason }}</td>
                    <td style="max-width:360px;white-space:normal;line-height:1.5">{{ filled($report->notes) ? \Illuminate\Support\Str::limit($report->notes, 180) : '—' }}</td>
                    <td><span class="badge {{ $report->status === 'pending' ? 'warning' : 'success' }}">{{ ucfirst($report->status) }}</span></td>
                    <td>{{ $report->created_at?->format('M j, Y · g:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No user reports found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
