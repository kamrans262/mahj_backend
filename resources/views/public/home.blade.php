@extends('public.layout')

@section('page_title', 'Mahj Around Town | Find local Mahjong games and players')
@section('meta_description', 'Find your Mahjong community. Discover nearby games, connect with local players, and organize meaningful game nights with Mahj Around Town.')

@section('content')
<main>
    <section class="hero" aria-labelledby="hero-title">
        <div class="container hero-inner">
            <div class="hero-copy">
                <div class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> Mahjong is better together</div>
                <h1 id="hero-title">Great games.<br>Good company.<br><em>Right around town.</em></h1>
                <p class="hero-lead">Your next Mahjong table is closer than you think. Discover games near you, meet people who love to play, and make every game night something to look forward to.</p>
                <div class="hero-buttons">
                    <a class="btn btn-primary" href="#how-it-works">See How It Works <span aria-hidden="true">→</span></a>
                    <a class="btn btn-outline" href="{{ route('public.contact') }}">Get in Touch <span aria-hidden="true">↗</span></a>
                </div>
                <div class="hero-points" aria-label="Mahj highlights">
                    <span>Find local games</span>
                    <span>Meet fellow players</span>
                    <span>Play more often</span>
                </div>
            </div>
            <div class="hero-art" aria-label="Decorative illustration of a Mahjong table and local game community" role="img">
                <div class="art-backdrop">
                    <div class="art-header">
                        <span class="art-label">Around the table</span>
                        <span class="art-pill">The Mahj Community</span>
                    </div>
                    <div class="art-headline">A seat at the table<br>is waiting for you.</div>
                    <div class="mahj-board" aria-hidden="true">
                        <div class="tile-row">
                            <div class="mahj-tile">東</div>
                            <div class="mahj-tile orange">中</div>
                            <div class="mahj-tile"><div class="tile-dots"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></div>
                            <div class="mahj-tile red">發</div>
                            <div class="mahj-tile ink">南</div>
                        </div>
                        <div class="tile-row">
                            <div class="mahj-tile red">一</div>
                            <div class="mahj-tile"><div class="tile-dots"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></div>
                            <div class="mahj-tile ink">西</div>
                            <div class="mahj-tile orange">二</div>
                            <div class="mahj-tile">北</div>
                        </div>
                    </div>
                    <div class="art-bottom">
                        <div><strong>More than just a game.</strong><small>It's the people you meet along the way.</small></div>
                        <div class="avatar-stack" aria-hidden="true"><span>M</span><span>A</span><span>J</span></div>
                    </div>
                </div>
                <div class="float-card float-a" aria-hidden="true"><span class="float-icon">⌖</span><span><strong>Discover nearby</strong><small>Find a game around you</small></span></div>
                <div class="float-card float-b" aria-hidden="true"><span class="float-icon">♡</span><span><strong>Find your people</strong><small>Make every game count</small></span></div>
            </div>
        </div>
    </section>

    <section class="section section-light" id="how-it-works" aria-labelledby="how-title">
        <div class="container">
            <div class="section-head">
                <div class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> Simple to get started</div>
                <h2 id="how-title">More Mahjong. Less searching.</h2>
                <p>From finding your next game to meeting your next favorite playing partner, Mahj Around Town brings it all together.</p>
            </div>
            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">⌖</div>
                    <div class="feature-number">01 / DISCOVER</div>
                    <h3>Find games nearby</h3>
                    <p>Explore Mahjong games around your location and find opportunities to play that fit your plans.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">♧</div>
                    <div class="feature-number">02 / CONNECT</div>
                    <h3>Meet fellow players</h3>
                    <p>See player profiles, connect through invitations and chat, and get to know your local Mahjong community.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">✦</div>
                    <div class="feature-number">03 / PLAY</div>
                    <h3>Make game night happen</h3>
                    <p>Join a game or host one of your own. Spend less time organizing and more time playing together.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section" id="about" aria-labelledby="about-title">
        <div class="container discover">
            <div class="discover-scene" aria-hidden="true">
                <div class="scene-central">
                    <div class="scene-title">Your Mahjong community</div>
                    <p class="scene-copy">Everything starts with a shared love of the game.</p>
                    <div style="margin-top:18px">
                        <div class="scene-check"><i>✓</i><span>Discover local games</span></div>
                        <div class="scene-check"><i>✓</i><span>Connect with other players</span></div>
                        <div class="scene-check"><i>✓</i><span>Bring friends to the table</span></div>
                    </div>
                </div>
            </div>
            <div class="discover-content">
                <div class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> Made for Mahjong lovers</div>
                <h2 id="about-title">Because the best part of Mahjong is who you play with.</h2>
                <p>Whether you are new to an area, looking for more regular games, or ready to host a table of your own, Mahj Around Town helps turn a shared hobby into real connections.</p>
                <ul class="reason-list">
                    <li>Discover games based on location and distance</li>
                    <li>Create and join games with other players</li>
                    <li>Stay connected through player profiles and chat</li>
                </ul>
                <a class="btn btn-outline" href="{{ route('public.contact') }}">Questions? Contact Us <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container">
            <div class="cta-inner">
                <div>
                    <h2>Your next great game starts here.</h2>
                    <p>Bring your love of Mahjong and we'll help you find the people to share it with.</p>
                </div>
                <a href="{{ route('public.contact') }}" class="btn btn-cream">Get in Touch <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </section>
</main>
@endsection
