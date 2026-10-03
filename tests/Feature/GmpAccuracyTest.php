<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Ipo;
use App\Models\IpoGmpHistory;
use App\Models\User;
use App\Services\Blog\BlogImageService;
use App\Services\Market\ListingPriceSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GmpAccuracyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-01 21:45:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
        Storage::fake('uploads');
    }

    private function listed(string $name, string $type, float $price, ?float $gmp, float $listing, ?float $close = null, string $date = '2026-10-01', array $extra = []): Ipo
    {
        return Ipo::factory()->create([
            'name' => $name, 'type' => $type, 'price' => $price, 'listing_gmp' => $gmp, 'gmp' => $gmp, 'listing_price' => $listing,
            'listing_close' => $close, 'listing_exchange' => 'NSE', 'open_date' => '2026-09-24', 'close_date' => '2026-09-28', 'listing_date' => $date,
        ] + $extra);
    }

    private function scene(): array
    {
        return [
            // GMP +20% → listed +25%: 5 points above.
            'a' => $this->listed('Alpha Tech', 'mainboard', 100, 20, 125, 130, extra: [
                'lot_size' => 100, 'subscription_total' => 98.46, 'subscription_qib' => 227.45, 'subscription_nii' => 115.41, 'subscription_retail' => 19.57,
                'about' => 'Alpha Tech IPO is a Mainboard IPO by Alpha Tech Limited, which is a maker of electric motors. More.',
            ]),
            // GMP +10% → listed -5%: 15 points below, wrong direction.
            'b' => $this->listed('Beta Foods', 'mainboard', 200, 20, 190, 180),
            // SME at the 90% cap with GMP +50%.
            'c' => $this->listed('Gamma Retail', 'sme', 50, 25, 95, 99.75),
            // Bad price on file (listing 10x the issue price): left out of the accuracy numbers.
            'bad' => $this->listed('Delta Assets', 'mainboard', 1369, 12, 139, 140, '2026-09-17'),
        ];
    }

    public function test_accuracy_page_summarises_gmp_against_actual_listings(): void
    {
        $this->scene();

        $this->get('/ipo-gmp-accuracy')
            ->assertOk()
            ->assertSee('IPO GMP Accuracy Tracker')
            ->assertSee('for 3 IPOs since Oct 2026')
            ->assertSee('<b>3</b>', false)
            ->assertSee('+5.0 pts')              // Alpha: listed 5 points above its GMP estimate
            ->assertSee('-15.0 pts')             // Beta: 15 points below
            ->assertDontSee('Delta Assets')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('How accurate is IPO GMP?');

        $this->get('/ipo-gmp')->assertOk()->assertSee(route('ipos.gmp-accuracy'), false);
        $this->get('/sitemaps/pages.xml')->assertOk()->assertSee(route('ipos.gmp-accuracy'), false);
    }

    public function test_ipo_page_compares_listing_with_the_last_gmp(): void
    {
        $ipos = $this->scene();

        $this->get($ipos['a']->url())->assertOk()
            ->assertSee('The last GMP before listing (₹20) pointed to about ₹120; the shares opened 5.0 points above that estimate.');
    }

    public function test_listing_capture_freezes_the_gmp_from_before_listing_day(): void
    {
        $ipo = Ipo::factory()->create(['name' => 'Moneyview', 'price' => 34, 'gmp' => 21, 'nse_symbol' => 'MONEYVIEW', 'close_date' => '2026-09-28', 'listing_date' => '2026-10-01']);
        IpoGmpHistory::query()->create(['ipo_id' => $ipo->id, 'date' => '2026-09-30', 'gmp' => 20, 'price' => 34]);
        IpoGmpHistory::query()->create(['ipo_id' => $ipo->id, 'date' => '2026-10-01', 'gmp' => 21, 'price' => 34]);
        Http::fake(['www.nseindia.com/api/special-preopen-listing' => Http::response(['data' => [
            ['symbol' => 'MONEYVIEW', 'isin' => 'INE0PTN01011', 'iep' => '55.00', 'prevClose' => '34.00'],
        ]])]);

        app(ListingPriceSync::class)->morning();

        $this->assertSame(20.0, $ipo->fresh()->listing_gmp);
        $this->assertSame(58.82, $ipo->fresh()->gmpEstimatePercent());
        $this->assertSame(2.94, $ipo->fresh()->gmpErrorPoints());
    }

    public function test_listing_day_recap_post_is_written_in_the_evening(): void
    {
        $this->scene();

        $this->artisan('blog:auto listing')->expectsOutputToContain('Published')->assertSuccessful();

        $post = BlogPost::query()->sole();
        $this->assertSame('ipo-listing-recap-1-october-2026', $post->slug);
        $this->assertSame('listing-recap', $post->category);
        $this->assertSame('IPO Listing Recap (1 Oct 2026): Gamma Retail Lists 90% Higher, 3 IPOs Debut', $post->title);
        $this->assertStringContainsString('Best listing: Gamma Retail at +90.0%', $post->takeaways);

        $body = $post->body;
        $this->assertStringContainsString('Three companies made their stock market debut', $body);
        $this->assertStringContainsString('<h2>GMP vs the actual listing</h2>', $body);
        $this->assertStringContainsString('Alpha Tech is a maker of electric motors.', $body);
        $this->assertStringContainsString('one lot of 100 shares, that is a gain of about ₹2,500 at the opening price', $body);
        $this->assertStringContainsString('so the listing came in 5.0 points above the grey market estimate', $body);
        $this->assertStringContainsString('subscribed 98.46x overall (QIB 227.45x, NII 115.41x, retail 19.57x)', $body);
        $this->assertStringContainsString('Gamma Retail opened at the 90% limit that SME shares are allowed on their first day', $body);
        $this->assertStringContainsString('By the close, 2 of 3 had risen above their listing price and 1 had fallen below it.', $body);
        $this->assertStringNotContainsString('Delta Assets', $body);
        if (app(BlogImageService::class)->supported()) {
            Storage::disk('uploads')->assertExists('blog/auto/ipo-listing-recap-1-october-2026-gmp-vs-actual.webp');
        }

        // The next morning's IPO Today post links to the recap.
        Carbon::setTestNow(Carbon::parse('2026-10-02 08:50:00', 'Asia/Kolkata'));
        Ipo::factory()->create(['name' => 'Open Now Ltd', 'open_date' => '2026-10-01', 'close_date' => '2026-10-05']);
        $this->artisan('blog:auto daily')->assertSuccessful();
        $this->assertStringContainsString('href="/blog/ipo-listing-recap-1-october-2026"', BlogPost::query()->where('category', 'daily')->sole()->body);
    }

    public function test_no_recap_without_listings_or_at_weekends(): void
    {
        $this->artisan('blog:auto listing')->expectsOutputToContain('No listings with prices recorded')->assertSuccessful();

        $this->scene();
        $this->artisan('blog:auto listing --date=2026-10-03')->expectsOutputToContain('No listings')->assertSuccessful();
        $this->assertSame(0, BlogPost::query()->count());
    }

    public function test_admin_can_switch_the_recap_and_scheduler_runs_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/blog')->assertOk()->assertSee('Listing day recap')->assertSee('Write the listing day recap now');

        $this->artisan('schedule:list')->expectsOutputToContain('blog:auto listing')->assertSuccessful();
    }
}
