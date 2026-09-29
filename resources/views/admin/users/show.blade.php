@extends('admin.layout')

@section('title', 'Manage User')
@section('subtitle', 'Update account details, access and verification.')

@section('content')
<a class="back" href="{{ route('admin.users') }}">← Back to users</a>

<div class="grid2">
    <div>
        <div class="card" style="margin-bottom:18px">
            <div class="card-pad">
                <div class="profile">
                    <div class="big-avatar">{{ strtoupper(substr($user->name,0,1)) }}</div>
                    <div><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-pad">
                <div class="card-head">
                    <div><div class="card-title">Account details</div><div class="card-copy">Change the main account information and access state.</div></div>
                </div>
                <form method="post" action="{{ route('admin.users.update',$user) }}">
                    @csrf @method('patch')
                    <div class="form-grid">
                        <div class="field"><label>Full name</label><input class="input" name="name" value="{{ old('name',$user->name) }}" required></div>
                        <div class="field"><label>Email address</label><input class="input" name="email" type="email" value="{{ old('email',$user->email) }}" required></div>
                        <div class="span2 switch-row">
                            <div><b>Email verified</b><span>Use this only when the user's email should be treated as verified.</span></div>
                            <input class="switch" type="checkbox" name="email_verified" value="1" @checked($user->email_verified_at)>
                        </div>
                        <div class="span2 switch-row">
                            <div><b>Suspend account</b><span>Suspended users cannot log in or use authenticated app features.</span></div>
                            <input class="switch" type="checkbox" name="is_suspended" value="1" @checked($user->is_suspended)>
                        </div>
                        <div class="span2"><button class="btn" type="submit">Save account changes</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="margin-bottom:18px">
            <div class="card-pad">
                <div class="card-head"><div><div class="card-title">Subscription</div><div class="card-copy">Current membership information for this player.</div></div></div>
                <div class="meta">
                    <div class="meta-row"><span>Plan</span><b>{{ $user->subscription?->plan?->name ?? 'No subscription' }}</b></div>
                    <div class="meta-row"><span>Status</span><b>{{ $user->subscription ? ucfirst(str_replace('_',' ',$user->subscription->status)) : 'Not subscribed' }}</b></div>
                    <div class="meta-row"><span>Trial ends</span><b>{{ $user->subscription?->trial_ends_at?->format('M j, Y') ?? '—' }}</b></div>
                    <div class="meta-row"><span>Period ends</span><b>{{ $user->subscription?->current_period_ends_at?->format('M j, Y') ?? '—' }}</b></div>
                </div>
                <a class="btn btn-soft" style="margin-top:18px" href="{{ route('admin.subscriptions.user',$user) }}">{{ $user->subscription ? 'Manage subscription' : 'Add subscription' }}</a>
            </div>
        </div>

        <div class="card" style="border-color:#FECDCA">
            <div class="card-pad">
                <div class="card-title" style="color:#B42318">Delete account</div>
                <div class="card-copy">Permanently removes this user and related subscription data. This cannot be undone.</div>
                <form method="post" action="{{ route('admin.users.delete',$user) }}" onsubmit="return confirm('Permanently delete this Mahj user?')">
                    @csrf @method('delete')
                    <button class="btn btn-danger" style="margin-top:16px" type="submit">Delete user permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>
