<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>{{ $page->title }} · Mahj Around Town</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#F7F8FA;color:#0D1328;font:14px/1.75 Inter,system-ui,sans-serif}
        a{color:#EC5D01}
        a:focus-visible,button:focus-visible,input:focus-visible,textarea:focus-visible{outline:3px solid #EC5D01;outline-offset:2px}
        .header{background:#fff;border-bottom:1px solid #EAECF0}
        .header-inner{max-width:930px;margin:auto;padding:20px 24px;display:flex;gap:20px;align-items:center;justify-content:space-between;flex-wrap:wrap}
        .brand{display:flex;align-items:center;gap:10px;color:#0D1328;font-weight:700;text-decoration:none;font-size:17px}
        .brand-mark{display:grid;place-items:center;width:36px;height:36px;background:#EC5D01;color:#fff;border-radius:11px}
        .nav{display:flex;gap:18px;align-items:center;flex-wrap:wrap}
        .nav a{color:#667085;font-size:12px;font-weight:600;text-decoration:none}
        .nav a.active,.nav a:hover{color:#EC5D01}
        main{max-width:930px;margin:36px auto 60px;padding:0 24px}
        .panel{background:#fff;border:1px solid #EAECF0;border-radius:18px;padding:36px 42px;box-shadow:0 5px 18px rgba(13,19,40,.035)}
        h1{font-size:30px;line-height:1.3;letter-spacing:-.02em;margin:0}
        .updated{font-size:12px;color:#667085;margin:7px 0 28px}
        .legal-content{overflow-wrap:anywhere}
        .legal-content h2{font-size:18px;line-height:1.5;margin:27px 0 8px}
        .legal-content h3{font-size:16px;margin:22px 0 8px}
        .legal-content p{margin:0 0 14px;color:#475467}
        .legal-content ul,.legal-content ol{color:#475467;padding-left:25px}
        .legal-content li{margin-bottom:6px}
        .request{border-top:1px solid #EAECF0;margin-top:30px;padding-top:23px}
        .request h2{font-size:20px;margin:0 0 7px}
        .hint{font-size:12px;color:#667085;line-height:1.6}
        .field{margin:16px 0}.field label{font-size:12px;font-weight:600;display:block;margin-bottom:6px}
        .input{border:1px solid #D0D5DD;border-radius:11px;width:100%;min-height:44px;padding:11px 12px;font:inherit;background:#fff}
        textarea.input{min-height:95px;resize:vertical}
        .btn{background:#EC5D01;color:#fff;border:0;border-radius:11px;padding:13px 22px;font:inherit;font-weight:600;cursor:pointer}
        .success,.error{padding:12px 14px;border-radius:11px;font-size:12px;line-height:1.6}
        .success{background:#ECFDF3;color:#027A48;border:1px solid #ABEFC6}
        .error{background:#FEF3F2;color:#B42318;border:1px solid #FECDCA}
        .footer{max-width:930px;padding:0 24px 28px;margin:auto;color:#667085;font-size:12px}
        @media(max-width:600px){main{margin-top:20px}.panel{padding:24px 20px}.header-inner{padding:16px 20px}h1{font-size:25px}.nav{gap:12px}}
    </style>
</head>
<body>
<header class="header">
    <div class="header-inner">
        <a class="brand" href="{{ route('public.terms') }}"><span class="brand-mark">M</span>Mahj Around Town</a>
        <nav class="nav" aria-label="Legal pages">
            <a href="{{ route('public.terms') }}" @class(['active' => $slug === 'terms-and-conditions'])>Terms &amp; Conditions</a>
            <a href="{{ route('public.privacy') }}" @class(['active' => $slug === 'privacy-policy'])>Privacy Policy</a>
            <a href="{{ route('public.account-deletion') }}" @class(['active' => $slug === 'account-deletion'])>Account Deletion</a>
        </nav>
    </div>
</header>
<main>
    <article class="panel">
        <h1>{{ $page->title }}</h1>
        <p class="updated">Last updated: {{ $page->published_at?->format('F j, Y') }}</p>
        <div class="legal-content">{!! $html !!}</div>
        @if($slug === 'account-deletion')
            <section class="request" aria-labelledby="deletion-request-title">
                <h2 id="deletion-request-title">Request account deletion</h2>
                <p class="hint">If you cannot access Mahj Around Town, use this form to request deletion. Enter the email associated with your account. We will verify your identity before taking any action. Sending this form does not immediately delete your account.</p>
                @if(session('deletion_request_received'))
                    <div class="success" role="status">Your request has been received. Our team will review it and may contact you to verify account ownership.</div>
                @endif
                @if($errors->any())
                    <div class="error" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="post" action="{{ route('public.account-deletion.request') }}">
                    @csrf
                    <div class="field"><label for="email">Account email address</label><input class="input" id="email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}"></div>
                    <div class="field"><label for="note">Additional information (optional)</label><textarea class="input" id="note" name="note" maxlength="2000" placeholder="Only information needed to help locate your account. Do not include passwords or verification codes.">{{ old('note') }}</textarea></div>
                    <div style="display:none" aria-hidden="true"><label for="company_website">Company website</label><input id="company_website" name="company_website" tabindex="-1" autocomplete="off"></div>
                    <button type="submit" class="btn">Submit deletion request</button>
                </form>
                @if($supportEmail)
                    <p class="hint">Need assistance? Contact <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
                @endif
            </section>
        @endif
    </article>
</main>
<footer class="footer">&copy; {{ now()->year }} Mahj Around Town. <a href="{{ route('public.privacy') }}">Privacy Policy</a> · <a href="{{ route('public.terms') }}">Terms &amp; Conditions</a></footer>
</body>
</html>
