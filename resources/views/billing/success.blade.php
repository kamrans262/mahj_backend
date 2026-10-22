@php
    // The checkout session is verified by the authenticated API, not by this page.
    $sessionId = request()->query('session_id');
    $returnUrl = is_string($sessionId)
        && preg_match('/^cs_(?:test|live)_[A-Za-z0-9_]+$/D', $sessionId)
        ? 'mahjaroundtown://billing/success?session_id='.rawurlencode($sessionId)
        : null;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mahj Around Town - Subscription</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#F7F8FA;color:#0D1328;font-family:Inter,Arial,sans-serif}
        .card{width:min(460px,100%);background:#fff;border:1px solid #EAECF0;border-radius:20px;padding:32px;text-align:center;box-shadow:0 18px 45px rgba(13,19,40,.07)}
        .icon{width:62px;height:62px;margin:0 auto 18px;border-radius:50%;display:grid;place-items:center;background:#ECFDF3;color:#16803A;font-size:28px;font-weight:700}
        h1{font-size:24px;margin:0 0 10px}.open-app{display:block;margin-top:22px;padding:15px 20px;background:#EC5D01;color:#fff;font-weight:600;text-decoration:none;border-radius:12px}p{margin:0;color:#667085;line-height:1.65;font-size:14px}.brand{margin-top:24px;color:#EC5D01;font-weight:700}
    </style>
</head>
<body>
<div class="card">
    <div class="icon">✓</div>
    <h1>Subscription setup complete</h1>
    @if ($returnUrl)
    <p>Opening Mahj Around Town to confirm your subscription...</p>
    <a class="open-app" href="{{ $returnUrl }}">Open Mahj Around Town</a>
    <p style="margin-top:12px">If the app does not open automatically, tap the orange button.</p>
    @else
    <p>Return to Mahj Around Town to check your subscription status.</p>
    @endif
    <div class="brand">Mahj Around Town</div>
</div>
@if ($returnUrl)
<script>
    // Keep the manual button: some mobile browsers block automatic URL-scheme launches.
    // The app still verifies the Stripe session before activating membership.
    window.location.replace(@json($returnUrl));
</script>
@endif
</body>
</html>
