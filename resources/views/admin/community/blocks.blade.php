@extends('admin.layout')

@section('title', 'Blocked Users')
@section('subtitle', 'Review user blocks created from M6 player profiles and match chat.')

@section('content')
<div class="toolbar">
    <div>
        <div class="card-title">{{ $totalBlocks }} active user blocks</div>
        <div class="card-copy">These blocks are enforced in match discovery, invitations and match chat.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.blocks') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search blocked users">
        <button class="btn" type="submit">Search</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Blocked user</th><th>Blocked by</th><th>Reason</th><th>Blocked on</th></tr></thead>
            <tbody>
            @forelse($blocks as $block)
                <tr>
                    <td>
                        @if($block->blockedUser)
                            <a href="{{ route('admin.users.show', $block->blockedUser) }}"><b>{{ $block->blockedUser->name }}</b></a>
                            <div style="color:#667085;font-size:10px;margin-top:3px">{{ $block->blockedUser->email }}</div>
                        @else
                            <span>Deleted user</span>
                        @endif
                    </td>
                    <td>
                        @if($block->blocker)
                            <a href="{{ route('admin.users.show', $block->blocker) }}"><b>{{ $block->blocker->name }}</b></a>
                            <div style="color:#667085;font-size:10px;margin-top:3px">{{ $block->blocker->email }}</div>
                        @else
                            <span>Deleted user</span>
                        @endif
                    </td>
                    <td>{{ $block->reason }}</td>
                    <td>{{ $block->created_at?->format('M j, Y · g:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No user blocks found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
