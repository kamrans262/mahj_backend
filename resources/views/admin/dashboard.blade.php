<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mahj Admin</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,sans-serif;background:#fff;color:#0D1328;font-size:14px}
        a{color:inherit;text-decoration:none}.shell{display:grid;grid-template-columns:230px 1fr;min-height:100vh}.side{border-right:1px solid #F2F4F7;padding:26px 20px;position:sticky;top:0;height:100vh;background:#fff}
        .brand{font-size:25px;font-weight:700;margin-bottom:34px}.brand span{color:#EC5D01}.nav a{display:block;padding:12px 14px;border-radius:10px;margin:5px 0;color:#667085}.nav a:hover{background:#FFF4ED;color:#EC5D01}
        .logout{margin-top:30px}.logout button{background:white;border:1px solid rgba(13,19,40,.12);color:#0D1328}
        main{padding:30px;max-width:1500px;width:100%}.top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px}h1{margin:0;font-size:26px}h2{font-size:20px;margin:0 0 16px}.muted{color:#667085}
        .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:20px 0 28px}.stat,.panel{border:1px solid rgba(13,19,40,.09);border-radius:16px;background:#fff;box-shadow:0 3px 12px rgba(13,19,40,.035)}
        .stat{padding:18px}.stat b{display:block;font-size:24px;margin-top:7px}.panel{padding:20px;margin-bottom:22px;overflow:auto}.panel-head{display:flex;justify-content:space-between;gap:15px;align-items:center;margin-bottom:16px}
        .search{display:flex;gap:8px}.search input{min-width:260px}.grid-form{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:10px;align-items:end;margin-bottom:18px;padding:14px;background:#FFF8F3;border-radius:12px}
        label{display:block;font-size:11px;font-weight:600;color:#667085;margin:0 0 5px}input,select{width:100%;height:38px;border:1px solid rgba(13,19,40,.13);border-radius:9px;padding:0 9px;background:#fff;color:#0D1328;font:inherit}input:focus,select:focus{outline:none;border-color:#EC5D01}
        input[type=checkbox]{width:auto;height:auto}.btn,button{height:38px;border:0;border-radius:9px;padding:0 14px;background:#EC5D01;color:#fff;font:inherit;font-weight:600;cursor:pointer}.secondary{background:white;color:#0D1328;border:1px solid rgba(13,19,40,.12)}.danger{background:#EF4444}
        table{width:100%;border-collapse:collapse;min-width:900px}th,td{text-align:left;padding:11px 8px;border-bottom:1px solid #F2F4F7;vertical-align:middle}th{font-size:11px;color:#667085;text-transform:uppercase;letter-spacing:.04em}td form{margin:0}
        .inline{display:flex;gap:7px;align-items:center}.inline input,.inline select{min-width:110px}.pill{display:inline-flex;padding:5px 9px;border-radius:999px;background:#FFF0E6;color:#EC5D01;font-size:11px;font-weight:700}.ok{background:#ECFDF3;color:#027A48}.off{background:#FEF3F2;color:#B42318}
        .flash{padding:12px 14px;border-radius:10px;background:#ECFDF3;color:#027A48;margin-bottom:16px}.errors{padding:12px 14px;border-radius:10px;background:#FEF3F2;color:#B42318;margin-bottom:16px}
        @media(max-width:980px){.shell{grid-template-columns:1fr}.side{height:auto;position:static;border-right:0;border-bottom:1px solid #F2F4F7}.nav{display:flex;flex-wrap:wrap}.stats{grid-template-columns:repeat(2,1fr)}main{padding:20px}.grid-form{grid-template-columns:repeat(2,1fr)}} 
    </style>
</head>
<body>
<div class="shell">
<aside class="side">
    <div class="brand">Mahj <span>Admin</span></div>
    <nav class="nav">
        <a href="#dashboard">Dashboard</a><a href="#users">Users & Accounts</a><a href="#plans">Subscription Plans</a><a href="#subscriptions">User Subscriptions</a>
    </nav>
    <form class="logout" method="post" action="{{ route('admin.logout') }}">@csrf<button type="submit">Log Out</button></form>
</aside>
<main>
    <div id="dashboard" class="top"><div><h1>Dashboard</h1><div class="muted">Manage the Mahj app from one place.</div></div><div class="pill">{{ auth()->user()->name }}</div></div>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
    <section class="stats">
        <div class="stat"><span class="muted">Users</span><b>{{ $stats['users'] }}</b></div>
        <div class="stat"><span class="muted">Verified</span><b>{{ $stats['verified'] }}</b></div>
        <div class="stat"><span class="muted">On Trial</span><b>{{ $stats['trialing'] }}</b></div>
        <div class="stat"><span class="muted">Active</span><b>{{ $stats['active'] }}</b></div>
    </section>

    <section id="users" class="panel">
        <div class="panel-head"><div><h2>Users & Accounts</h2><div class="muted">Milestone 1 account management.</div></div>
            <form class="search" method="get"><input name="search" value="{{ $search }}" placeholder="Search name or email"><button type="submit">Search</button></form>
        </div>
        <table><thead><tr><th>User</th><th>Email</th><th>Verified</th><th>Status</th><th>Subscription</th><th>Actions</th></tr></thead><tbody>
        @forelse($users as $user)
            <tr>
                <td><form id="user-{{ $user->id }}" method="post" action="{{ route('admin.users.update',$user) }}">@csrf @method('patch')<input name="name" value="{{ $user->name }}"></form></td>
                <td><input form="user-{{ $user->id }}" name="email" type="email" value="{{ $user->email }}"></td>
                <td><label class="inline"><input form="user-{{ $user->id }}" type="checkbox" name="email_verified" value="1" @checked($user->email_verified_at)> <span>{{ $user->email_verified_at ? 'Yes' : 'No' }}</span></label></td>
                <td><label class="inline"><input form="user-{{ $user->id }}" type="checkbox" name="is_suspended" value="1" @checked($user->is_suspended) @disabled(auth()->id()===$user->id)> <span>{{ $user->is_suspended ? 'Suspended' : ($user->is_admin ? 'Admin' : 'Active') }}</span></label></td>
                <td>{{ $user->subscription?->plan?->name ?? 'None' }} @if($user->subscription)<span class="pill">{{ $user->subscription->status }}</span>@endif</td>
                <td><div class="inline"><button form="user-{{ $user->id }}" type="submit">Save</button>
                @if(!$user->is_admin)<form method="post" action="{{ route('admin.users.delete',$user) }}" onsubmit="return confirm('Delete this user?')">@csrf @method('delete')<button class="danger" type="submit">Delete</button></form>@endif</div></td>
            </tr>
        @empty<tr><td colspan="6">No users found.</td></tr>@endforelse
        </tbody></table>
    </section>

    <section id="plans" class="panel">
        <div class="panel-head"><div><h2>Subscription Plans</h2><div class="muted">Pricing, trial duration, visibility and Stripe Price ID are editable here.</div></div></div>
        <form class="grid-form" method="post" action="{{ route('admin.plans.store') }}">@csrf
            <div><label>Name</label><input name="name" value="Monthly Plan" required></div><div><label>Slug</label><input name="slug" value="monthly"></div>
            <div><label>Price (cents)</label><input name="price_cents" type="number" min="0" value="0" required></div><div><label>Trial days</label><input name="trial_days" type="number" min="0" value="14" required></div>
            <div><label>Stripe Price ID</label><input name="stripe_price_id" placeholder="price_..."></div><div><label>Active</label><input type="checkbox" name="is_active" value="1" checked></div>
            <input type="hidden" name="currency" value="USD"><input type="hidden" name="interval" value="month"><input type="hidden" name="sort_order" value="20"><input type="hidden" name="description" value="No charges for 14 days">
            <div><button type="submit">Add Plan</button></div>
        </form>
        <table><thead><tr><th>Name / Description</th><th>Price</th><th>Trial</th><th>Stripe</th><th>Active</th><th></th></tr></thead><tbody>
        @foreach($plans as $plan)<tr>
            <td><form id="plan-{{ $plan->id }}" method="post" action="{{ route('admin.plans.update',$plan) }}">@csrf @method('patch')
                <input name="name" value="{{ $plan->name }}"><input name="description" value="{{ $plan->description }}" style="margin-top:6px"></form></td>
            <td><input form="plan-{{ $plan->id }}" name="price_cents" type="number" min="0" value="{{ $plan->price_cents }}"><input form="plan-{{ $plan->id }}" name="currency" value="{{ $plan->currency }}" style="margin-top:6px"></td>
            <td><input form="plan-{{ $plan->id }}" name="trial_days" type="number" min="0" value="{{ $plan->trial_days }}"><input form="plan-{{ $plan->id }}" type="hidden" name="interval" value="month"></td>
            <td><input form="plan-{{ $plan->id }}" name="stripe_price_id" value="{{ $plan->stripe_price_id }}" placeholder="price_..."><input form="plan-{{ $plan->id }}" type="hidden" name="slug" value="{{ $plan->slug }}"><input form="plan-{{ $plan->id }}" type="hidden" name="sort_order" value="{{ $plan->sort_order }}"></td>
            <td><input form="plan-{{ $plan->id }}" type="checkbox" name="is_active" value="1" @checked($plan->is_active)></td><td><button form="plan-{{ $plan->id }}" type="submit">Save</button></td>
        </tr>@endforeach</tbody></table>
    </section>

    <section id="subscriptions" class="panel">
        <div class="panel-head"><div><h2>User Subscriptions</h2><div class="muted">Assign or override a user's plan, status and entitlement dates.</div></div></div>
        <form class="grid-form" method="post" action="{{ route('admin.subscriptions.save') }}">@csrf
            <div><label>User</label><select name="user_id" required>@foreach($users->where('is_admin',false) as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></div>
            <div><label>Plan</label><select name="subscription_plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select></div>
            <div><label>Status</label><select name="status"><option>trialing</option><option>active</option><option>past_due</option><option>canceled</option></select></div>
            <div><label>Trial ends</label><input name="trial_ends_at" type="datetime-local"></div><div><label>Period ends</label><input name="current_period_ends_at" type="datetime-local"></div>
            <div><label>Cancel at end</label><input type="checkbox" name="cancel_at_period_end" value="1"></div><div><button type="submit">Save Subscription</button></div>
        </form>
        <table><thead><tr><th>User</th><th>Plan</th><th>Status</th><th>Trial End</th><th>Period End</th><th>Cancel</th></tr></thead><tbody>
        @forelse($subscriptions as $subscription)<tr>
            <td>{{ $subscription->user->name }}<div class="muted">{{ $subscription->user->email }}</div></td><td>{{ $subscription->plan->name }}</td>
            <td><span class="pill {{ $subscription->status==='active'?'ok':($subscription->status==='canceled'?'off':'') }}">{{ $subscription->status }}</span></td>
            <td>{{ $subscription->trial_ends_at?->format('M j, Y H:i') ?? '—' }}</td><td>{{ $subscription->current_period_ends_at?->format('M j, Y H:i') ?? '—' }}</td><td>{{ $subscription->cancel_at_period_end ? 'Yes' : 'No' }}</td>
        </tr>@empty<tr><td colspan="6">No subscriptions yet.</td></tr>@endforelse
        </tbody></table>
    </section>
</main></div>
</body></html>
