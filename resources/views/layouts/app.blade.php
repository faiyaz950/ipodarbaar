<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @inject('siteSettings', 'App\Support\Settings')
    @php
        // Section content is already escaped by @section, so it must not be escaped again.
        $pageTitle = trim($__env->yieldContent('title_full'))
            ?: (trim($__env->yieldContent('title')) ? trim($__env->yieldContent('title')).' | IPO Darbaar' : e('IPO Darbaar: IPO GMP Today, Upcoming IPO & Allotment Status'));
        $pageDescription = trim($__env->yieldContent('description')) ?: e('Live IPO GMP today, upcoming and current IPO list, allotment status, listing gains, IPO news and 28 free calculators for mainboard and SME IPOs in India.');
        $currentPage = request()->integer('page');
        $canonical = trim($__env->yieldContent('canonical')) ?: url()->current().($currentPage > 1 ? '?page='.$currentPage : '');
        $robots = trim($__env->yieldContent('robots')) ?: 'index, follow';
        if (! str_contains($robots, 'noindex')) {
            $robots .= ', max-image-preview:large, max-snippet:-1, max-video-preview:-1';
        }
    @endphp
    <title>{!! $pageTitle !!}</title>
    <meta name="description" content="{!! $pageDescription !!}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta name="theme-color" content="#0A1633">
    <meta name="robots" content="{{ $robots }}">
    @if ($siteSettings->get('seo.google_verification'))<meta name="google-site-verification" content="{{ $siteSettings->get('seo.google_verification') }}">@endif
    @if ($siteSettings->get('seo.bing_verification'))<meta name="msvalidate.01" content="{{ $siteSettings->get('seo.bing_verification') }}">@endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="IPO Darbaar">
    <meta property="og:site_name" content="IPO Darbaar">
    <meta property="og:locale" content="en_IN">
    <meta property="og:title" content="{!! trim($__env->yieldContent('title')) ?: $pageTitle !!}">
    <meta property="og:description" content="{!! $pageDescription !!}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ trim($__env->yieldContent('og_image')) ?: asset('images/brand/og-logo.png') }}">
    <meta name="twitter:card" content="{{ trim($__env->yieldContent('og_image')) ? 'summary_large_image' : 'summary' }}">
    {{-- Google Search shows favicons in multiples of 48px, so the larger sizes come first. --}}
    <link rel="icon" href="{{ asset('favicon-192.png') }}" sizes="192x192" type="image/png">
    <link rel="icon" href="{{ asset('favicon-96.png') }}" sizes="96x96" type="image/png">
    <link rel="icon" href="{{ asset('favicon-48.png') }}" sizes="48x48" type="image/png">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" sizes="32x32" type="image/png">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preload" href="{{ asset('fonts/inter-normal-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/fraunces-normal-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('assets/css/fonts.css') }}?v={{ filemtime(public_path('assets/css/fonts.css')) }}">
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
        @php
            $sameAs = collect(\App\Http\Controllers\Admin\SettingsController::SOCIAL_NETWORKS)->keys()
                ->map(fn (string $network) => $siteSettings->get('social.'.$network))->filter()->values()->all();
            $organization = array_filter([
                '@type' => 'Organization',
                '@id' => route('home').'#org',
                'name' => 'IPO Darbaar',
                'alternateName' => ['IPO Darbar', 'IPODarbaar', 'ipodarbaar.in'],
                'url' => route('home'),
                'logo' => ['@type' => 'ImageObject', 'url' => asset('icons/icon-512.png'), 'width' => 512, 'height' => 512],
                'description' => 'IPO Darbaar tracks every mainboard and SME IPO in India: live GMP, subscription, allotment and listing dates, IPO news and investment calculators.',
                'sameAs' => $sameAs ?: null,
                'contactPoint' => config('mail.from.address') ? ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => config('mail.from.address'), 'areaServed' => 'IN', 'availableLanguage' => ['English', 'Hindi']] : null,
            ]);
        @endphp
        <x-jsonld :data="[
            '@context' => 'https://schema.org',
            '@graph' => [
                $organization,
                [
                    '@type' => 'WebSite', '@id' => route('home').'#website', 'name' => 'IPO Darbaar', 'alternateName' => ['IPO Darbar', 'IPODarbaar', 'ipodarbaar.in'], 'url' => route('home'), 'inLanguage' => 'en-IN', 'publisher' => ['@id' => route('home').'#org'],
                    'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('ipos.index').'?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
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
            <a href="{{ route('ipos.listing-today') }}">Listing Today</a>
            <a href="{{ route('ipos.calendar') }}">IPO Calendar</a>
            <a href="{{ route('alerts') }}">IPO Alerts</a>
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
            <div class="dd {{ request()->routeIs('ipos.index', 'ipos.current', 'ipos.upcoming', 'ipos.upcoming-sme', 'ipos.allotment', 'ipos.listing-today', 'ipos.listed', 'ipos.sme', 'ipos.mainboard', 'ipos.show', 'ipos.calendar', 'ipos.year', 'ipos.report-card', 'compare') ? 'active' : '' }}">
                <button type="button" aria-haspopup="true">IPOs <x-icon name="chevron-down" :size="15" /></button>
                <div class="dd-menu">
                    <a href="{{ route('ipos.current') }}"><span class="ico"><x-icon name="zap" /></span><span>Current IPOs<small>Open for subscription today</small></span></a>
                    <a href="{{ route('ipos.upcoming') }}"><span class="ico"><x-icon name="calendar" /></span><span>Upcoming IPOs<small>Opening in the coming days</small></span></a>
                    <a href="{{ route('ipos.allotment') }}"><span class="ico"><x-icon name="ticket" /></span><span>Allotment Status<small>Check by PAN, registrar links</small></span></a>
                    <a href="{{ route('ipos.listing-today') }}"><span class="ico"><x-icon name="rocket" /></span><span>Listing Today<small>Expected listing price & GMP</small></span></a>
                    <a href="{{ route('ipos.index') }}"><span class="ico"><x-icon name="layers" /></span><span>All IPOs<small>Complete IPO list</small></span></a>
                    <a href="{{ route('ipos.mainboard') }}"><span class="ico"><x-icon name="building" /></span><span>Mainboard IPOs<small>NSE & BSE main board issues</small></span></a>
                    <a href="{{ route('ipos.sme') }}"><span class="ico"><x-icon name="briefcase" /></span><span>SME IPOs<small>NSE Emerge & BSE SME</small></span></a>
                    <a href="{{ route('ipos.calendar') }}"><span class="ico"><x-icon name="calendar" /></span><span>IPO Calendar<small>Open, close & listing dates</small></span></a>
                    <a href="{{ route('ipos.report-card') }}"><span class="ico"><x-icon name="trophy" /></span><span>IPO Report Card<small>Year-wise listing performance</small></span></a>
                    <a href="{{ route('compare') }}"><span class="ico"><x-icon name="columns" /></span><span>Compare IPOs<small>Up to 3 side by side</small></span></a>
                </div>
            </div>
            <a href="{{ route('ipos.gmp') }}" class="{{ request()->routeIs('ipos.gmp', 'ipos.gmp.*') ? 'active' : '' }}"><span class="live"></span> Live GMP</a>
            <a href="{{ route('news.index') }}" class="{{ request()->routeIs('news.index', 'news.show') ? 'active' : '' }}">News</a>
            @php
                $toolGroups = [
                    'IPO calculators' => [
                        ['calculators.show', 'ipo-gmp', 'trending-up', 'GMP → Listing Price'],
                        ['calculators.show', 'ipo-application', 'wallet', 'Application Amount'],
                        ['calculators.show', 'sme-ipo-investment', 'briefcase', 'SME Min Investment'],
                        ['calculators.show', 'ipo-allotment-chance', 'ticket', 'Allotment Chance'],
                        ['calculators.show', 'ipo-profit', 'rocket', 'Listing Gain'],
                        ['calculators.show', 'ipo-net-proceeds', 'banknote', 'Net Proceeds'],
                        ['calculators.show', 'hni-funding-cost', 'hand-coins', 'HNI Funding Cost'],
                    ],
                    'Tax & investing' => [
                        ['calculators.show', 'capital-gains', 'badge-percent', 'Capital Gains Tax'],
                        ['calculators.show', 'buyback-acceptance-ratio', 'repeat', 'Buyback Acceptance'],
                        ['calculators.show', 'dividend-yield', 'coins', 'Dividend Yield'],
                        ['calculators.show', 'pe-ratio', 'scale', 'P/E Ratio'],
                        ['calculators.show', 'brokerage', 'receipt', 'Brokerage'],
                        ['calculators.show', 'sip', 'sprout', 'SIP'],
                    ],
                    'Trackers & dashboards' => [
                        ['portfolio', null, 'briefcase', 'IPO Portfolio Tracker'],
                        ['ipos.sme-dashboard', null, 'bar-chart', 'SME IPO Dashboard'],
                        ['compare', null, 'columns', 'Compare IPOs'],
                        ['ipos.allotment', null, 'check-circle', 'Allotment Status'],
                        ['ipos.listing-today', null, 'calendar', 'Listing Today'],
                        ['ipos.report-card', null, 'trophy', 'IPO Report Card'],
                    ],
                ];
            @endphp
            <div class="dd dd-mega {{ request()->routeIs('calculators.*', 'portfolio', 'ipos.sme-dashboard') ? 'active' : '' }}">
                <button type="button" aria-haspopup="true">Tools <x-icon name="chevron-down" :size="15" /></button>
                <div class="dd-menu">
                    @foreach ($toolGroups as $group => $links)
                        <div class="dd-group">
                            <span class="dd-head">{{ $group }}</span>
                            @foreach ($links as [$routeName, $param, $icon, $label])
                                <a href="{{ $param ? route($routeName, $param) : route($routeName) }}"><x-icon :name="$icon" :size="16" /> {{ $label }}</a>
                            @endforeach
                        </div>
                    @endforeach
                    <a class="dd-all" href="{{ route('calculators.index') }}"><x-icon name="grid" :size="16" /> Browse all {{ count(\App\Support\Calculators::all()) }} calculators <x-icon name="arrow-right" :size="15" /></a>
                </div>
            </div>
            <a href="{{ route('guides.index') }}" class="{{ request()->routeIs('guides.*') ? 'active' : '' }}">IPO Guide</a>
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
        <a href="{{ route('ipos.current') }}" class="sub">Current IPOs</a>
        <a href="{{ route('ipos.upcoming') }}" class="sub">Upcoming IPOs</a>
        <a href="{{ route('ipos.allotment') }}" class="sub">Allotment Status</a>
        <a href="{{ route('ipos.listing-today') }}" class="sub">Listing Today</a>
        <a href="{{ route('ipos.mainboard') }}" class="sub">Mainboard IPOs</a>
        <a href="{{ route('ipos.sme') }}" class="sub">SME IPOs</a>
        <a href="{{ route('ipos.gmp') }}"><x-icon name="trending-up" /> Live GMP</a>
        <a href="{{ route('ipos.gmp.sme') }}" class="sub">SME IPO GMP</a>
        <a href="{{ route('ipos.gmp.mainboard') }}" class="sub">Mainboard IPO GMP</a>
        <a href="{{ route('ipos.calendar') }}"><x-icon name="calendar" /> IPO Calendar</a>
        <a href="{{ route('ipos.report-card') }}"><x-icon name="trophy" /> IPO Report Card</a>
        <a href="{{ route('compare') }}"><x-icon name="columns" /> Compare IPOs</a>
        <a href="{{ route('news.index') }}"><x-icon name="newspaper" /> Market News</a>
        <a href="{{ route('calculators.index') }}"><x-icon name="calculator" /> Calculators &amp; Tools</a>
        <a href="{{ route('portfolio') }}" class="sub">IPO Portfolio Tracker</a>
        <a href="{{ route('ipos.sme-dashboard') }}" class="sub">SME IPO Dashboard</a>
        <a href="{{ route('guides.index') }}"><x-icon name="file-text" /> IPO Guide</a>
        <a href="{{ route('watchlist') }}"><x-icon name="star" /> My Watchlist</a>
        <a href="{{ route('alerts') }}"><x-icon name="bell" /> IPO Alerts</a>
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
                    <li><a href="{{ route('ipos.current') }}">Current IPOs</a></li>
                    <li><a href="{{ route('ipos.upcoming') }}">Upcoming IPOs</a></li>
                    <li><a href="{{ route('ipos.allotment') }}">IPO Allotment Status</a></li>
                    <li><a href="{{ route('ipos.listing-today') }}">IPO Listing Today</a></li>
                    <li><a href="{{ route('ipos.listed') }}">Recently Listed IPOs</a></li>
                    <li><a href="{{ route('ipos.year', now()->year) }}">IPO List {{ now()->year }}</a></li>
                    <li><a href="{{ route('ipos.mainboard') }}">Mainboard IPOs</a></li>
                    <li><a href="{{ route('ipos.sme') }}">SME IPOs</a></li>
                    <li><a href="{{ route('ipos.upcoming-sme') }}">Upcoming SME IPOs</a></li>
                    <li><a href="{{ route('ipos.gmp') }}">IPO GMP Today</a></li>
                    <li><a href="{{ route('ipos.gmp.mainboard') }}">Mainboard IPO GMP</a></li>
                    <li><a href="{{ route('ipos.gmp.sme') }}">SME IPO GMP</a></li>
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
                        <li><a href="{{ route('news.category', $c['slug']) }}">{{ $c['name'] }} News</a></li>
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
                    <li><a href="{{ route('portfolio') }}">IPO Portfolio Tracker</a></li>
                    <li><a href="{{ route('ipos.sme-dashboard') }}">SME IPO Dashboard</a></li>
                    <li><a href="{{ route('calculators.index') }}">All Calculators</a></li>
                </ul>
                <h4 style="margin-top:22px">IPO Guide</h4>
                <ul>
                    <li><a href="{{ route('guides.show', 'how-to-apply-for-ipo') }}">How to apply for IPO</a></li>
                    <li><a href="{{ route('guides.show', 'what-is-ipo-gmp') }}">What is IPO GMP</a></li>
                    <li><a href="{{ route('guides.show', 'how-to-check-ipo-allotment-status') }}">Check allotment status</a></li>
                    <li><a href="{{ route('guides.index') }}">All guides</a></li>
                </ul>
            </div>
        </div>
        <p class="footer-disclaimer">
            <strong>Disclaimer:</strong> IPO Darbaar is an information platform and is not registered with SEBI as an investment adviser or research analyst. Grey Market Premium (GMP) figures are unofficial and indicative only. Nothing on this site is a recommendation to buy, sell or subscribe to any security. Investments in securities markets are subject to market risks; read all offer documents carefully before investing.
        </p>
        <div class="footer-bottom">
            <span>© {{ now()->year }} IPO Darbaar. All rights reserved.</span>
            <span><a href="{{ route('about') }}">About</a> · <a href="{{ route('contact') }}">Contact</a> · <a href="{{ route('editorial-policy') }}">Editorial Policy</a> · <a href="{{ route('alerts') }}">IPO Alerts</a> · <a href="{{ route('disclaimer') }}">Disclaimer</a> · <a href="{{ route('privacy') }}">Privacy</a> · <a href="{{ route('sitemap') }}">Sitemap</a></span>
        </div>
    </div>
</footer>

<div class="toast" data-toast role="status" aria-live="polite"></div>

<script>window.IPO_DARBAAR = { suggestUrl: @json(route('ipos.suggest')), searchUrl: @json(route('ipos.index')) };</script>
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
