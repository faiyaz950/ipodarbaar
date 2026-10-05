<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Ipo;
use App\Support\IpoInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SeoBoostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-05 11:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
    }

    private function openIpo(array $extra = []): Ipo
    {
        $ipo = Ipo::factory()->create($extra + [
            'name' => 'EverestIMS Technologies', 'slug' => 'everestims-technologies-ipo', 'type' => 'sme', 'price_min' => 80, 'price' => 85, 'gmp' => 42,
            'issue_size' => 48.46, 'open_date' => '2026-09-29', 'close_date' => '2026-10-06', 'listing_date' => '2026-10-09',
            'subscription_total' => 3.2, 'subscription_retail' => 2.5, 'subscription_qib' => 5.1, 'subscription_nii' => 4.0, 'subscription_updated_at' => '2026-10-05 10:45:00',
            'about' => 'EverestIMS Technologies IPO is an SME IPO by EverestIMS Technologies Limited, which is a software enterprise that offers SaaS products. More.',
        ]);
        foreach ([['FY24', '2024-03-31', 45.62, 10.83], ['FY25', '2025-03-31', 55.1, 11.9], ['FY26', '2026-03-31', 65.91, 13.14]] as [$period, $end, $revenue, $pat]) {
            $ipo->financials()->create(['period' => $period, 'period_end' => $end, 'revenue' => $revenue, 'pat' => $pat, 'net_worth' => 40, 'borrowings' => 4]);
        }

        return $ipo;
    }

    public function test_ipo_page_has_a_fitting_title_keyword_h1_and_unique_analysis(): void
    {
        $ipo = $this->openIpo();

        $this->get($ipo->url())->assertOk()
            // Too long for the brand suffix, so the title is the bare search phrase.
            ->assertSee('<title>EverestIMS Technologies IPO GMP Today &amp; Subscription Status</title>', false)
            ->assertSee('GMP Today &amp; Subscription Status</span></h1>', false)
            ->assertSee('Subscribed 3.20x (retail 2.50x).')
            ->assertSee('EverestIMS Technologies IPO analysis: what the numbers say')
            ->assertSee('EverestIMS Technologies is a software enterprise that offers SaaS products.')
            ->assertSee('a compound annual growth of 20.2%')
            ->assertSee('By Mon, 5 Oct, 10:45 AM, the issue was subscribed 3.20x overall (QIB 5.10x, NII 4.00x and retail 2.50x).')
            ->assertSee('What is the subscription status of EverestIMS Technologies IPO?');
    }

    public function test_short_titles_keep_the_brand_and_thin_pages_skip_the_analysis(): void
    {
        $ipo = Ipo::factory()->create(['name' => 'SRIT India', 'open_date' => '2026-10-01', 'close_date' => '2026-10-07', 'about' => null, 'gmp' => null]);

        $this->get($ipo->url())->assertOk()
            ->assertSee('<title>SRIT India IPO GMP Today &amp; Subscription Status | IPO Darbaar</title>', false)
            ->assertDontSee('IPO analysis: what the numbers say');
    }

    public function test_listed_ipo_analysis_compares_listing_with_gmp(): void
    {
        $ipo = Ipo::factory()->create([
            'name' => 'Moneyview', 'price' => 34, 'listing_gmp' => 12.75, 'listing_price' => 55, 'listing_close' => 53.88,
            'open_date' => '2026-09-24', 'close_date' => '2026-09-28', 'listing_date' => '2026-10-01', 'subscription_total' => 98.46,
            'subscription_updated_at' => '2026-09-28 19:00:00', 'issue_size' => 1091.68,
        ]);

        $this->assertSame(
            'Moneyview listed at ₹55 on 1 Oct 2026, +61.8% against the issue price of ₹34. The last GMP before listing had pointed to about ₹46.75, so it opened 24.3 points above the grey market estimate. It closed the first day at ₹53.88 (+58.5%).',
            IpoInsights::listing($ipo)
        );
        $this->get($ipo->url())->assertOk()
            ->assertSee('Listing Price, Gain &amp; Allotment</span></h1>', false)
            ->assertSee('The issue closed subscribed 98.46x overall.')
            ->assertSee('What was the listing price of Moneyview IPO?');
    }

    public function test_hub_pages_read_well_when_empty_and_never_cut_names(): void
    {
        $this->get('/upcoming-ipo')->assertOk()
            ->assertSee('added as soon as they are announced')
            ->assertDontSee('0 mainboard and SME IPOs');

        foreach (['Alpha Tech Industries', 'Beta Foods Limited', 'Gamma Retail Ventures', 'Delta Infra Projects'] as $name) {
            Ipo::factory()->create(['name' => $name, 'close_date' => '2026-09-30', 'listing_date' => '2026-10-05']);
        }
        $html = $this->get('/ipo-listing-today')->assertOk()->getContent();
        preg_match('/<meta name="description" content="([^"]*)"/', $html, $m);
        $this->assertStringNotContainsString('...', $m[1]);
        $this->assertMatchesRegularExpression('/and \d more\./', $m[1]);
    }

    public function test_news_like_blog_posts_are_news_articles_in_the_news_sitemap(): void
    {
        $daily = BlogPost::factory()->create(['category' => 'daily', 'title' => 'IPO Today (5 Oct 2026): 8 IPOs Closing', 'published_at' => now()->subHours(2)]);
        $explainer = BlogPost::factory()->create(['category' => 'explainers', 'title' => 'Rights issue explained', 'published_at' => now()->subHours(3)]);
        $old = BlogPost::factory()->create(['category' => 'daily', 'title' => 'IPO Today old', 'published_at' => now()->subDays(5)]);

        $this->get($daily->url())->assertOk()->assertSee('"@type":"NewsArticle"', false);
        $this->get($explainer->url())->assertOk()->assertSee('"@type":"BlogPosting"', false);

        $this->get('/sitemaps/news.xml')->assertOk()
            ->assertSee('<loc>'.$daily->url().'</loc>', false)
            ->assertSee('<news:title>IPO Today (5 Oct 2026): 8 IPOs Closing</news:title>', false)
            ->assertSee('<loc>'.$explainer->url().'</loc>', false)
            ->assertDontSee($old->url());
    }
}
