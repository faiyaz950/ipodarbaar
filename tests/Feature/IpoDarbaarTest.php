<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Services\IpoSyncService;
use App\Services\NewsService;
use App\Support\Calculators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class IpoDarbaarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        config(['ipodarbar.ipo_api.key' => 'test-key']);

        Http::fake([
            'www.finowings.com/ipo-sync.php*' => Http::response([
                'status' => 'success',
                'pagination' => ['limit' => 50, 'offset' => 0, 'total' => 4, 'has_more' => false],
                'data' => [
                    $this->ipoRow(1, 'Alpha Tech IPO: Date, Price, GMP & Review | Finowings', 'IPO', '2026-09-23', '2026-09-25', '2026-09-30', '120', '15',
                        'Alpha Tech IPO opens Sep 23-25, price band ₹110-120/share, lists on NSE & BSE. Check lot size (125 shares).'),
                    $this->ipoRow(2, 'Beta Foods IPO: Date, Price & Review | Finowings', 'SME IPO', '2026-09-29', '2026-10-01', '2026-10-06', '--', '--',
                        'Beta Foods IPO opens Sep 29-Oct 1, price band ₹85-90/share, ₹40 crore NSE SME issue.'),
                    $this->ipoRow(3, 'Gamma Steel IPO: Date & GMP | Finowings', 'IPO', '2026-09-17', '2026-09-21', '2026-09-26', '300', '-5', ''),
                    $this->ipoRow(4, 'Delta Old IPO - Review | Finowings', 'SME IPO', '2024-01-10', '2024-01-12', '2024-01-17', '50', '--', ''),
                ],
            ]),
            'courses.finowings.com/api/market-news-list/*' => Http::response([
                'success' => true,
                'data' => $this->newsRow(501),
            ]),
            'courses.finowings.com/api/market-news-list*' => Http::response([
                'success' => true,
                'data' => [$this->newsRow(501), $this->newsRow(502)],
                'total' => 2, 'page' => 1, 'per_page' => 20, 'has_more' => false,
            ]),
        ]);

        $this->artisan('ipo:sync', ['--full' => true])->assertSuccessful();
    }

    public function test_sync_normalizes_api_rows(): void
    {
        $this->assertSame(4, Ipo::count());

        $alpha = Ipo::where('api_id', 1)->first();
        $this->assertSame('Alpha Tech', $alpha->name);
        $this->assertSame('alpha-tech-ipo', $alpha->slug);
        $this->assertSame('mainboard', $alpha->type);
        $this->assertSame(110.0, $alpha->price_min);
        $this->assertSame(120.0, $alpha->price);
        $this->assertSame(125, $alpha->lot_size);
        $this->assertSame('BSE, NSE', $alpha->exchange);
        $this->assertSame(135.0, $alpha->estListingPrice());
        $this->assertSame(12.5, $alpha->gmpPercent());

        $beta = Ipo::where('api_id', 2)->first();
        $this->assertSame('sme', $beta->type);
        $this->assertSame(90.0, $beta->price, 'Missing price falls back to the upper band in the description');
        $this->assertNull($beta->gmp);
        $this->assertSame('NSE SME', $beta->exchange);
    }

    public function test_status_buckets_follow_dates(): void
    {
        $this->assertSame(['Alpha Tech'], Ipo::open()->pluck('name')->all());
        $this->assertSame(['Beta Foods'], Ipo::upcoming()->pluck('name')->all());
        $this->assertSame(['Gamma Steel'], Ipo::closed()->pluck('name')->all());
        $this->assertSame(['Delta Old'], Ipo::listed()->pluck('name')->all());

        foreach (Ipo::all() as $ipo) {
            $bucket = collect(['open', 'upcoming', 'closed', 'listed'])->first(fn ($s) => Ipo::{$s}()->whereKey($ipo->id)->exists());
            $this->assertSame($bucket, $ipo->status(), "Scope and accessor disagree for {$ipo->name}");
        }

        $this->assertSame('Closes tomorrow', Ipo::where('api_id', 1)->first()->countdown());
        $this->assertSame('Opens in 5 days', Ipo::where('api_id', 2)->first()->countdown());
    }

    public function test_lot_table_uses_sebi_limits(): void
    {
        $rows = collect(Ipo::where('api_id', 1)->first()->lotTable())->keyBy('category');

        // ₹120 × 125 = ₹15,000 per lot → 13 lots max for retail (≤ ₹2L)
        $this->assertSame(1, $rows['Retail (Min)']['lots']);
        $this->assertSame(13, $rows['Retail (Max)']['lots']);
        $this->assertSame(14, $rows['S-HNI (Min)']['lots']);
        $this->assertSame(66, $rows['S-HNI (Max)']['lots']);
        $this->assertSame(67, $rows['B-HNI (Min)']['lots']);
    }

    public function test_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Alpha Tech')->assertSee('One Darbaar');
        $this->get('/ipo')->assertOk()->assertSee('Delta Old');
        $this->get('/ipo?status=open')->assertOk()->assertSee('Alpha Tech')->assertDontSee('delta-old-ipo" class="co-name"', false);
        $this->get('/ipo/type/sme')->assertOk()->assertSee('Beta Foods')->assertDontSee('alpha-tech-ipo" class="co-name"', false);
        $this->get('/ipo?q=gamma')->assertOk()->assertSee('Gamma Steel');
        $this->get('/ipo/alpha-tech-ipo')->assertOk()->assertSee('Alpha Tech IPO')->assertSee('₹110 – 120')->assertSee('Basis of Allotment');
        $this->get('/ipo/unknown-ipo')->assertNotFound();
        $this->get('/ipo-gmp')->assertOk()->assertSee('Alpha Tech');
        $this->get('/ipo-calendar?month=2026-09')->assertOk()->assertSee('Gamma Steel');
        $this->get('/calculators')->assertOk()->assertSee('SIP Calculator');
        $this->get('/calculators/ipo-gmp')->assertOk()->assertSee('data-calc="ipo-gmp"', false);
        $this->get('/calculators/does-not-exist')->assertNotFound();
        foreach (Calculators::all() as $slug => $calc) {
            $this->get('/calculators/'.$slug)->assertOk()->assertSee('data-calc="'.$slug.'"', false);
        }
        $this->get('/about')->assertOk()->assertSee('IPO Darbaar is an information platform');
        $this->get('/disclaimer')->assertOk();
        $this->get('/sitemap.xml')->assertOk()->assertSee('alpha-tech-ipo');
    }

    public function test_search_suggestions(): void
    {
        $this->getJson('/ipo/search/suggest?q=alp')
            ->assertOk()
            ->assertJsonPath('0.name', 'Alpha Tech')
            ->assertJsonPath('0.statusKey', 'open');

        $this->getJson('/ipo/search/suggest?q=a')->assertOk()->assertExactJson([]);
    }

    public function test_news_pages_and_feed(): void
    {
        $this->get('/news')->assertOk()->assertSee('Markets rally on IPO demand 501');
        $this->get('/news/501')->assertRedirect('/news/501/markets-rally-on-ipo-demand-501');
        $this->get('/news/501/markets-rally-on-ipo-demand-501')->assertOk()
            ->assertSee('Hinglish')
            ->assertDontSee('alert(1)', false);
        $this->get('/shorts')->assertOk()->assertSee('Markets rally on IPO demand 502');
        $this->getJson('/shorts/feed?page=2')->assertOk()->assertJsonStructure(['items', 'has_more', 'page']);
    }

    public function test_news_html_is_sanitized(): void
    {
        $clean = NewsService::sanitize('<p onclick="x()">Hi <strong style="color:red">there</strong></p><script>alert(1)</script><p><br></p><a href="javascript:bad()">x</a><a href="https://example.com" class="y">ok</a>');

        $this->assertSame('<p>Hi <strong>there</strong></p><a>x</a><a href="https://example.com" target="_blank" rel="noopener nofollow">ok</a>', $clean);
    }

    public function test_name_and_band_parsing(): void
    {
        $this->assertSame('Shah Investor\'s Home', IpoSyncService::extractName("Shah Investor's Home IPO: Date, Price | Finowings"));
        $this->assertSame('Crop Life Science LTD.', IpoSyncService::extractName('Crop Life Science LTD. IPO - Review, Valuation'));
        $this->assertSame(['min' => 296.0, 'max' => 311.0], IpoSyncService::parsePriceBand('price band ₹296-311/share'));
        $this->assertSame(['min' => null, 'max' => 56.0], IpoSyncService::parsePriceBand('fixed price ₹56/share'));
        $this->assertSame(400, IpoSyncService::parseLotSize('Check lot size (400 shares), allotment'));
        $this->assertSame('₹1,23,45,678.5', '₹'.Ipo::num(12345678.5));
    }

    private function ipoRow(int $id, string $title, string $type, ?string $open, ?string $close, ?string $listing, string $price, string $gmp, string $desc): array
    {
        $slug = Str::slug(IpoSyncService::extractName($title)).'-ipo';

        return [
            'id' => $id, 'title' => $title, 'description' => $desc, 'type' => $type, 'tags' => $slug,
            'image_url' => "https://www.finowings.com/admin/media/{$slug}.webp", 'image_alt' => $slug, 'external_link' => $slug,
            'price' => $price, 'gmp' => $gmp, 'size' => '100.5',
            'open_date' => null, 'open_date_iso' => $open, 'close_date' => null, 'close_date_iso' => $close,
            'listing_date' => null, 'listing_date_iso' => $listing, 'is_published' => 'Yes',
            'created_date' => '2026-09-20 00:00:00', 'updated_date' => '2026-09-23 10:00:00',
        ];
    }

    private function newsRow(int $id): array
    {
        return [
            'id' => $id,
            'headline' => "Markets rally on IPO demand {$id}",
            'headline_hinglish' => "IPO demand se market mein tezi {$id}",
            'news_detail' => '<p>Indian markets rallied strongly today as investors lined up for new listings across sectors.</p><ul><li>Point one</li></ul><script>alert(1)</script>',
            'news_detail_hinglish' => '<p>Aaj Indian markets mein zabardast tezi dikhi kyunki investors naye listings ke liye line mein the.</p>',
            'date' => '2026-09-23',
            'image' => 'https://courses.finowings.com/storage/market_news/a.avif',
            'image_banner' => 'https://courses.finowings.com/storage/market_news/a.avif',
            'category_id' => 9,
            'category' => ['id' => 9, 'name' => 'IPO', 'slug' => 'ipo', 'color' => '#DC143C'],
            'status' => 'active',
            'created_at' => '2026-09-23 11:35:20',
            'updated_at' => '2026-09-23 11:35:20',
        ];
    }
}
