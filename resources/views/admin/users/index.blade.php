@extends('admin.layout')

@section('title', 'Users')
@section('subtitle', 'Manage player accounts without editing everything in one table.')

@section('content')
<div class="toolbar">
    <div>
        <div class="card-title">{{ $totalUsers }} registered users</div>
        <div class="card-copy">Search for a player, then open their account to make changes.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.users') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search by name or email">
        <button class="btn" type="submit">Search</button>
        @if($search)<a class="btn btn-secondary" href="{{ route('admin.users') }}">Clear</a>@endif
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>User</th><th>Email</th><th>Status</th><th>Membership</th><th></th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td>
                        <div class="user">
                            <div class="avatar">{{ strtoupper(substr($user->name,0,1)) }}</div>
                            <div><b>{{ $user->name }}</b><span>Joined {{ $user->created_at->format('M j, Y') }}</span></div>
                        </div>
                    </td>
                    <td><span class="badge {{ $user->email_verified_at ? 'success' : 'warning' }}">{{ $user->email_verified_at ? 'Verified' : 'Not verified' }}</span></td>
                    <td><span class="badge {{ $user->is_suspended ? 'danger' : 'success' }}">{{ $user->is_suspended ? 'Suspended' : 'Active' }}</span></td>
                    <td>
                        @if($user->subscription)
                            <span class="badge orange">{{ $user->subscription->plan->name }}</span>
                            <span class="badge">{{ ucfirst(str_replace('_',' ',$user->subscription->status)) }}</span>
                        @else
                            <span style="color:#98A2B3">No subscription</span>
                        @endif
                    </td>
                    <td><a class="btn btn-secondary" href="{{ route('admin.users.show',$user) }}">Manage user</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No users match your search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
