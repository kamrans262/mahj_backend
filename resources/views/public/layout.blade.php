<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="index,follow">
    <meta name="description" content="@yield('meta_description', 'Mahj Around Town helps Mahjong players discover nearby games, meet fellow players, and enjoy playing together.')">
    <meta name="theme-color" content="#EC5D01">
    <title>@yield('page_title', 'Mahj Around Town — Find your next Mahjong game')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/mahj-site.css') }}">
</head>
<body>
<header class="site-header">
    <div class="container nav-wrap">
        <a href="{{ route('public.home') }}" class="site-brand" aria-label="Mahj Around Town home">
            <span class="brand-mark" aria-hidden="true">M</span>
            <span>Mahj Around Town</span>
        </a>
        <nav class="main-nav" aria-label="Main navigation">
            <a href="{{ route('public.home') }}#how-it-works" @if(($activePage ?? '') === 'home') aria-label="How Mahj Around Town works" @endif>How It Works</a>
            <a href="{{ route('public.home') }}#about">About</a>
            <a href="{{ route('public.contact') }}" @if(($activePage ?? '') === 'contact') aria-current="page" @endif>Contact Us</a>
        </nav>
        <a class="admin-link" href="{{ route('admin.login') }}">Login as Admin</a>
    </div>
</header>
@yield('content')
<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="{{ route('public.home') }}" class="site-brand"><span class="brand-mark" aria-hidden="true">M</span><span>Mahj Around Town</span></a>
                <p>A more connected Mahjong community, one local game at a time.</p>
            </div>
            <nav class="footer-links" aria-label="Footer navigation">
                <a href="{{ route('public.terms') }}">Terms &amp; Conditions</a>
                <a href="{{ route('public.privacy') }}">Privacy Policy</a>
                <a href="{{ route('public.contact') }}">Contact Us</a>
                <a href="{{ route('public.account-deletion') }}">Account Deletion</a>
            </nav>
        </div>
        <div class="footer-bottom">&copy; {{ now()->year }} Mahj Around Town. All rights reserved.</div>
    </div>
</footer>
</body>
</html>
