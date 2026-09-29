@extends('admin.layout')

@section('title', 'Manage Subscription')
@section('subtitle', 'A simple membership form for one user.')

@section('content')
<a class="back" href="{{ route('admin.subscriptions') }}">← Back to subscriptions</a>

<div class="grid2">
    <div class="card">
        <div class="card-pad">
            <div class="profile" style="margin-bottom:20px">
                <div class="big-avatar">{{ strtoupper(substr($user->name,0,1)) }}</div>
                <div><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p></div>
            </div>
            <div class="meta">
                <div class="meta-row"><span>Current plan</span><b>{{ $user->subscription?->plan?->name ?? 'None' }}</b></div>
                <div class="meta-row"><span>Current status</span><b>{{ $user->subscription ? ucfirst(str_replace('_',' ',$user->subscription->status)) : 'Not subscribed' }}</b></div>
                <div class="meta-row"><span>Trial ends</span><b>{{ $user->subscription?->trial_ends_at?->format('M j, Y H:i') ?? '—' }}</b></div>
                <div class="meta-row"><span>Period ends</span><b>{{ $user->subscription?->current_period_ends_at?->format('M j, Y H:i') ?? '—' }}</b></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-pad">
            <div class="card-head">
                <div><div class="card-title">{{ $user->subscription ? 'Update membership' : 'Add membership' }}</div><div class="card-copy">Choose the plan and the user's current access status.</div></div>
            </div>

            <form method="post" action="{{ route('admin.subscriptions.save') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <div class="form-grid">
                    <div class="span2 field">
                        <label>Membership plan</label>
                        <select class="select" name="subscription_plan_id" required>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @selected($user->subscription?->subscription_plan_id === $plan->id)>{{ $plan->name }} — &#36;{{ number_format($plan->price_cents/100,2) }}/month</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="span2 field">
                        <label>Membership status</label>
                        <select class="select" name="status" required>
                            <option value="trialing" @selected($user->subscription?->status === 'trialing')>Free trial — trial is currently running</option>
                            <option value="active" @selected($user->subscription?->status === 'active')>Active — full membership access</option>
                            <option value="past_due" @selected($user->subscription?->status === 'past_due')>Payment issue — payment needs attention</option>
                            <option value="canceled" @selected($user->subscription?->status === 'canceled')>Canceled — membership ended</option>
                        </select>
                        <small>This status controls the user's subscription entitlement.</small>
                    </div>

                    <div class="field">
                        <label>Trial ends (optional)</label>
                        <input class="input" name="trial_ends_at" type="datetime-local" value="{{ $user->subscription?->trial_ends_at?->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="field">
                        <label>Current period ends (optional)</label>
                        <input class="input" name="current_period_ends_at" type="datetime-local" value="{{ $user->subscription?->current_period_ends_at?->format('Y-m-d\TH:i') }}">
                    </div>

                    <div class="span2 switch-row">
                        <div><b>Cancel at the end of this period</b><span>The user keeps access until the current end date, then renewal stops.</span></div>
                        <input class="switch" type="checkbox" name="cancel_at_period_end" value="1" @checked($user->subscription?->cancel_at_period_end)>
                    </div>

                    <div class="span2"><button class="btn" type="submit">Save subscription</button></div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="hint" style="margin-top:18px">
    <b>Status guide:</b> Free trial = inside trial period. Active = full membership access. Payment issue = billing problem. Canceled = subscription no longer active.
</div>
@endsection
