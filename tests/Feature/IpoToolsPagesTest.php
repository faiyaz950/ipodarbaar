<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpoToolsPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 24 Sep 2026.
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        // A fresh sync timestamp keeps the RefreshIpoData middleware from calling the API.
        cache()->forever('ipo:synced_at', now()->toIso8601String());

        Http::fake(['courses.finowings.com/*' => Http::response([
            'success' => true, 'data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'has_more' => false,
        ])]);
    }

    public function test_listing_today_shows_todays_listings_the_week_ahead_and_recent_gains(): void
    {
        Ipo::factory()->create(['name' => 'Today Co', 'slug' => 'today-co-ipo', 'open_date' => '2026-09-17', 'close_date' => '2026-09-21', 'listing_date' => '2026-09-24']);
        Ipo::factory()->create(['name' => 'Week Co', 'slug' => 'week-co-ipo', 'open_date' => '2026-09-21', 'close_date' => '2026-09-23', 'listing_date' => '2026-09-28']);
        Ipo::factory()->create(['name' => 'Past Co', 'slug' => 'past-co-ipo', 'open_date' => '2026-09-14', 'close_date' => '2026-09-16', 'listing_date' => '2026-09-21', 'listing_price' => 150]);
        Ipo::factory()->create(['name' => 'Old Co', 'slug' => 'old-co-ipo', 'open_date' => '2026-08-01', 'close_date' => '2026-08-05', 'listing_date' => '2026-08-10']);

        $this->get('/ipo-listing-today')->assertOk()
            ->assertSee('<title>IPO Listing Today (24 Sep 2026): Listing Price &amp; GMP | IPO Darbaar</title>', false)
            ->assertSee('1 IPO lists on the stock exchanges today (24 Sep 2026): Today Co.')
            // ₹120 issue price + ₹15 GMP.
            ->assertSeeInOrder(['IPOs Listing Today', 'Today Co', '₹135', '+12.5%', 'After 10 AM'], false)
            ->assertSeeInOrder(['Upcoming IPO Listings This Week', 'Week Co', 'Mon, 28 Sep'], false)
            ->assertSeeInOrder(['Recently Listed IPOs', 'Past Co', '₹150', '+25.0% actual'], false)
            ->assertDontSee('old-co-ipo" class="co-name"', false)
            ->assertSee('"@type":"FAQPage"', false);
    }

    public function test_listing_today_names_the_next_listing_when_none_is_due_today(): void
    {
        Ipo::factory()->create(['name' => 'Week Co', 'slug' => 'week-co-ipo', 'open_date' => '2026-09-21', 'close_date' => '2026-09-23', 'listing_date' => '2026-09-28']);

        $this->get('/ipo-listing-today')->assertOk()
            ->assertSee('No IPO lists today (24 Sep 2026). The next listing is Week Co on Mon, 28 Sep.');
    }

    public function test_board_gmp_pages_list_only_their_board(): void
    {
        Ipo::factory()->open()->create(['name' => 'Big Board', 'slug' => 'big-board-ipo']);
        Ipo::factory()->open()->sme()->create(['name' => 'Small Shop', 'slug' => 'small-shop-ipo']);

        $this->get('/sme-ipo-gmp')->assertOk()
            ->assertSee('<title>SME IPO GMP Today (24 Sep 2026): Live Grey Market Premium | IPO Darbaar</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('ipos.gmp.sme').'">', false)
            ->assertSee('<h1>SME IPO GMP Today</h1>', false)
            ->assertSee('small-shop-ipo" class="co-name"', false)
            ->assertDontSee('big-board-ipo" class="co-name"', false)
            ->assertSee('Why do SME IPO GMPs swing so much?');

        $this->get('/mainboard-ipo-gmp')->assertOk()
            ->assertSee('<h1>Mainboard IPO GMP Today</h1>', false)
            ->assertSee('big-board-ipo" class="co-name"', false)
            ->assertDontSee('small-shop-ipo" class="co-name"', false);
    }

    public function test_upcoming_sme_hub_lists_only_upcoming_sme_ipos(): void
    {
        Ipo::factory()->upcoming()->sme()->create(['name' => 'Soon Small', 'slug' => 'soon-small-ipo']);
        Ipo::factory()->upcoming()->create(['name' => 'Soon Big', 'slug' => 'soon-big-ipo']);
        Ipo::factory()->open()->sme()->create(['name' => 'Open Small', 'slug' => 'open-small-ipo']);

        $this->get('/upcoming-sme-ipo')->assertOk()
            ->assertSee('<title>Upcoming SME IPO List September 2026: Dates, Price &amp; GMP | IPO Darbaar</title>', false)
            ->assertSee('soon-small-ipo" class="co-name"', false)
            ->assertDontSee('soon-big-ipo" class="co-name"', false)
            ->assertDontSee('open-small-ipo" class="co-name"', false);

        // The list is fixed: a status in the URL does not change it.
        $this->get('/upcoming-sme-ipo?status=open')->assertOk()
            ->assertSee('soon-small-ipo" class="co-name"', false)
            ->assertDontSee('open-small-ipo" class="co-name"', false);
    }

    public function test_alerts_page_offers_email_and_the_channels_set_in_admin(): void
    {
        $this->get('/ipo-alerts')->assertOk()
            ->assertSee('<title>Free IPO Alerts on Email: Never Miss an IPO Date | IPO Darbaar</title>', false)
            ->assertSee('action="'.route('subscribe').'"', false)
            ->assertDontSee('Join on Telegram');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.settings.update'), ['social' => [
                'telegram' => 'https://t.me/ipodarbaar',
                'whatsapp' => 'https://whatsapp.com/channel/ipodarbaar',
            ]])
            ->assertRedirect(route('admin.settings'));

        $this->get('/ipo-alerts')->assertOk()
            ->assertSee('<title>Free IPO Alerts on Telegram, WhatsApp &amp; Email: Never Miss an IPO Date | IPO Darbaar</title>', false)
            ->assertSee('href="https://t.me/ipodarbaar"', false)
            ->assertSee('href="https://whatsapp.com/channel/ipodarbaar"', false);
    }

    public function test_contact_and_editorial_policy_pages(): void
    {
        config(['mail.from.address' => 'noreply@ipodarbaar.in', 'ipodarbar.contact_email' => 'hello@ipodarbaar.in']);

        $this->get('/contact')->assertOk()
            ->assertSee('href="mailto:hello@ipodarbaar.in"', false)
            ->assertDontSee('noreply@ipodarbaar.in')
            ->assertSee('"@type":"ContactPage"', false);

        $this->get('/about')->assertOk()
            ->assertSee('href="mailto:hello@ipodarbaar.in"', false);

        $this->get('/editorial-policy')->assertOk()
            ->assertSee('No investment advice')
            ->assertSee(route('contact'));
    }

    public function test_new_calculators_have_pages_with_schema(): void
    {
        foreach (['hni-funding-cost', 'pe-ratio', 'buyback-acceptance-ratio', 'sme-ipo-investment', 'ipo-net-proceeds', 'dividend-yield'] as $slug) {
            $this->get(route('calculators.show', $slug))->assertOk()
                ->assertSee('data-calc="'.$slug.'"', false)
                ->assertSee('"@type":"WebApplication"', false)
                ->assertSee('"@type":"FAQPage"', false);
        }
    }

    public function test_sme_dashboard_summarises_the_sme_market(): void
    {
        Ipo::factory()->open()->sme()->create(['name' => 'Small Open', 'slug' => 'small-open-ipo', 'gmp' => 30]);
        Ipo::factory()->upcoming()->sme()->create(['name' => 'Small Soon', 'slug' => 'small-soon-ipo', 'gmp' => 12]);
        Ipo::factory()->sme()->create(['name' => 'Small Big', 'slug' => 'small-big-ipo', 'issue_size' => 95,
            'open_date' => '2026-03-02', 'close_date' => '2026-03-04', 'listing_date' => '2026-03-09']);
        Ipo::factory()->open()->create(['name' => 'Board Co', 'slug' => 'board-co-ipo', 'gmp' => 90]);

        $this->get('/sme-ipo-dashboard')->assertOk()
            ->assertSee('<title>SME IPO Dashboard 2026: GMP, Open &amp; Upcoming SME IPOs | IPO Darbaar</title>', false)
            ->assertSeeInOrder(['Open now', '<b>1</b>', 'Upcoming', '<b>1</b>'], false)
            // GMP ₹30 and ₹12 on ₹120: 25% and 10%, averaging 17.5%.
            ->assertSee('+17.5%')
            ->assertSeeInOrder(['Highest SME IPO GMP Today', 'Small Open IPO</a>', 'Small Soon IPO</a>'], false)
            // 3 of the 4 IPOs opening in 2026 are SME.
            ->assertSee('75% of all IPOs')
            ->assertSeeInOrder(['Largest SME IPOs of 2026', 'Small Big', '₹95 Cr'], false)
            // The mainboard IPO stays out of the dashboard's lists (the site-wide ticker still shows it).
            ->assertDontSee('Board Co IPO</a>', false)
            ->assertDontSee('board-co-ipo" class="co-name"', false);
    }

    public function test_portfolio_page_and_price_endpoint(): void
    {
        $ipo = Ipo::factory()->open()->sme()->create(['name' => 'Small Open', 'slug' => 'small-open-ipo', 'gmp' => 30]);

        $this->get('/ipo-portfolio')->assertOk()
            ->assertSee('data-prices="'.route('portfolio.prices').'"', false)
            ->assertSee('"@type":"WebApplication"', false)
            ->assertSee('assets/js/portfolio.js', false);

        $this->getJson('/ipo-portfolio/prices?slugs=small-open-ipo,Bad%20Slug,missing-ipo')->assertOk()
            ->assertExactJson(['small-open-ipo' => [
                'name' => 'Small Open',
                'url' => $ipo->url(),
                'type' => 'SME',
                'status' => 'open',
                'statusLabel' => $ipo->statusLabel(),
                'price' => 120,
                'lot' => 1200,
                'gmp' => 30,
                'listingPrice' => null,
                'listingDate' => $ipo->listing_date->toDateString(),
            ]]);

        $this->get('/robots.txt')->assertSee('Disallow: /ipo-portfolio/prices');
    }

    public function test_tools_menu_and_calculator_search(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<div class="dd dd-mega', false)
            ->assertSee(route('portfolio'))
            ->assertSee(route('ipos.sme-dashboard'))
            ->assertSee(route('calculators.show', 'ipo-net-proceeds'));

        $this->get('/calculators')->assertOk()
            ->assertSee('data-calc-search', false)
            ->assertSee('data-calc-card="ipo net proceeds calculator', false);
    }

    public function test_home_page_shows_top_gmp_and_listings_this_week(): void
    {
        Ipo::factory()->open()->create(['name' => 'Modest Gain', 'slug' => 'modest-gain-ipo', 'gmp' => 6]);
        Ipo::factory()->open()->create(['name' => 'Hot Pick', 'slug' => 'hot-pick-ipo', 'gmp' => 60]);
        Ipo::factory()->create(['name' => 'Week Co', 'slug' => 'week-co-ipo', 'open_date' => '2026-09-21', 'close_date' => '2026-09-23', 'listing_date' => '2026-09-28']);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Top GMP Today', 'Hot Pick', '▲ 50.0%', 'Modest Gain', '▲ 5.0%'], false)
            // Both open IPOs list on 30 Sep and Week Co on 28 Sep, all within the next 7 days.
            ->assertSee('<span class="kpi">3</span><span class="lbl" style="display:block">Listing this week</span>', false);
    }
}
