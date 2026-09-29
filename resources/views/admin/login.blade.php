<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mahj Admin</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,sans-serif;background:#fff;color:#0D1328}
        .page{min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at top right,rgba(236,93,1,.10),transparent 34%)}
        .card{width:min(430px,100%);border:1px solid rgba(13,19,40,.10);border-radius:18px;padding:32px;background:#fff;box-shadow:0 12px 34px rgba(13,19,40,.07)}
        .brand{font-size:28px;font-weight:700;margin:0 0 6px}.sub{color:#667085;margin:0 0 28px}.label{display:block;font-size:13px;font-weight:600;margin:16px 0 8px}
        input{width:100%;height:48px;border:1px solid rgba(13,19,40,.14);border-radius:12px;padding:0 14px;font:inherit;color:#0D1328;outline:none}
        input:focus{border-color:#EC5D01;box-shadow:0 0 0 3px rgba(236,93,1,.10)}button{width:100%;height:48px;border:0;border-radius:12px;background:#EC5D01;color:white;font:inherit;font-weight:600;margin-top:22px;cursor:pointer}
        .error{margin:12px 0 0;color:#D92D20;font-size:13px}.row{display:flex;gap:8px;align-items:center;margin-top:14px;color:#667085;font-size:13px}.row input{width:auto;height:auto}
    </style>
</head>
<body>
<div class="page">
    <form class="card" method="post" action="{{ route('admin.login.submit') }}">
        @csrf
        <h1 class="brand">Mahj Admin</h1>
        <p class="sub">Manage users, accounts and subscriptions.</p>
        <label class="label" for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        <label class="label" for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <label class="row"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
        <button type="submit">Sign In</button>
    </form>
</div>
</body>
</html>
