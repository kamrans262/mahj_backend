<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mahj Around Town - Checkout</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#F7F8FA;color:#0D1328;font-family:Inter,Arial,sans-serif}
        .card{width:min(460px,100%);background:#fff;border:1px solid #EAECF0;border-radius:20px;padding:32px;text-align:center;box-shadow:0 18px 45px rgba(13,19,40,.07)}
        .icon{width:62px;height:62px;margin:0 auto 18px;border-radius:50%;display:grid;place-items:center;background:#FFF0E6;color:#EC5D01;font-size:28px;font-weight:700}
        h1{font-size:24px;margin:0 0 10px}.open-app{display:block;margin-top:22px;padding:15px 20px;background:#EC5D01;color:#fff;font-weight:600;text-decoration:none;border-radius:12px}p{margin:0;color:#667085;line-height:1.65;font-size:14px}.brand{margin-top:24px;color:#EC5D01;font-weight:700}
    </style>
</head>
<body>
<div class="card">
    <div class="icon">×</div>
    <h1>Checkout canceled</h1>
    <p>No subscription changes were made. Returning to Mahj Around Town...</p>
    <a class="open-app" href="mahjaroundtown://billing/cancel">Open Mahj Around Town</a>
    <p style="margin-top:12px">If the app does not open automatically, tap the orange button.</p>
    <div class="brand">Mahj Around Town</div>
</div>
<script>
    window.location.replace('mahjaroundtown://billing/cancel');
</script>
</body>
</html>
