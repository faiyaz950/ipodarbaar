<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ trim($__env->yieldContent('title')) ? trim($__env->yieldContent('title')).' | IPO Darbaar' : 'IPO Darbaar — Live IPO GMP, Upcoming IPOs, Market News & Calculators' }}</title>
    <meta name="description" content="@yield('description', 'Track every mainboard and SME IPO in India — live GMP, subscription dates, price bands, listing calendar, market news shorts and investing calculators.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="theme-color" content="#0A1633">
    <meta property="og:site_name" content="IPO Darbaar">
    <meta property="og:title" content="@yield('title', 'IPO Darbaar')">
    <meta property="og:description" content="@yield('description', 'Live IPO GMP, upcoming IPOs, market news & calculators.')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600;1,9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    @stack('head')
</head>
<body class="@yield('body_class')">

<div class="topstrip">
    <div class="container">
        <div class="left">
            <span>{{ now()->format('l, j F Y') }}</span>
            @if ($lastSynced)
                <span><span class="dot"></span>IPO data updated {{ $lastSynced->diffForHumans() }}</span>
            @endif
        </div>
        <div class="right">
            <a href="{{ route('ipos.gmp') }}">Live GMP</a>
            <a href="{{ route('ipos.calendar') }}">IPO Calendar</a>
            <a href="{{ route('about') }}">About</a>
            <a href="{{ route('disclaimer') }}">Disclaimer</a>
        </div>
    </div>
</div>

<header class="site-header">
    <div class="container">
        <a href="{{ route('home') }}" class="brand" aria-label="IPO Darbaar home">
            @include('partials.brand-mark')
            <span>
                <span class="brand-name">IPO <span>Darbaar</span></span>
                <span class="brand-tag">Every IPO · One Court</span>
            </span>
        </a>

        <nav class="nav" aria-label="Main">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
            <div class="dd {{ request()->routeIs('ipos.index', 'ipos.type', 'ipos.show', 'ipos.calendar') ? 'active' : '' }}">
                <button type="button" aria-haspopup="true">IPOs <x-icon name="chevron-down" :size="15" /></button>
                <div class="dd-menu">
                    <a href="{{ route('ipos.index') }}"><span class="ico"><x-icon name="layers" /></span><span>All IPOs<small>Open, upcoming & listed</small></span></a>
                    <a href="{{ route('ipos.type', 'mainboard') }}"><span class="ico"><x-icon name="building" /></span><span>Mainboard IPOs<small>NSE & BSE main board issues</small></span></a>
                    <a href="{{ route('ipos.type', 'sme') }}"><span class="ico"><x-icon name="briefcase" /></span><span>SME IPOs<small>NSE Emerge & BSE SME</small></span></a>
                    <a href="{{ route('ipos.calendar') }}"><span class="ico"><x-icon name="calendar" /></span><span>IPO Calendar<small>Open, close & listing dates</small></span></a>
                </div>
            </div>
            <a href="{{ route('ipos.gmp') }}" class="{{ request()->routeIs('ipos.gmp') ? 'active' : '' }}"><span class="live"></span> Live GMP</a>
            <a href="{{ route('news.index') }}" class="{{ request()->routeIs('news.index', 'news.show') ? 'active' : '' }}">News</a>
            <a href="{{ route('news.shorts') }}" class="{{ request()->routeIs('news.shorts') ? 'active' : '' }}">Shorts <span class="pill">NEW</span></a>
            <a href="{{ route('calculators.index') }}" class="{{ request()->routeIs('calculators.*') ? 'active' : '' }}">Calculators</a>
        </nav>

        <div class="header-actions">
            <form class="search" action="{{ route('ipos.index') }}" method="get" role="search" data-search>
                <label class="search-field">
                    <x-icon name="search" :size="17" />
                    <input type="search" name="q" placeholder="Search any IPO…" autocomplete="off" aria-label="Search IPOs" data-search-input>
                    <kbd>/</kbd>
                </label>
                <div class="suggest" data-suggest></div>
            </form>
            <button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Toggle dark mode">
                <x-icon name="moon" class="i-moon" />
                <x-icon name="sun" class="i-sun" />
            </button>
            <button type="button" class="icon-btn menu-btn" data-menu-toggle aria-label="Open menu" aria-expanded="false">
                <x-icon name="menu" class="i-menu" />
                <x-icon name="x" class="i-close" />
            </button>
        </div>
    </div>
</header>

<div class="drawer" data-drawer>
    <form class="search" action="{{ route('ipos.index') }}" method="get" role="search" data-search>
        <label class="search-field">
            <x-icon name="search" :size="17" />
            <input type="search" name="q" placeholder="Search any IPO…" autocomplete="off" aria-label="Search IPOs" data-search-input>
        </label>
        <div class="suggest" data-suggest></div>
    </form>
    <nav>
        <a href="{{ route('home') }}"><x-icon name="home" /> Home</a>
        <a href="{{ route('ipos.index') }}"><x-icon name="layers" /> All IPOs</a>
        <a href="{{ route('ipos.type', 'mainboard') }}" class="sub">Mainboard IPOs</a>
        <a href="{{ route('ipos.type', 'sme') }}" class="sub">SME IPOs</a>
        <a href="{{ route('ipos.gmp') }}"><x-icon name="trending-up" /> Live GMP</a>
        <a href="{{ route('ipos.calendar') }}"><x-icon name="calendar" /> IPO Calendar</a>
        <a href="{{ route('news.index') }}"><x-icon name="newspaper" /> Market News</a>
        <a href="{{ route('news.shorts') }}"><x-icon name="smartphone" /> News Shorts</a>
        <a href="{{ route('calculators.index') }}"><x-icon name="calculator" /> Calculators</a>
        <a href="{{ route('about') }}"><x-icon name="info" /> About</a>
    </nav>
