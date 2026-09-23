# IPO Darbaar

A Laravel + Blade site covering every Indian IPO: live GMP, open, upcoming, listing-soon and listed IPOs, an IPO calendar, market news with an Inshorts-style **Shorts** feed (English / Hinglish), and 16 calculators.

Plain HTML, CSS and vanilla JS. **No npm build step**, so it can be deployed on any PHP host.

## Features

| Area | Pages |
| --- | --- |
| Home | Live GMP ticker, hero with IPOs closing soon, stats, IPO dashboard tabs (Open / Upcoming / Listing Soon / Recently Listed, filter Mainboard or SME), this week's IPO calendar, latest news, calculators |
| IPOs | `/ipo` (filter by status, type, search), `/ipo/type/mainboard`, `/ipo/type/sme`, `/ipo/{slug}` detail (key facts, T+3 timeline, GMP panel, lot size table), `/ipo-gmp`, `/ipo-calendar` |
| News | `/news` (category chips + pagination), `/news/{id}/{slug}` article with English/Hinglish toggle, `/shorts` swipeable feed with infinite scroll |
| Calculators | `/calculators`: IPO GMP, listing profit, application amount, allotment chance, SIP (with step-up), lumpsum, SWP, CAGR, stock average, inflation, FD, RD, PPF, EMI, brokerage, capital gains tax |
| Extras | Light/dark theme, instant IPO search (press `/`), share buttons, SEO meta + JSON-LD, `/sitemap.xml`, mobile layout |

## Setup

```bash
composer install
cp .env.example .env        # then set IPO_API_KEY
php artisan key:generate
php artisan migrate
php artisan ipo:sync --full # first import of all IPOs (~30s)
php artisan serve
```

### Environment

```
APP_NAME="IPO Darbaar"
APP_TIMEZONE=Asia/Kolkata
IPO_API_URL=https://www.finowings.com/ipo-sync.php
IPO_API_KEY=your-key
NEWS_API_URL=https://courses.finowings.com/api/market-news-list
```

SQLite works out of the box. For MySQL, set `DB_CONNECTION=mysql` and the `DB_*` values, then run `php artisan migrate`.

## How the data flows

- **IPOs** are synced into the `ipos` table by `App\Services\IpoSyncService`. It cleans up names, parses the price band, lot size and exchange from the description, and assigns unique slugs. Status (open, upcoming, listing soon, listed) is worked out from the dates in Asia/Kolkata time.
  - `php artisan ipo:sync`: quick refresh of the latest 150 IPOs
  - `php artisan ipo:sync --full`: all pages
- **Scheduler** (recommended). Add one cron entry on the server:
  ```
  * * * * * cd /path/to/ipodarbar && php artisan schedule:run >> /dev/null 2>&1
  ```
  This runs a quick sync every 15 minutes and a full sync daily at 03:30.
- **Without cron**, the `RefreshIpoData` middleware triggers a quick sync after the response once data is more than 20 minutes old.
- **News** is fetched live from the News API and cached with stale-while-revalidate (fresh for 5 minutes, served stale up to 1 hour). Article HTML is sanitised to an allow-list before rendering.

Tunable values live in `config/ipodarbar.php` (page size, refresh interval, news categories and colours, SEBI limits).

## Code map

```
app/Models/Ipo.php                  status scopes, GMP maths, lot table, T+3 timeline
app/Services/IpoSyncService.php     API → DB sync and parsing
app/Services/NewsService.php        news fetch, cache, normalise, sanitise
app/Support/Calculators.php         calculator registry (copy, formulas, FAQs)
app/Http/Controllers/*              Home, Ipo, News, Calculator, Page
resources/views/                    Blade layouts, components and pages
public/assets/css/app.css           design system (light and dark tokens)
public/assets/js/app.js             theme, search, tabs, share, language toggle
public/assets/js/shorts.js          Shorts feed
public/assets/js/calculators.js     calculator engine and all formulas
```

## Tests

```bash
php artisan test
```

## Disclaimer

GMP is unofficial and indicative only. Dates after an issue closes are tentative estimates based on SEBI's T+3 timeline. Nothing on the site is investment advice.
