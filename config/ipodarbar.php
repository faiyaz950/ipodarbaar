<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IPO data source
    |--------------------------------------------------------------------------
    | IPOs are synced into the local database (see `php artisan ipo:sync`)
    | so the site can filter by status, search and paginate quickly.
    */
    'ipo_api' => [
        'url' => env('IPO_API_URL', 'https://www.finowings.com/ipo-sync.php'),
        'page_url' => env('IPO_PAGE_URL', 'https://www.finowings.com/ipo/'),   // + slug: source IPO page (registrar)
        'key' => env('IPO_API_KEY'),
        'pull' => (bool) env('IPO_API_PULL', true),     // false when the relay pushes data instead
        'push_token' => env('IPO_PUSH_TOKEN'),          // enables POST /internal/ipo-push
        'page_size' => 50,          // API maximum per request
        'recent_pages' => 3,        // pages refreshed on a quick sync (latest 150 IPOs)
        'stale_after' => 20,        // minutes before a background refresh is triggered
    ],

    /*
    | The scheduler writes a heartbeat every minute; while it is younger than this many
    | minutes, page requests skip the fallback IPO sync.
    */
    'scheduler' => [
        'heartbeat_grace' => 15,
    ],

    /*
    | Old or alternate hostnames (comma separated) that 301-redirect to APP_URL,
    | e.g. "www.example.in,old.example.com".
    */
    'redirect_hosts' => array_filter(array_map('trim', explode(',', (string) env('REDIRECT_HOSTS', '')))),

    /*
    | IndexNow: tells Bing, Yandex and other participating search engines about new
    | and updated pages within minutes. Set INDEXNOW_KEY to any 8–128 character
    | hex/alphanumeric string to enable it.
    */
    // Full-page cache for anonymous visitors (App\Support\PageCache).
    'page_cache' => [
        'enabled' => (bool) env('PAGE_CACHE_ENABLED', env('APP_ENV') === 'production'),
        'store' => env('PAGE_CACHE_STORE', 'pages'),
        'ttl' => (int) env('PAGE_CACHE_TTL', 300),
    ],

    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
        'endpoint' => 'https://api.indexnow.org/indexnow',
    ],

    /*
    |--------------------------------------------------------------------------
    | Market news source
    |--------------------------------------------------------------------------
    */
    'news_api' => [
        'url' => env('NEWS_API_URL', 'https://courses.finowings.com/api/market-news-list'),
        'fresh_for' => 300,         // seconds a cached response is considered fresh
        'stale_for' => 3600,        // seconds a stale response may still be served
    ],

    // "title"/"description" are the search copy for each category page (/{slug}-news).
    'news_categories' => [
        ['id' => 6, 'name' => 'Trending', 'slug' => 'trending', 'color' => '#D93A3A',
            'title' => 'Trending Business & Market News Today',
            'description' => 'Trending business, economy and market news from India and the world, explained in short. Updated through the day in English and Hinglish.'],
        ['id' => 9, 'name' => 'IPO', 'slug' => 'ipo', 'color' => '#B8841F',
            'title' => 'IPO News Today: Latest IPO News & Updates in India',
            'description' => 'Latest IPO news in India: new IPO announcements, subscription status, GMP, allotment and listing updates for mainboard and SME IPOs, updated daily.'],
        ['id' => 1, 'name' => 'Stock Market', 'slug' => 'stock-market', 'color' => '#2F5BEA',
            'title' => 'Stock Market News Today: Sensex, Nifty & Share Market Updates',
            'description' => 'Share market news today: Sensex, Nifty, stocks in focus, results and market-moving updates from India, in short and simple language.'],
        ['id' => 2, 'name' => 'Trading', 'slug' => 'trading', 'color' => '#6D4AE0',
            'title' => 'Trading News Today: Market Moves & Trading Updates',
            'description' => 'Trading news for Indian markets: F&O, stocks to watch, sector moves and key levels, explained in short.'],
        ['id' => 4, 'name' => 'Investment', 'slug' => 'investment', 'color' => '#0B9B6A',
            'title' => 'Investment News: Mutual Funds, SIP & Personal Finance',
            'description' => 'Investment news for Indian investors: mutual funds, SIPs, fixed deposits, tax and personal finance updates, explained simply.'],
        ['id' => 3, 'name' => 'Commodity', 'slug' => 'commodity', 'color' => '#C06A12',
            'title' => 'Commodity News Today: Gold, Silver & Crude Oil Updates',
            'description' => 'Commodity market news today: gold, silver, crude oil and base metal price moves and what is driving them.'],
        ['id' => 5, 'name' => 'Crypto', 'slug' => 'crypto', 'color' => '#C2338A',
            'title' => 'Crypto News Today: Bitcoin & Cryptocurrency Updates',
            'description' => 'Crypto news today: Bitcoin, Ethereum and cryptocurrency market updates, regulation and tax news for Indian investors.'],
    ],

    /*
    | Registrars (RTAs) with their public allotment-status pages, plus the exchanges.
    */
    'registrars' => [
        ['name' => 'KFin Technologies', 'url' => 'https://ipostatus.kfintech.com/'],
        ['name' => 'MUFG Intime (Link Intime)', 'url' => 'https://in.mpms.mufg.com/Initial_Offer/public-issues.html'],
        ['name' => 'Bigshare Services', 'url' => 'https://ipo.bigshareonline.com/IPO_Status.html'],
        ['name' => 'Skyline Financial Services', 'url' => 'https://www.skylinerta.com/ipo.php'],
        ['name' => 'Maashitla Securities', 'url' => 'https://maashitla.com/allotment-status/public-issues'],
        ['name' => 'Purva Sharegistry', 'url' => 'https://www.purvashare.com/investor-service/ipo-query'],
        ['name' => 'Cameo Corporate Services', 'url' => 'https://ipo.cameoindia.com/'],
        ['name' => 'Integrated Registry Management', 'url' => 'https://www.integratedregistry.in/RegistrarsToSTA.aspx?type=ipo'],
    ],

    'allotment_links' => [
        ['name' => 'BSE', 'url' => 'https://www.bseindia.com/investors/appli_check'],
        ['name' => 'NSE', 'url' => 'https://www.nseindia.com/invest/check-trades-bids-verify-ipo-bids'],
    ],

    /*
    | Outgoing mail: shared hosting SMTP servers cap messages per hour, so digest
    | emails are queued and released at most this many per hour.
    */
    'mail' => [
        'hourly_limit' => (int) env('MAIL_HOURLY_LIMIT', 250),
    ],

    /*
    | Defaults for admin-editable settings (Admin → Settings). A value saved in the
    | database overrides the default here. Keys are read as "ads.enabled", etc.
    */
    'settings' => [
        'ads' => [
            'enabled' => (bool) env('ADS_ENABLED', false),
            'client' => env('ADSENSE_CLIENT'),
            'slots' => [
                'home_top' => null,
                'home_mid' => null,
                'ipo_after_gmp' => null,
                'ipo_sidebar' => null,
                'article' => null,
                'calculator' => null,
                'list' => null,
            ],
        ],
        'analytics' => [
            'ga4_id' => env('GA4_MEASUREMENT_ID'),
            'cf_token' => env('CF_ANALYTICS_TOKEN'),
        ],
        'brokers' => [],
        'telegram' => [
            'digest' => true,
            'gmp' => true,
            'new_ipo' => true,
        ],
        'email' => [
            'digest' => true,
        ],
        'seo' => [
            'google_verification' => env('GOOGLE_SITE_VERIFICATION'),
            'bing_verification' => env('BING_SITE_VERIFICATION'),
        ],
        'social' => [
            'telegram' => env('SOCIAL_TELEGRAM_URL'),
            'whatsapp' => env('SOCIAL_WHATSAPP_URL'),
            'x' => null,
            'youtube' => null,
            'instagram' => null,
            'facebook' => null,
            'linkedin' => null,
        ],
    ],

    /*
    | SEBI application limits used for investor-category calculations.
    */
    'limits' => [
        'retail_max' => 200000,
        'shni_max' => 1000000,
    ],
];
