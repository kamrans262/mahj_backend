@extends('admin.layout')

@section('title', 'Overview')
@section('subtitle', 'A clear snapshot of users and memberships.')

@section('content')
<section class="stats">
    <div class="stat"><div class="label">Total users</div><div class="value">{{ $stats['users'] }}</div><div class="note">Registered player accounts</div></div>
    <div class="stat"><div class="label">Verified users</div><div class="value">{{ $stats['verified'] }}</div><div class="note">Email verified</div></div>
    <div class="stat"><div class="label">Free trials</div><div class="value">{{ $stats['trialing'] }}</div><div class="note">Currently trialing</div></div>
    <div class="stat"><div class="label">Active members</div><div class="value">{{ $stats['active'] }}</div><div class="note">Active subscriptions</div></div>
</section>

<section class="grid2" style="margin-bottom:22px">
    <a class="quick" href="{{ route('admin.users') }}">
        <div class="quick-main"><div class="quick-icon">U</div><div><b>Manage users</b><span>View accounts, verify email or suspend access.</span></div></div><strong>→</strong>
    </a>
    <a class="quick" href="{{ route('admin.subscriptions') }}">
        <div class="quick-main"><div class="quick-icon">S</div><div><b>Manage subscriptions</b><span>Change plan settings and member status.</span></div></div><strong>→</strong>
    </a>
    <a class="quick" href="{{ route('admin.matches') }}">
        <div class="quick-main"><div class="quick-icon">M</div><div><b>Manage matches</b><span>Review hosts, players and match status.</span></div></div><strong>→</strong>
    </a>
</section>

<section class="grid2">
    <div class="card">
        <div class="card-pad">
            <div class="card-head"><div><div class="card-title">Newest users</div><div class="card-copy">Recently registered Mahj accounts.</div></div><a class="btn btn-soft" href="{{ route('admin.users') }}">View all</a></div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>User</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($recentUsers as $user)
                    <tr>
                        <td><div class="user"><div class="avatar">{{ strtoupper(substr($user->name,0,1)) }}</div><div><b>{{ $user->name }}</b><span>{{ $user->email }}</span></div></div></td>
                        <td><span class="badge {{ $user->is_suspended ? 'danger' : 'success' }}">{{ $user->is_suspended ? 'Suspended' : 'Active' }}</span></td>
                        <td><a class="btn btn-secondary" href="{{ route('admin.users.show',$user) }}">Manage</a></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No users yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-pad">
            <div class="card-head"><div><div class="card-title">Recent memberships</div><div class="card-copy">Latest subscription activity.</div></div><a class="btn btn-soft" href="{{ route('admin.subscriptions') }}">View all</a></div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>User</th><th>Plan</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($recentSubscriptions as $subscription)
                    <tr>
                        <td><b>{{ $subscription->user->name }}</b><div style="color:#667085;font-size:10px;margin-top:3px">{{ $subscription->user->email }}</div></td>
                        <td>{{ $subscription->plan->name }}</td>
                        <td><span class="badge {{ $subscription->status === 'active' ? 'success' : ($subscription->status === 'trialing' ? 'orange' : 'warning') }}">{{ ucfirst(str_replace('_',' ',$subscription->status)) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No subscriptions yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
