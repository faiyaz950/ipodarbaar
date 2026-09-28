<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\User;
use App\Support\Guides;
use App\Support\IpoLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    use RefreshDatabase;

    private string $newsHeadline = 'Alpha Tech IPO subscribed 12 times';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        // A fresh sync timestamp keeps the RefreshIpoData middleware from calling the API.
        cache()->forever('ipo:synced_at', now()->toIso8601String());

        Http::fake([
            // Closures, so a test can change the fixture headline before the request.
            'courses.finowings.com/api/market-news-list/*' => fn () => Http::response(['success' => true, 'data' => $this->newsRow()]),
            'courses.finowings.com/api/market-news-list*' => fn () => Http::response([
                'success' => true, 'data' => [$this->newsRow()], 'total' => 1, 'page' => 1, 'per_page' => 20, 'has_more' => false,
            ]),
            'api.indexnow.org/*' => Http::response(null, 200),
        ]);
    }

    public function test_ipo_hubs_list_the_right_ipos_with_keyword_titles_and_faq_schema(): void
    {
        Ipo::factory()->open()->create(['name' => 'Open Co', 'slug' => 'open-co-ipo']);
        Ipo::factory()->upcoming()->create(['name' => 'Soon Co', 'slug' => 'soon-co-ipo']);

        $this->get('/current-ipo')->assertOk()
            ->assertSee('<title>Current IPO Open Today: Live GMP &amp; Subscription | IPO Darbaar</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('ipos.current').'">', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('open-co-ipo" class="co-name"', false)
            ->assertDontSee('soon-co-ipo" class="co-name"', false);

        $this->get('/upcoming-ipo')->assertOk()
            ->assertSee('<title>Upcoming IPO List September 2026: Dates, Price &amp; GMP | IPO Darbaar</title>', false)
            ->assertSee('soon-co-ipo" class="co-name"', false)
            ->assertDontSee('open-co-ipo" class="co-name"', false);
    }

    public function test_home_page_leads_with_the_brand_for_brand_searches(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<title>IPO Darbaar: IPO GMP Today, Upcoming IPO &amp; Allotment Status</title>', false)
            ->assertSee('<h1 class="hero-h1">IPO Darbaar:', false)
            ->assertSee('"alternateName":["IPO Darbar","IPODarbaar","ipodarbaar.in"]', false);
    }

    public function test_upcoming_hub_splits_boards_and_lists_this_weeks_openings(): void
    {
        Ipo::factory()->upcoming()->create(['name' => 'Big Board', 'slug' => 'big-board-ipo', 'issue_size' => 900]);
        Ipo::factory()->upcoming()->sme()->create(['name' => 'Small Shop', 'slug' => 'small-shop-ipo', 'open_date' => '2026-10-20', 'close_date' => '2026-10-22']);

        $this->get('/upcoming-ipo')->assertOk()
            ->assertSee('As of 24 Sep 2026, 2 IPOs are lined up: 1 mainboard and 1 SME. The largest is Big Board at ₹900 crore.')
            ->assertSeeInOrder(['Upcoming Mainboard IPOs', 'big-board-ipo" class="co-name"', 'Upcoming SME IPOs', 'small-shop-ipo" class="co-name"'], false)
            ->assertSeeInOrder(['Upcoming IPOs This Week (24 Sep – 30 Sep)', 'Big Board IPO</a>', 'opens Sun, 27 Sep'], false)
            ->assertDontSee('Small Shop IPO</a>', false);

        // A filtered view keeps the plain single table.
        $this->get('/upcoming-ipo?type=sme')->assertOk()->assertDontSee('Upcoming SME IPOs');
    }

    public function test_current_hub_lists_ipos_closing_today(): void
    {
        Ipo::factory()->create(['name' => 'Last Call', 'slug' => 'last-call-ipo', 'open_date' => '2026-09-22', 'close_date' => '2026-09-24', 'subscription_total' => 12.5]);
        Ipo::factory()->open()->create(['name' => 'Still Open', 'slug' => 'still-open-ipo']);

        $this->get('/current-ipo')->assertOk()
            ->assertSeeInOrder(['IPOs Closing Today (24 Sep)', 'Last Call IPO</a>', 'closes today at 5 PM, subscribed 12.50x so far'], false)
            ->assertDontSee('Still Open IPO</a>', false)
            ->assertSee('Subscribed 12.50x');
    }

    public function test_live_gmp_page_ranks_the_highest_gmp_and_shows_allotment_dates(): void
    {
        Ipo::factory()->open()->create(['name' => 'Modest Gain', 'slug' => 'modest-gain-ipo', 'gmp' => 6]);
        Ipo::factory()->open()->create(['name' => 'Hot Pick', 'slug' => 'hot-pick-ipo', 'gmp' => 60]);
        Ipo::factory()->upcoming()->create(['name' => 'No Premium', 'slug' => 'no-premium-ipo', 'gmp' => null]);

        $this->get('/ipo-gmp')->assertOk()
            ->assertSee('<title>Live IPO GMP Today (24 Sep 2026): Grey Market Premium | IPO Darbaar</title>', false)
            ->assertSee('<h1>Live IPO GMP Today</h1>', false)
            ->assertSeeInOrder(['Highest IPO GMP Today', 'Hot Pick IPO</a>', '(50.0%)', 'Modest Gain IPO</a>', '(5.0%)'], false)
            ->assertDontSee('No Premium IPO</a>:', false)
            // Opened 23 Sep, closes 25 Sep (Fri): allotment T+1 is Mon 28 Sep.
            ->assertSee('<td>28 Sep</td>', false)
            // No IPO has subscription figures, so the column stays hidden.
            ->assertDontSee('<th class="r">Subscription</th>', false);
    }

    public function test_ipo_tables_show_lot_size_and_minimum_investment(): void
    {
        Ipo::factory()->open()->create(['name' => 'Lot Co', 'slug' => 'lot-co-ipo']);

        // ₹120 × 125 shares = ₹15,000 for one lot.
        $this->get('/current-ipo')->assertOk()->assertSee('Lot 125 · min ₹15,000');
    }

    public function test_filtered_hubs_are_not_indexed(): void
    {
        $this->get('/sme-ipo')->assertOk()->assertSee('<meta name="robots" content="index, follow, max-image-preview:large', false);
        $this->get('/sme-ipo?status=upcoming')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_allotment_hub_lists_closed_and_just_listed_ipos_with_registrar_links(): void
    {
        Ipo::factory()->create([
            'name' => 'Closed Co', 'slug' => 'closed-co-ipo', 'registrar' => 'KFin Technologies',
            'open_date' => '2026-09-19', 'close_date' => '2026-09-23', 'listing_date' => '2026-09-26',
        ]);
        Ipo::factory()->listed()->create(['name' => 'Fresh Listing', 'slug' => 'fresh-listing-ipo']);
        Ipo::factory()->create([
            'name' => 'Old Listing', 'slug' => 'old-listing-ipo',
            'open_date' => '2026-08-01', 'close_date' => '2026-08-05', 'listing_date' => '2026-08-08',
        ]);

        $this->get('/ipo-allotment-status')->assertOk()
            ->assertSee('Closed Co IPO allotment status')
            ->assertSee('https://ipostatus.kfintech.com/', false)
            ->assertSee('Fresh Listing IPO allotment status')
            ->assertDontSee('Old Listing IPO allotment status');
    }

    public function test_old_filter_urls_redirect_permanently_to_the_hubs(): void
    {
        $this->get('/ipo?status=open')->assertStatus(301)->assertRedirect(route('ipos.current'));
        $this->get('/ipo?status=closed')->assertStatus(301)->assertRedirect(route('ipos.allotment'));
        $this->get('/ipo/type/sme?status=upcoming&page=2')->assertStatus(301)
            ->assertRedirect(route('ipos.sme', ['status' => 'upcoming', 'page' => 2]));
        $this->get('/ipo/type/mainboard')->assertStatus(301)->assertRedirect(route('ipos.mainboard'));

        // Searches stay on the list page.
        $this->get('/ipo?q=alpha&status=open')->assertOk();
    }

    public function test_ipo_page_title_follows_the_ipo_lifecycle(): void
    {
        $stages = [
            'Soon Co IPO GMP, Date, Price Band &amp; Lot Size' => Ipo::factory()->upcoming()->create(['name' => 'Soon Co', 'slug' => 'soon-co-ipo']),
            'Open Co IPO GMP Today &amp; Subscription Status' => Ipo::factory()->open()->create(['name' => 'Open Co', 'slug' => 'open-co-ipo']),
            'Closed Co IPO Allotment Status &amp; GMP' => Ipo::factory()->create([
                'name' => 'Closed Co', 'slug' => 'closed-co-ipo',
                'open_date' => '2026-09-19', 'close_date' => '2026-09-23', 'listing_date' => '2026-09-26',
            ]),
            'Listed Co IPO Listing Price &amp; Allotment Status' => Ipo::factory()->listed()->create(['name' => 'Listed Co', 'slug' => 'listed-co-ipo', 'listing_price' => 150]),
        ];

        foreach ($stages as $title => $ipo) {
            $this->get($ipo->url())->assertOk()
                ->assertSee('<title>'.$title.' | IPO Darbaar</title>', false)
                ->assertSee('<meta name="description" content="'.$ipo->name.' IPO', false);
        }
    }

    public function test_ipo_page_has_a_visible_faq_with_matching_schema_and_no_event_markup(): void
    {
        $ipo = Ipo::factory()->open()->create(['name' => 'Open Co', 'slug' => 'open-co-ipo', 'registrar' => 'KFin Technologies']);

        $this->get($ipo->url())->assertOk()
            ->assertSee('What is Open Co IPO GMP today?')
            ->assertSee('How to check Open Co IPO allotment status?')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"WebPage"', false)
            ->assertDontSee('"@type":"Event"', false)
            ->assertSee('id="gmp"', false)
            ->assertSee('<nav class="card toc"', false);
    }

    public function test_news_categories_have_their_own_urls(): void
    {
        $this->get('/news?category=ipo')->assertStatus(301)->assertRedirect(route('news.category', 'ipo'));
        $this->get('/ipo-news')->assertOk()
            ->assertSee('<title>IPO News Today: Latest IPO News &amp; Updates in India | IPO Darbaar</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('news.category', 'ipo').'">', false);
        $this->get('/unknown-news')->assertNotFound();
    }

    public function test_news_articles_link_ipos_they_mention_and_carry_news_article_schema(): void
    {
        $ipo = Ipo::factory()->open()->create(['name' => 'Alpha Tech', 'slug' => 'alpha-tech-ipo']);

        $this->get(route('news.show', ['id' => 77, 'slug' => 'alpha-tech-ipo-subscribed-12-times']))->assertOk()
            ->assertSee('<a href="'.$ipo->url().'" title="Alpha Tech IPO">Alpha Tech</a>', false)
            ->assertSee('IPOs in this story')
            ->assertSee('"@type":"NewsArticle"', false)
            ->assertSee('<meta property="og:type" content="article">', false);
    }

    public function test_shouting_news_headlines_are_shown_in_title_case_without_changing_the_url(): void
    {
        $this->newsHeadline = 'NSE FINALLY GOES PUBLIC! A ₹22,562 CRORE MILESTONE FOR INDIA';
        // The URL is still built from the feed's original headline.
        $url = route('news.show', ['id' => 77, 'slug' => Str::slug($this->newsHeadline)]);

        $this->get($url)->assertOk()
            ->assertSee('<title>NSE Finally Goes Public! A ₹22,562 Crore Milestone for India | IPO Darbaar</title>', false)
            ->assertSee('<h1 data-lang-en>NSE Finally Goes Public! A ₹22,562 Crore Milestone for India</h1>', false)
            ->assertDontSee('FINALLY GOES PUBLIC');

        $this->get('/sitemaps/news.xml')->assertOk()
            ->assertSee('<loc>'.$url.'</loc>', false)
            ->assertSee('<news:title>NSE Finally Goes Public! A ₹22,562 Crore Milestone for India</news:title>', false);
    }

    public function test_ipo_linker_links_each_ipo_once_and_leaves_existing_links_alone(): void
    {
        $alpha = Ipo::factory()->open()->create(['name' => 'Alpha Tech', 'slug' => 'alpha-tech-ipo']);
        $beta = Ipo::factory()->upcoming()->create(['name' => 'Beta Foods', 'slug' => 'beta-foods-ipo']);

        $result = IpoLinker::link(
            '<p><a href="/elsewhere">Beta Foods</a> and Alpha Tech rallied. Alpha Tech again, then Beta Foods.</p>',
            Ipo::all()
        );

        $this->assertSame(1, substr_count($result['html'], 'href="'.$alpha->url().'"'));
        $this->assertSame(1, substr_count($result['html'], 'href="'.$beta->url().'"'));
        $this->assertStringContainsString('<a href="/elsewhere">Beta Foods</a>', $result['html']);
        $this->assertStringEndsWith('then <a href="'.$beta->url().'" title="Beta Foods IPO">Beta Foods</a>.</p>', $result['html']);
        $this->assertEqualsCanonicalizing([$alpha->id, $beta->id], $result['ipos']->pluck('id')->all());
    }

    public function test_guides_render_with_article_schema(): void
    {
        $this->get('/ipo-guide')->assertOk()->assertSee('How to Apply for an IPO Online');

        foreach (array_keys(Guides::all()) as $slug) {
            $this->get(route('guides.show', $slug))->assertOk()
                ->assertSee('"@type":"Article"', false)
                ->assertSee('"@type":"BreadcrumbList"', false);
        }

        $this->get('/ipo-guide/not-a-guide')->assertNotFound();
    }

    public function test_calculator_pages_carry_web_application_and_faq_schema(): void
    {
        $this->get('/calculators/sip')->assertOk()
            ->assertSee('<title>SIP Calculator: Calculate SIP Returns Online', false)
            ->assertSee('"@type":"WebApplication"', false)
            ->assertSee('"@type":"FAQPage"', false);
    }

    public function test_sitemaps_include_hubs_guides_news_categories_and_google_news(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('sitemaps.show', 'news-archive'));

        $this->get('/sitemaps/pages.xml')->assertOk()
            ->assertSee(route('ipos.current'))
            ->assertSee(route('ipos.allotment'))
            ->assertSee(route('guides.show', 'what-is-ipo-gmp'))
            ->assertSee(route('news.category', 'ipo'));

        $this->get('/sitemaps/news.xml')->assertOk()
            ->assertSee('<news:name>IPO Darbaar</news:name>', false)
            ->assertSee('<news:title>Alpha Tech IPO subscribed 12 times</news:title>', false);
    }

    public function test_news_archive_sitemap_survives_a_cache_that_does_not_unserialize_objects(): void
    {
        // Production's file cache refuses to unserialize objects (cache.serializable_classes = false).
        config(['cache.stores.array.serialize' => true, 'cache.serializable_classes' => false]);
        Cache::forgetDriver('array');

        // The first request fills the cache; the second reads it back.
        for ($request = 1; $request <= 2; $request++) {
            $this->get('/sitemaps/news-archive.xml')->assertOk()
                ->assertSee(route('news.show', ['id' => 77, 'slug' => 'alpha-tech-ipo-subscribed-12-times']))
                ->assertSee('<lastmod>2026-09-23T11:35:20+05:30</lastmod>', false);
        }
    }

    public function test_indexnow_key_file_is_served_only_when_configured(): void
    {
        $this->get('/indexnow-key.txt')->assertNotFound();

        config(['ipodarbar.indexnow.key' => 'a1b2c3d4e5f6a7b8']);
        $this->get('/indexnow-key.txt')->assertOk()->assertSeeText('a1b2c3d4e5f6a7b8');
    }

    public function test_indexnow_command_submits_changed_ipo_pages(): void
    {
        config(['ipodarbar.indexnow.key' => 'a1b2c3d4e5f6a7b8']);
        $ipo = Ipo::factory()->open()->create();

        $this->artisan('seo:indexnow')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.indexnow.org/indexnow'
            && $request['key'] === 'a1b2c3d4e5f6a7b8'
            && $request['keyLocation'] === route('indexnow.key')
            && in_array($ipo->url(), $request['urlList'], true)
            && in_array(route('ipos.current'), $request['urlList'], true));
    }

    public function test_indexnow_command_does_nothing_without_a_key(): void
    {
        Ipo::factory()->open()->create();

        $this->artisan('seo:indexnow')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_search_console_verification_and_social_profiles_from_settings(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.settings.update'), [
                'google_verification' => '<meta name="google-site-verification" content="abcDEF123_-xyz0987" />',
                'social' => ['telegram' => 'https://t.me/ipodarbaar'],
            ])
            ->assertRedirect(route('admin.settings'));

        $this->get('/')->assertOk()
            ->assertSee('<meta name="google-site-verification" content="abcDEF123_-xyz0987">', false)
            ->assertSee('"sameAs":["https://t.me/ipodarbaar"]', false);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.settings.update'), ['bing_verification' => 'not-a-code'])
            ->assertSessionHasErrors('bing_verification');
    }

    /**
     * @return array<string, mixed>
     */
    private function newsRow(): array
    {
        return [
            'id' => 77,
            'headline' => $this->newsHeadline,
            'news_detail' => '<p>The Alpha Tech IPO was subscribed 12 times on the final day of bidding.</p>',
            'date' => '2026-09-23',
            'image' => 'https://courses.finowings.com/storage/market_news/a.avif',
            'category_id' => 9,
            'category' => ['id' => 9, 'name' => 'IPO', 'slug' => 'ipo'],
            'status' => 'active',
            'created_at' => '2026-09-23 11:35:20',
            'updated_at' => '2026-09-23 11:35:20',
        ];
    }
}
