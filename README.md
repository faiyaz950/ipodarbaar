# IPO Darbaar

A Laravel + Blade site covering every Indian IPO: live GMP, open, upcoming, listing-soon and listed IPOs, an IPO calendar, market news with an Inshorts-style **Shorts** feed (English / Hinglish), IPO guides and 31 calculators.

Plain HTML, CSS and vanilla JS. **No npm build step**, so it can be deployed on any PHP host.

## Features

| Area | Pages |
| --- | --- |
| Home | Live GMP ticker, hero with IPOs closing soon, stats, IPO dashboard tabs (Open / Upcoming / Listing Soon / Recently Listed, filter Mainboard or SME), this week's IPO calendar, latest news, calculators |
| IPOs | `/ipo` (all IPOs + search), keyword hubs `/current-ipo`, `/upcoming-ipo`, `/ipo-allotment-status`, `/recently-listed-ipo`, `/mainboard-ipo`, `/sme-ipo` (old `?status=` / `/ipo/type/*` URLs 301 here), `/upcoming-sme-ipo`, `/ipo-listing-today`, `/ipo/{slug}` detail (key facts, T+3 timeline, GMP panel and trend, sentiment poll, lot size table, issue structure, financials, KPIs, anchor lock-ins, documents), `/ipo-gmp` (plus `/mainboard-ipo-gmp`, `/sme-ipo-gmp`), `/ipo-calendar` |
| IPO data | `/ipo-compare?ipos=a,b,c` (up to 3 side by side), `/ipo-report-card` and `/ipo/{year}` (funds raised, listing gains, best/worst listings) |
| Engagement | `/ipo-alerts` (Telegram, WhatsApp channel, email, watchlist), `/watchlist` (stored in the browser), installable PWA with offline page, email digest with double opt-in, Telegram channel auto-posts |
| News | `/news` and `/{category}-news` (e.g. `/ipo-news`) with pagination, `/news/{id}/{slug}` article with English/Hinglish toggle, `/shorts` swipeable feed with infinite scroll |
| Calculators | `/calculators`: IPO GMP, listing profit, application amount, allotment chance, HNI funding cost, buyback acceptance, P/E ratio, SIP (with step-up), lumpsum, SWP, CAGR, stock average, inflation, FD, RD, PPF, EMI, brokerage, capital gains tax |
| Guides | `/ipo-guide`: how to apply, GMP, allotment status, how allotment works, SME vs mainboard, tax on listing gains, NRI investing, glossary; `/about`, `/contact`, `/editorial-policy` |
| Growth | SEO (see below), 1200×630 share cards, GA4 / Cloudflare analytics, AdSense slots with `/ads.txt`, sponsored broker links |
| Admin | `/admin`: IPO overrides that survive syncs, company & financials editor, Settings (ads, analytics, brokers, notification switches), System (health checks, test email/Telegram) |

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

## Hosting

Production runs on BigRock shared cPanel hosting with MySQL and a single cron entry. See [DEPLOY-BIGROCK.md](DEPLOY-BIGROCK.md) for the full setup and `deploy/bigrock/deploy.sh` for updates. Secrets (SMTP password, Telegram token, API keys) live in `.env`; ads, analytics and broker links are managed in Admin → Settings.

## How the data flows

- **IPOs** are synced into the `ipos` table by `App\Services\IpoSyncService`. It cleans up names, parses the price band, lot size and exchange from the description, and assigns unique slugs. Status (open, upcoming, listing soon, listed) is worked out from the dates in Asia/Kolkata time.
  - `php artisan ipo:sync`: quick refresh of the latest 150 IPOs
  - `php artisan ipo:sync --full`: all pages
- **Scheduler** (recommended). Add one cron entry on the server:
  ```
  * * * * * cd /path/to/ipodarbar && php artisan schedule:run >> /dev/null 2>&1
  ```
  This runs a quick sync every 15 minutes and a full sync daily at 03:30.
  The same cron also posts to Telegram, sends the email digest and runs a short queue worker for mail.
- **Without cron**, the `RefreshIpoData` middleware triggers a quick sync after the response once data is more than 20 minutes old. While the scheduler heartbeat is fresh, it does nothing.
- **News** is fetched live from the News API and cached with stale-while-revalidate (fresh for 5 minutes, served stale up to 1 hour). Article HTML is sanitised to an allow-list before rendering.

Tunable values live in `config/ipodarbar.php` (page size, refresh interval, news categories and colours, SEBI limits).

## SEO

- **Titles follow the IPO's stage** (`App\Support\IpoSeo`): "GMP, Date, Price Band" before it opens, "GMP Today & Subscription Status" while open, "Allotment Status" after it closes, "Listing Price" once listed. Each IPO page has a visible FAQ with matching `FAQPage` schema.
- **Keyword hubs** (`App\Support\IpoHubs`) target searches such as "upcoming IPO", "current IPO", "IPO allotment status" and "SME IPO". Filtered views are `noindex`.
- **Structured data**: Organization + WebSite search box, BreadcrumbList, WebPage, CollectionPage/ItemList, FAQPage, NewsArticle, Article, WebApplication (calculators), DefinedTermSet (glossary).
- **Internal links**: news articles link the IPOs they mention (`App\Support\IpoLinker`); IPO pages show that company's news.
- **Sitemaps**: `/sitemap.xml` indexes pages, IPOs (with share-card images), a Google News sitemap (last 48 hours) and a news archive. `/robots.txt` points to it.
- **IndexNow**: set `INDEXNOW_KEY` (8–128 letters, digits or dashes). The key is served at `/indexnow-key.txt`, and `seo:indexnow` runs every 30 minutes to tell Bing and others about changed pages. Google ignores IndexNow and reads the sitemaps.
- **Search Console / Bing Webmaster**: paste the verification meta tag in Admin → Settings → Search engines. Add your social profile URLs there too; they appear in the Organization schema.
- Fonts are self-hosted WOFF2 files (`public/fonts`) for faster first paint.

## Code map

```
app/Models/Ipo.php                  status scopes, GMP maths, lot table, T+3 timeline
app/Services/IpoSyncService.php     API → DB sync and parsing
app/Services/NewsService.php        news fetch, cache, normalise, sanitise
app/Services/IpoStatsService.php    year-wise report card numbers (cached)
app/Services/IpoDigestBuilder.php   content for the email digest and Telegram posts
app/Services/TelegramService.php    Telegram Bot API client
app/Services/ShareCardService.php   GD-rendered social share cards
app/Support/Calculators.php         calculator registry (copy, formulas, FAQs, SEO titles)
app/Support/IpoSeo.php              stage-aware IPO titles, descriptions and FAQs
app/Support/IpoHubs.php             keyword hub pages (current, upcoming, allotment, …)
app/Support/Guides.php              IPO guide articles registry
app/Services/IndexNowService.php    IndexNow submissions
app/Support/Settings.php            admin-editable settings with config fallbacks
app/Http/Controllers/*              Home, Ipo, Compare, IpoYear, Watchlist, Vote, Subscription, News, Calculator, Seo, Page
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
