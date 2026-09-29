@extends('admin.layout')

@section('title', 'Subscriptions')
@section('subtitle', 'Simple plan settings and member subscription management.')

@section('content')
<section class="stats">
    <div class="stat"><div class="label">On free trial</div><div class="value">{{ $stats['trialing'] }}</div><div class="note">Trial period currently active</div></div>
    <div class="stat"><div class="label">Active members</div><div class="value">{{ $stats['active'] }}</div><div class="note">Full membership access</div></div>
    <div class="stat"><div class="label">Total users</div><div class="value">{{ $stats['users'] }}</div><div class="note">Users available for membership</div></div>
    <div class="stat"><div class="label">Verified users</div><div class="value">{{ $stats['verified'] }}</div><div class="note">Verified Mahj accounts</div></div>
</section>

@foreach($plans as $plan)
<div class="card" style="margin-bottom:22px">
    <div class="card-pad">
        <div class="card-head">
            <div><div class="card-title">Membership plan</div><div class="card-copy">These are the plan details users see in the app.</div></div>
            <span class="badge {{ $plan->is_active ? 'success' : 'danger' }}">{{ $plan->is_active ? 'Plan active' : 'Plan hidden' }}</span>
        </div>

        <div class="plan-hero">
            <div><div class="eyebrow">Current plan</div><h3>{{ $plan->name }}</h3><p>{{ $plan->trial_days }} day free trial · billed monthly after trial</p></div>
            <div class="price">&#36;{{ number_format($plan->price_cents / 100, 2) }}<span>per month</span></div>
        </div>

        <form method="post" action="{{ route('admin.plans.update',$plan) }}">
            @csrf @method('patch')
            <div class="form-grid">
                <div class="field"><label>Plan name</label><input class="input" name="name" value="{{ $plan->name }}" required></div>
                <div class="field"><label>Monthly price ($)</label><input class="input" name="price_dollars" type="number" min="0" step="0.01" value="{{ number_format($plan->price_cents / 100, 2, '.', '') }}" required><small>Example: enter 9.99, not cents.</small></div>
                <div class="field"><label>Free trial length (days)</label><input class="input" name="trial_days" type="number" min="0" max="365" value="{{ $plan->trial_days }}" required></div>
                <div class="field"><label>Short description</label><input class="input" name="description" value="{{ $plan->description }}"></div>

                <div class="span2 switch-row">
                    <div><b>Plan available in the app</b><span>Turn this off only if you want to hide the plan from users.</span></div>
                    <input class="switch" type="checkbox" name="is_active" value="1" @checked($plan->is_active)>
                </div>

                <div class="span2">
                    <details>
                        <summary>Advanced billing setting</summary>
                        <div class="details-body">
                            <div class="field">
                                <label>Stripe Price ID</label>
                                <input class="input" name="stripe_price_id" value="{{ $plan->stripe_price_id }}" placeholder="price_...">
                                <small>Leave this blank until Stripe pricing is configured.</small>
                            </div>
                            <input type="hidden" name="slug" value="{{ $plan->slug }}">
                        </div>
                    </details>
                </div>

                <div class="span2"><button class="btn" type="submit">Save plan settings</button></div>
            </div>
        </form>
    </div>
</div>
@endforeach

<div class="toolbar">
    <div>
        <div class="card-title">User memberships</div>
        <div class="card-copy">Choose one user and manage their subscription on a simple separate page.</div>
    </div>
    <form class="search" method="get" action="{{ route('admin.subscriptions') }}">
        <input class="input" name="search" value="{{ $search }}" placeholder="Search user name or email">
        <button class="btn" type="submit">Search</button>
        @if($search)<a class="btn btn-secondary" href="{{ route('admin.subscriptions') }}">Clear</a>@endif
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>User</th><th>Plan</th><th>Status</th><th>Access until</th><th></th></tr></thead>
            <tbody>
            @forelse($users as $user)
                @php($subscription = $user->subscription)
                <tr>
                    <td><div class="user"><div class="avatar">{{ strtoupper(substr($user->name,0,1)) }}</div><div><b>{{ $user->name }}</b><span>{{ $user->email }}</span></div></div></td>
                    <td>{{ $subscription?->plan?->name ?? '—' }}</td>
                    <td>
                        @if($subscription)
                            <span class="badge {{ $subscription->status === 'active' ? 'success' : ($subscription->status === 'trialing' ? 'orange' : ($subscription->status === 'canceled' ? 'danger' : 'warning')) }}">{{ ucfirst(str_replace('_',' ',$subscription->status)) }}</span>
                        @else
                            <span class="badge">Not subscribed</span>
                        @endif
                    </td>
                    <td>{{ $subscription?->current_period_ends_at?->format('M j, Y') ?? $subscription?->trial_ends_at?->format('M j, Y') ?? '—' }}</td>
                    <td><a class="btn btn-secondary" href="{{ route('admin.subscriptions.user',$user) }}">{{ $subscription ? 'Manage' : 'Add subscription' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No users match your search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
