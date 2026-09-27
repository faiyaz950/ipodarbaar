<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- Section content is already escaped by @section, so it must not be escaped again. --}}
    <title>{!! trim($__env->yieldContent('title')) ? trim($__env->yieldContent('title')).' | IPO Darbaar' : e('IPO Darbaar — Live IPO GMP, Upcoming IPOs, Market News & Calculators') !!}</title>
    <meta name="description" content="@yield('description', 'Track every mainboard and SME IPO in India — live GMP, subscription dates, price bands, listing calendar, market news shorts and investing calculators.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="theme-color" content="#0A1633">
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="IPO Darbaar">
    <meta property="og:site_name" content="IPO Darbaar">
    <meta property="og:title" content="@yield('title', 'IPO Darbaar')">
    <meta property="og:description" content="@yield('description', 'Live IPO GMP, upcoming IPOs, market news & calculators.')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ trim($__env->yieldContent('og_image')) ?: asset('images/brand/og-logo.png') }}">
    <meta name="twitter:card" content="{{ trim($__env->yieldContent('og_image')) ? 'summary_large_image' : 'summary' }}">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" sizes="32x32" type="image/png">
    <link rel="icon" href="{{ asset('favicon-192.png') }}" sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
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
    @include('partials.analytics')
    @if (request()->routeIs('home'))
        <x-jsonld :data="[
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'Organization', '@id' => route('home').'#org', 'name' => 'IPO Darbaar', 'url' => route('home'), 'logo' => asset('favicon-192.png')],
                [
                    '@type' => 'WebSite', 'name' => 'IPO Darbaar', 'url' => route('home'), 'publisher' => ['@id' => route('home').'#org'],
                    'potentialAction' => ['@type' => 'SearchAction', 'target' => route('ipos.index').'?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
                ],
            ],
        ]" />
    @endif
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
            <div class="dd {{ request()->routeIs('ipos.index', 'ipos.type', 'ipos.show', 'ipos.calendar', 'ipos.year', 'ipos.report-card', 'compare') ? 'active' : '' }}">
                <button type="button" aria-haspopup="true">IPOs <x-icon name="chevron-down" :size="15" /></button>
                <div class="dd-menu">
                    <a href="{{ route('ipos.index') }}"><span class="ico"><x-icon name="layers" /></span><span>All IPOs<small>Open, upcoming & listed</small></span></a>
                    <a href="{{ route('ipos.type', 'mainboard') }}"><span class="ico"><x-icon name="building" /></span><span>Mainboard IPOs<small>NSE & BSE main board issues</small></span></a>
                    <a href="{{ route('ipos.type', 'sme') }}"><span class="ico"><x-icon name="briefcase" /></span><span>SME IPOs<small>NSE Emerge & BSE SME</small></span></a>
                    <a href="{{ route('ipos.calendar') }}"><span class="ico"><x-icon name="calendar" /></span><span>IPO Calendar<small>Open, close & listing dates</small></span></a>
                    <a href="{{ route('ipos.report-card') }}"><span class="ico"><x-icon name="trophy" /></span><span>IPO Report Card<small>Year-wise listing performance</small></span></a>
                    <a href="{{ route('compare') }}"><span class="ico"><x-icon name="columns" /></span><span>Compare IPOs<small>Up to 3 side by side</small></span></a>
                </div>
            </div>
            <a href="{{ route('ipos.gmp') }}" class="{{ request()->routeIs('ipos.gmp') ? 'active' : '' }}"><span class="live"></span> Live GMP</a>
            <a href="{{ route('news.index') }}" class="{{ request()->routeIs('news.index', 'news.show') ? 'active' : '' }}">News</a>
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
            <a href="{{ route('watchlist') }}" class="icon-btn watch-link" aria-label="My watchlist" title="My watchlist">
                <x-icon name="star" />
                <span class="watch-count" data-watch-count hidden>0</span>
            </a>
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
        <a href="{{ route('ipos.report-card') }}"><x-icon name="trophy" /> IPO Report Card</a>
        <a href="{{ route('compare') }}"><x-icon name="columns" /> Compare IPOs</a>
        <a href="{{ route('news.index') }}"><x-icon name="newspaper" /> Market News</a>
        <a href="{{ route('calculators.index') }}"><x-icon name="calculator" /> Calculators</a>
        <a href="{{ route('watchlist') }}"><x-icon name="star" /> My Watchlist</a>
        <a href="{{ route('about') }}"><x-icon name="info" /> About</a>
    </nav>
    <button type="button" class="btn btn-gold btn-block" data-install-app hidden style="margin-top:18px"><x-icon name="download" :size="16" /> Install IPO Darbaar app</button>
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
        <div class="footer-subscribe" id="subscribe">
            <div>
                <h3>Get the IPO Darbaar digest</h3>
                <p>Open IPOs, GMP moves, allotment and listing dates in your inbox. Free, no spam, one-click unsubscribe.</p>
            </div>
            <x-subscribe-form />
        </div>
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
                    <li><a href="{{ route('ipos.report-card') }}">IPO Report Card</a></li>
                    <li><a href="{{ route('compare') }}">Compare IPOs</a></li>
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
            <span><a href="{{ route('about') }}">About</a> · <a href="{{ route('disclaimer') }}">Disclaimer</a> · <a href="{{ route('privacy') }}">Privacy</a> · <a href="{{ route('sitemap') }}">Sitemap</a></span>
        </div>
    </div>
</footer>

<div class="toast" data-toast role="status" aria-live="polite"></div>

<script>window.IPO_DARBAAR = { suggestUrl: @json(route('ipos.suggest')), searchUrl: @json(route('ipos.index')) };</script>
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