</div>

@sectionMissing('hide_ticker')
    @if (count($tickerItems))
        <div class="ticker" aria-label="Live IPO GMP ticker">
            <div class="ticker-label"><x-icon name="activity" :size="15" /> Live GMP</div>
            <div class="ticker-track">
                @foreach (array_merge($tickerItems, $tickerItems) as $t)
                    @php $cls = $t['gmp'] === null ? 'flat' : ($t['gmp'] > 0 ? 'up' : ($t['gmp'] < 0 ? 'down' : 'flat')); @endphp
                    <a class="ticker-item" href="{{ $t['url'] }}" @if($loop->index >= count($tickerItems)) aria-hidden="true" tabindex="-1" @endif>
                        <b>{{ $t['name'] }}</b>
                        @if ($t['price'])<span class="price">₹{{ \App\Models\Ipo::num($t['price']) }}</span>@endif
                        @if ($t['gmp'] !== null)
                            <span class="gmp-pill {{ $cls }} {{ $cls }}">{{ $t['gmp'] > 0 ? '▲ +' : ($t['gmp'] < 0 ? '▼ ' : '') }}₹{{ \App\Models\Ipo::num($t['gmp']) }}@if($t['pct'] !== null) ({{ number_format($t['pct'], 1) }}%)@endif</span>
                        @else
                            <span class="gmp-pill flat">GMP —</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif
@endif

<main id="main">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-about">
                <a href="{{ route('home') }}" class="brand">
                    @include('partials.brand-mark')
                    <span class="brand-name" style="color:#fff">IPO <span>Darbaar</span></span>
                </a>
                <p>IPO Darbaar brings every Indian IPO into one court: live grey market premium, subscription dates, price bands, listing calendar, bite-sized market news and smart calculators.</p>
            </div>
            <div>
                <h4>IPOs</h4>
                <ul>
                    <li><a href="{{ route('ipos.index', ['status' => 'open']) }}">Open IPOs</a></li>
                    <li><a href="{{ route('ipos.index', ['status' => 'upcoming']) }}">Upcoming IPOs</a></li>
                    <li><a href="{{ route('ipos.type', 'mainboard') }}">Mainboard IPOs</a></li>
                    <li><a href="{{ route('ipos.type', 'sme') }}">SME IPOs</a></li>
                    <li><a href="{{ route('ipos.gmp') }}">IPO GMP Today</a></li>
                    <li><a href="{{ route('ipos.calendar') }}">IPO Calendar</a></li>
                </ul>
            </div>
            <div>
                <h4>News</h4>
                <ul>
                    <li><a href="{{ route('news.shorts') }}">News Shorts</a></li>
                    @foreach (array_slice(config('ipodarbar.news_categories'), 0, 5) as $c)
                        <li><a href="{{ route('news.index', ['category' => $c['slug']]) }}">{{ $c['name'] }} News</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h4>Tools</h4>
                <ul>
                    <li><a href="{{ route('calculators.show', 'ipo-gmp') }}">IPO GMP Calculator</a></li>
                    <li><a href="{{ route('calculators.show', 'ipo-allotment-chance') }}">Allotment Chance</a></li>
                    <li><a href="{{ route('calculators.show', 'sip') }}">SIP Calculator</a></li>
                    <li><a href="{{ route('calculators.show', 'brokerage') }}">Brokerage Calculator</a></li>
                    <li><a href="{{ route('calculators.index') }}">All Calculators</a></li>
                </ul>
            </div>
        </div>
        <p class="footer-disclaimer">
            <strong>Disclaimer:</strong> IPO Darbaar is an information platform and is not registered with SEBI as an investment adviser or research analyst. Grey Market Premium (GMP) figures are unofficial and indicative only. Nothing on this site is a recommendation to buy, sell or subscribe to any security. Investments in securities markets are subject to market risks; read all offer documents carefully before investing.
        </p>
        <div class="footer-bottom">
            <span>© {{ now()->year }} IPO Darbaar. All rights reserved.</span>
            <span><a href="{{ route('about') }}">About</a> · <a href="{{ route('disclaimer') }}">Disclaimer</a> · <a href="{{ route('sitemap') }}">Sitemap</a></span>
        </div>
    </div>
</footer>

<div class="toast" data-toast role="status" aria-live="polite"></div>

<script>window.IPO_DARBAAR = { suggestUrl: @json(route('ipos.suggest')), searchUrl: @json(route('ipos.index')) };</script>
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
