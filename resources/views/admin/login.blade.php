<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mahj Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#F7F8FA;color:#0D1328}.page{min-height:100vh;display:grid;grid-template-columns:1.05fr .95fr}.hero{padding:58px;display:flex;flex-direction:column;justify-content:space-between;background:linear-gradient(145deg,#0D1328,#1D2949);color:#fff;position:relative;overflow:hidden}.hero:after{content:"";position:absolute;width:430px;height:430px;border-radius:50%;background:rgba(236,93,1,.18);right:-160px;top:-150px}.brand{display:flex;align-items:center;gap:12px;font-size:22px;font-weight:700;position:relative;z-index:1}.mark{width:42px;height:42px;border-radius:13px;background:#EC5D01;display:grid;place-items:center;box-shadow:0 10px 26px rgba(236,93,1,.25)}.copy{max-width:520px;position:relative;z-index:1}.copy h1{font-size:42px;line-height:1.08;margin:0 0 16px;letter-spacing:-.04em}.copy p{margin:0;color:#B7BED3;line-height:1.7;font-size:14px}.foot{color:#98A2B3;font-size:11px;position:relative;z-index:1}.login{display:grid;place-items:center;padding:30px;background:#fff}.card{width:min(430px,100%)}.eyebrow{color:#EC5D01;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em}.card h2{font-size:28px;margin:9px 0 8px}.sub{margin:0 0 26px;color:#667085;line-height:1.55}.field{margin-bottom:15px}.field label{display:block;margin-bottom:7px;font-size:11px;font-weight:600}.input{width:100%;height:47px;border:1px solid #D0D5DD;border-radius:12px;padding:0 14px;outline:none;font:inherit}.input:focus{border-color:#EC5D01;box-shadow:0 0 0 3px rgba(236,93,1,.1)}.remember{display:flex;align-items:center;gap:8px;color:#667085;font-size:11px}.btn{width:100%;height:47px;border:0;border-radius:12px;background:#EC5D01;color:#fff;font:inherit;font-weight:600;margin-top:20px;cursor:pointer}.error{padding:12px;border-radius:10px;background:#FEF3F2;color:#B42318;border:1px solid #FECDCA;margin-bottom:15px;font-size:11px}@media(max-width:820px){.page{grid-template-columns:1fr}.hero{display:none}.login{min-height:100vh;padding:24px}}
    </style>
</head>
<body>
<div class="page">
    <section class="hero">
        <div class="brand"><span class="mark">M</span>Mahj Admin</div>
        <div class="copy"><h1>Simple control over your Mahj platform.</h1><p>Manage player accounts, free trials and subscriptions from one clear dashboard using the same Mahj visual language.</p></div>
        <div class="foot">Secure administrator access</div>
    </section>
    <section class="login">
        <form class="card" method="post" action="{{ route('admin.login.submit') }}">
            @csrf
            <div class="eyebrow">Administrator</div><h2>Welcome back</h2><p class="sub">Sign in to manage Mahj.</p>
            @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
            <div class="field"><label for="email">Email address</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
            <div class="field"><label for="password">Password</label><input class="input" id="password" name="password" type="password" autocomplete="current-password" required></div>
            <label class="remember"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button class="btn" type="submit">Sign in to Admin</button>
        </form>
    </section>
</div>
</body>
</html>
