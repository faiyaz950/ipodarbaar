<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Ipo;
use App\Models\IpoSubscriptionDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class MarketDataTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'TradDt,BizDt,Sgmt,Src,FinInstrmTp,FinInstrmId,ISIN,TckrSymb,SctySrs,XpryDt,FininstrmActlXpryDt,StrkPric,OptnTp,FinInstrmNm,OpnPric,HghPric,LwPric,ClsPric,LastPric,PrvsClsgPric,UndrlygPric,SttlmPric,OpnIntrst,ChngInOpnIntrst,TtlTradgVol,TtlTrfVal,TtlNbOfTxsExctd,SsnId,NewBrdLotQty,Rmks,Rsvd1,Rsvd2,Rsvd3,Rsvd4';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-01 15:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
    }

    private function categories(string $asOf, float $qib, float $nii, float $retail, float $total): array
    {
        $row = fn (string $category, string $offered, float $times, string $sr): array => [
            'category' => $category, 'noOfShareOffered' => $offered, 'noOfSharesBid' => '1', 'noOfTotalMeant' => (string) $times, 'srNo' => $sr,
        ];

        return [
            'dataList' => [
                ['category' => 'Category', 'noOfShareOffered' => 'No.of shares offered/reserved', 'noOfSharesBid' => 'No. of shares bid for', 'noOfTotalMeant' => 'No. of times of total meant for the category', 'srNo' => 'Sr.No.'],
                $row('Qualified Institutional Buyers(QIBs)', '84710', $qib, '1'),
                $row('Foreign Institutional Investors(FIIs)', '', 0, '1(a)'),
                $row('Non Institutional Investors', '2456635', $nii, '2'),
                $row('Non Institutional Investors(Bid amount of more than Ten Lakh Rupees)', '1637757', 1.05, '2.1'),
                $row('Non Institutional Investors(Bid amount of more than Two Lakh Rupees upto Ten Lakh Rupees)', '818878', 0.31, '2.2'),
                $row('Retail Individual Investors(RIIs)', '5929808', $retail, '3'),
                $row('Employees', '0', 0, '4'),
                ['category' => 'Total', 'noOfShareOffered' => '8471153.0', 'noOfSharesBid' => '1', 'noOfTotalMeant' => (string) $total, 'srNo' => null],
            ],
            'updateTime' => 'Updated as on '.$asOf,
        ];
    }

    private function openIpo(): Ipo
    {
        return Ipo::factory()->create([
            'name' => 'Vishal Nirmiti', 'slug' => 'vishal-nirmiti-ipo', 'price_min' => 208, 'price' => 220,
            'open_date' => '2026-09-30', 'close_date' => '2026-10-05', 'listing_date' => '2026-10-08',
        ]);
    }

    private function nseZip(string $csv): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('BhavCopy_NSE_CM.csv', $csv);
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function bhavRow(string $src, string $code, string $isin, string $symbol, string $series, string $name, float $open, float $close, float $prev): string
    {
        return "2026-10-01,2026-10-01,CM,{$src},STK,{$code},{$isin},{$symbol},{$series},,,,,{$name},{$open},{$open},{$close},{$close},{$close},{$prev},,{$close},,,100,1000,10,F1,1,,,,,";
    }

    public function test_subscription_is_linked_to_the_nse_symbol_and_kept_per_day(): void
    {
        $ipo = $this->openIpo();
        Http::fake([
            'www.nseindia.com/api/ipo-current-issue' => Http::response([
                ['companyName' => 'Vishal Nirmiti Limited', 'symbol' => 'VNL', 'series' => 'EQ', 'issueStartDate' => '30-Sep-2026', 'issueEndDate' => '05-Oct-2026', 'status' => 'Active'],
                ['companyName' => 'Some Other Limited', 'symbol' => 'OTHER', 'series' => 'EQ', 'issueStartDate' => '30-Sep-2026', 'issueEndDate' => '05-Oct-2026'],
            ]),
            'www.nseindia.com/api/ipo-active-category*' => Http::sequence()
                ->push($this->categories('30-Sep-2026 17:00:00', 0.12, 0.2, 0.3, 0.21))
                ->push($this->categories('30-Sep-2026 17:00:00', 0.12, 0.2, 0.3, 0.21))
                ->push($this->categories('01-Oct-2026 17:00:00', 0.96, 0.8, 0.47, 0.57)),
        ]);

        $this->artisan('ipo:subscription')->expectsOutputToContain('linked 1, updated 1')->assertSuccessful();
        $ipo->refresh();
        $this->assertSame('VNL', $ipo->nse_symbol);
        $this->assertSame(0.21, $ipo->subscription_total);
        $this->assertSame(1.05, $ipo->subscription_bnii);
        $this->assertNull($ipo->subscription_employee, 'Categories with nothing reserved have no times-subscribed figure.');
        $this->assertSame('2026-09-30 17:00', $ipo->subscription_updated_at->format('Y-m-d H:i'));

        // Same reading again: nothing changes.
        $this->artisan('ipo:subscription')->expectsOutputToContain('updated 0')->assertSuccessful();

        // The next day's reading adds a second day.
        $this->artisan('ipo:subscription')->expectsOutputToContain('updated 1')->assertSuccessful();
        $this->assertSame(0.57, $ipo->fresh()->subscription_total);
        $this->assertSame(['2026-09-30', '2026-10-01'], IpoSubscriptionDay::query()->orderBy('date')->get()->map(fn ($d) => $d->date->toDateString())->all());

        $this->get($ipo->url())->assertOk()
            ->assertSee('Day 2')->assertSee('0.96x')->assertSee('bHNI (&gt;₹10L)', false)
            ->assertSee('from NSE (bids on NSE and BSE combined)');
    }

    public function test_sme_issues_without_category_reservations_get_the_overall_figure(): void
    {
        $ipo = Ipo::factory()->create(['name' => 'Eventions', 'type' => 'sme', 'price' => 118, 'open_date' => '2026-09-30', 'close_date' => '2026-10-05']);
        $empty = ['dataList' => [['category' => 'Total', 'noOfShareOffered' => '0.0', 'noOfSharesBid' => '4466400.0', 'noOfTotalMeant' => '0.00', 'srNo' => null]], 'updateTime' => 'Updated as on 01-Oct-2026 17:00:00'];
        Http::fake([
            'www.nseindia.com/api/ipo-current-issue' => Http::response([
                ['companyName' => 'Eventions Limited', 'symbol' => 'EVENTIONS', 'series' => 'SME', 'issueStartDate' => '30-Sep-2026', 'issueEndDate' => '05-Oct-2026', 'noOfTime' => '1.75'],
            ]),
            'www.nseindia.com/api/ipo-active-category*' => Http::response($empty),
        ]);

        $this->artisan('ipo:subscription')->expectsOutputToContain('updated 1')->assertSuccessful();
        $this->assertSame(1.75, $ipo->fresh()->subscription_total);
        $this->assertNull($ipo->fresh()->subscription_qib);
    }

    public function test_backfill_links_past_issues_and_fetches_final_figures(): void
    {
        $ipo = Ipo::factory()->create(['name' => 'Moneyview', 'price' => 34, 'open_date' => '2026-09-24', 'close_date' => '2026-09-28', 'listing_date' => '2026-10-01']);
        Http::fake([
            'www.nseindia.com/api/public-past-issues' => Http::response([
                ['companyName' => 'Moneyview Limited', 'symbol' => 'MONEYVIEW', 'securityType' => 'EQ', 'ipoStartDate' => '24-SEP-2026', 'ipoEndDate' => '28-SEP-2026', 'listingDate' => '01-OCT-2026', 'issuePrice' => '    34'],
            ]),
            'www.nseindia.com/api/ipo-active-category*' => Http::response($this->categories('28-Sep-2026 19:00:00', 227.45, 115.41, 19.57, 98.46)),
        ]);

        $this->artisan('ipo:subscription --backfill=2026-09-01')->expectsOutputToContain('Linked 1 NSE symbols; filled subscription for 1 IPOs')->assertSuccessful();
        $this->assertSame(98.46, $ipo->fresh()->subscription_total);
    }

    public function test_listing_prices_come_from_the_bhavcopies(): void
    {
        $mainboard = Ipo::factory()->create(['name' => 'Moneyview', 'price' => 34, 'nse_symbol' => 'MONEYVIEW', 'open_date' => '2026-09-24', 'close_date' => '2026-09-28', 'listing_date' => '2026-10-01']);
        // BSE SME issue whose listing date in the data was wrong.
        $sme = Ipo::factory()->create(['name' => 'Roopa Screen', 'type' => 'sme', 'price' => 64, 'open_date' => '2026-09-24', 'close_date' => '2026-09-28', 'listing_date' => '2026-10-03']);
        $edited = Ipo::factory()->create(['name' => 'A-One Steels India', 'price' => 405, 'listing_price' => 450, 'nse_symbol' => 'AONESTEELS', 'open_date' => '2026-09-24', 'close_date' => '2026-09-28', 'listing_date' => '2026-10-01']);
        // A different new BSE listing with a similar name and an impossible price is not taken.
        $unrelated = Ipo::factory()->create(['name' => 'Peshwa Wheat', 'type' => 'sme', 'price' => 1010, 'open_date' => '2026-09-24', 'close_date' => '2026-09-28', 'listing_date' => '2026-10-01']);

        $nse = self::HEADER."\n".implode("\n", [
            $this->bhavRow('NSE', '766325', 'INE0PTN01011', 'MONEYVIEW', 'EQ', 'MONEYVIEW LIMITED', 55, 53.88, 34),
            $this->bhavRow('NSE', '766314', 'INE0OTC01025', 'AONESTEELS', 'EQ', 'A-ONE STEELS INDIA LTD', 455, 416.55, 405),
            $this->bhavRow('NSE', '500001', 'INE000000001', 'OLDCO', 'EQ', 'OLD COMPANY LTD', 100, 101, 99),
        ]);
        $bse = self::HEADER."\n".implode("\n", [
            $this->bhavRow('BSE', '544953', 'INE0PTN01011', 'MONEYVIEW', 'B', 'Moneyview Limited', 55.61, 54.03, 0),
            $this->bhavRow('BSE', '544954', 'INE2BTZ01011', 'ROOPA', 'MT', 'Roopa Screen Limited', 121.6, 115.52, 0),
            $this->bhavRow('BSE', '544955', 'INE0SR101016', 'PESHWA', 'MT', 'Peshwa Wheat Limited', 100.05, 95.05, 0),
        ]);
        Http::fake([
            'nsearchives.nseindia.com/*' => Http::response($this->nseZip($nse)),
            'www.bseindia.com/download/*' => Http::response($bse),
        ]);

        $this->artisan('ipo:listing-prices --date=2026-10-01')->expectsOutputToContain('Recorded 3 listings')->assertSuccessful();

        $mainboard->refresh();
        $this->assertSame(55.0, $mainboard->listing_price);
        $this->assertSame(53.88, $mainboard->listing_close);
        $this->assertSame('NSE', $mainboard->listing_exchange);
        $this->assertSame('544953', $mainboard->bse_code);
        $this->assertSame('INE0PTN01011', $mainboard->isin);

        $sme->refresh();
        $this->assertSame(121.6, $sme->listing_price);
        $this->assertSame('BSE', $sme->listing_exchange);
        $this->assertSame('2026-10-01', $sme->listing_date->toDateString());
        $this->assertContains('listing_date', $sme->locked_fields);

        $this->assertSame(450.0, $edited->fresh()->listing_price, 'A price entered by an editor is kept.');
        $this->assertSame(416.55, $edited->fresh()->listing_close);
        $this->assertNull($unrelated->fresh()->listing_price);

        $this->get($mainboard->url())->assertOk()->assertSee('Listing price (NSE)')->assertSee('Listing-day close')->assertSee('+61.76%');
    }

    public function test_no_bhavcopy_yet_records_nothing(): void
    {
        Ipo::factory()->create(['name' => 'Moneyview', 'price' => 34, 'close_date' => '2026-09-28', 'listing_date' => '2026-10-01']);
        Http::fake([
            'nsearchives.nseindia.com/*' => Http::response('Not found', 404),
            'www.bseindia.com/download/*' => Http::response('<html>Not found</html>', 200),
        ]);

        $this->artisan('ipo:listing-prices --date=2026-10-01')->expectsOutputToContain('Recorded 0 listings')->assertSuccessful();
    }

    public function test_morning_pre_open_price_is_recorded_for_todays_listings(): void
    {
        $listing = Ipo::factory()->create(['name' => 'Moneyview', 'price' => 34, 'nse_symbol' => 'MONEYVIEW', 'close_date' => '2026-09-28', 'listing_date' => '2026-10-01']);
        $relisting = Ipo::factory()->create(['name' => 'KM Sugar', 'price' => 50, 'nse_symbol' => 'KMSUGAR', 'close_date' => '2026-09-28', 'listing_date' => '2026-10-01']);
        Http::fake([
            'www.nseindia.com/api/special-preopen-listing' => Http::response(['data' => [
                ['symbol' => 'MONEYVIEW', 'isin' => 'INE0PTN01011', 'iep' => '55.00', 'prevClose' => '34.00'],
                ['symbol' => 'KMSUGAR', 'isin' => 'INE157H01023', 'iep' => '27.10', 'prevClose' => '33.68'],
            ]]),
        ]);

        $this->artisan('ipo:listing-prices --morning')->expectsOutputToContain('Recorded 1 listing prices')->assertSuccessful();
        $this->assertSame(55.0, $listing->fresh()->listing_price);
        $this->assertNull($listing->fresh()->listing_close);
        $this->assertNull($relisting->fresh()->listing_price, 'A previous close that is not the issue price means it is not this IPO\'s listing.');
    }

    public function test_daily_blog_post_reports_subscription_and_the_last_listings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:50:00', 'Asia/Kolkata'));
        Ipo::factory()->create([
            'name' => 'Vishal Nirmiti', 'price' => 220, 'open_date' => '2026-09-30', 'close_date' => '2026-10-05', 'listing_date' => '2026-10-08',
            'subscription_qib' => 0.96, 'subscription_nii' => 0.8, 'subscription_retail' => 0.47, 'subscription_total' => 0.57,
            'subscription_updated_at' => '2026-10-01 17:00:00',
        ]);
        Ipo::factory()->create(['name' => 'Moneyview', 'price' => 34, 'gmp' => 20, 'listing_price' => 55, 'listing_close' => 53.88, 'open_date' => '2026-09-24', 'close_date' => '2026-09-28', 'listing_date' => '2026-10-01']);

        $this->artisan('blog:auto daily')->assertSuccessful();

        $body = BlogPost::query()->sole()->body;
        $this->assertStringContainsString('By Thu, 1 Oct, 5:00 PM, the issue was subscribed 0.57x overall (QIB 0.96x, NII 0.80x and retail 0.47x)', $body);
        $this->assertStringContainsString('<h2>How the last listings did</h2>', $body);
        $this->assertStringContainsString('Moneyview had the strongest debut, opening +61.8% above its issue price against a last GMP of +58.8%', $body);
    }

    public function test_scheduler_runs_the_market_data_jobs(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('ipo:subscription')
            ->expectsOutputToContain('ipo:listing-prices --morning')
            ->assertSuccessful();
    }
}
