<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
    }

    public function test_robots_txt_blocks_admin_and_internal_endpoints_and_points_to_the_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /ipo/search/suggest')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_ads_txt_publishes_the_adsense_seller_line(): void
    {
        app(Settings::class)->set(['ads.client' => 'ca-pub-1234567890123456']);

        $this->get('/ads.txt')->assertOk()
            ->assertSeeText('google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0');
    }

    public function test_ads_txt_returns_404_until_adsense_is_configured(): void
    {
        $this->get('/ads.txt')->assertNotFound();
    }

    public function test_sitemap_index_lists_the_page_ipo_and_news_sitemaps(): void
    {
        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('<sitemapindex', false)
            ->assertSee(route('sitemaps.show', 'pages'))
            ->assertSee(route('sitemaps.show', 'ipos'))
            ->assertSee(route('sitemaps.show', 'news'));
    }

    public function test_sitemaps_list_ipos_and_news_articles(): void
    {
        $ipo = Ipo::factory()->create();
        Http::fake(['courses.finowings.com/*' => Http::response(['success' => true, 'data' => [[
            'id' => 77, 'headline' => 'SEBI tweaks IPO rules', 'news_detail' => '<p>Body</p>', 'date' => '2026-09-23',
            'created_at' => '2026-09-23 10:00:00', 'category' => ['id' => 9, 'name' => 'IPO', 'slug' => 'ipo'],
        ]], 'total' => 1, 'page' => 1, 'per_page' => 100, 'has_more' => false])]);

        $this->get('/sitemaps/ipos.xml')->assertOk()->assertSee($ipo->url());
        $this->get('/sitemaps/news.xml')->assertOk()->assertSee(route('news.show', ['id' => 77, 'slug' => 'sebi-tweaks-ipo-rules']));
        $this->get('/sitemaps/unknown.xml')->assertNotFound();
    }
}
