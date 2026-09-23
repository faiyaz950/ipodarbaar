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
        'key' => env('IPO_API_KEY'),
        'page_size' => 50,          // API maximum per request
        'recent_pages' => 3,        // pages refreshed on a quick sync (latest 150 IPOs)
        'stale_after' => 20,        // minutes before a background refresh is triggered
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

    'news_categories' => [
        ['id' => 6, 'name' => 'Trending', 'slug' => 'trending', 'color' => '#D93A3A'],
        ['id' => 9, 'name' => 'IPO', 'slug' => 'ipo', 'color' => '#B8841F'],
        ['id' => 1, 'name' => 'Stock Market', 'slug' => 'stock-market', 'color' => '#2F5BEA'],
        ['id' => 2, 'name' => 'Trading', 'slug' => 'trading', 'color' => '#6D4AE0'],
        ['id' => 4, 'name' => 'Investment', 'slug' => 'investment', 'color' => '#0B9B6A'],
        ['id' => 3, 'name' => 'Commodity', 'slug' => 'commodity', 'color' => '#C06A12'],
        ['id' => 5, 'name' => 'Crypto', 'slug' => 'crypto', 'color' => '#C2338A'],
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
    | SEBI application limits used for investor-category calculations.
    */
    'limits' => [
        'retail_max' => 200000,
        'shni_max' => 1000000,
    ],
];
