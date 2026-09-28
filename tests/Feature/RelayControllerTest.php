<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\IpoFinancial;
use App\Services\IpoSyncService;
use App\Services\LogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RelayControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'relay-test-token';

    /** @var list<string> */
    private array $createdLogos = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['ipodarbar.ipo_api.push_token' => self::TOKEN]);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdLogos as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_relayed_rows_are_imported_and_mark_the_sync_time(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson(route('relay.ipos'), ['data' => [$this->row(11, 'Alpha Tech IPO: Date & GMP | Finowings'), ['id' => 0]]])
            ->assertOk()
            ->assertExactJson(['imported' => 2]);

        $this->assertDatabaseHas('ipos', ['api_id' => 11, 'name' => 'Alpha Tech', 'price' => 120]);
        $this->assertDatabaseCount('ipos', 1);
        $this->assertNotNull(IpoSyncService::lastSyncedAt());
    }

    public function test_a_wrong_or_missing_token_is_hidden_behind_404(): void
    {
        $ipo = Ipo::factory()->create(['image_url' => 'https://img.test/a.jpg']);
        $payload = ['data' => [$this->row(11, 'Alpha Tech IPO | Finowings')]];

        $this->withToken('wrong')->postJson(route('relay.ipos'), $payload)->assertNotFound();
        $this->postJson(route('relay.ipos'), $payload)->assertNotFound();
        $this->withToken('wrong')->getJson(route('relay.logos.missing'))->assertNotFound();
        $this->withToken('wrong')->post(route('relay.logos.store', $ipo), ['banner' => $this->banner()])->assertNotFound();

        $this->assertDatabaseCount('ipos', 1);
    }

    public function test_endpoint_is_disabled_until_a_token_is_configured(): void
    {
        config(['ipodarbar.ipo_api.push_token' => null]);

        $this->withToken('')->postJson(route('relay.ipos'), ['data' => []])->assertNotFound();
    }

    public function test_payload_must_contain_a_data_array(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson(route('relay.ipos'), ['rows' => []])
            ->assertJsonValidationErrors('data');

        $this->assertSame(0, Ipo::count());
    }

    public function test_missing_logos_lists_current_ipos_without_a_thumbnail(): void
    {
        $open = Ipo::factory()->open()->create(['image_url' => 'https://img.test/open banner.jpg']);
        Ipo::factory()->open()->create(['image_url' => null]);
        Ipo::factory()->create([
            'image_url' => 'https://img.test/old.jpg',
            'open_date' => now()->subYear()->toDateString(),
            'close_date' => now()->subYear()->addDays(2)->toDateString(),
            'listing_date' => now()->subYear()->addDays(5)->toDateString(),
        ]);

        $this->withToken(self::TOKEN)
            ->getJson(route('relay.logos.missing'))
            ->assertOk()
            ->assertExactJson([['api_id' => $open->api_id, 'url' => 'https://img.test/open%20banner.jpg']]);
    }

    public function test_a_relayed_banner_becomes_the_logo_thumbnail(): void
    {
        $ipo = Ipo::factory()->open()->create(['image_url' => 'https://img.test/banner.png']);
        $this->createdLogos[] = app(LogoService::class)->path(app(LogoService::class)->filename($ipo));

        $this->withToken(self::TOKEN)
            ->post(route('relay.logos.store', $ipo), ['banner' => $this->banner()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertExactJson(['stored' => true]);

        $this->assertTrue(app(LogoService::class)->exists($ipo));
        $this->withToken(self::TOKEN)->getJson(route('relay.logos.missing'))->assertExactJson([]);
    }

    public function test_relayed_banner_must_be_an_image(): void
    {
        $ipo = Ipo::factory()->create(['image_url' => 'https://img.test/banner.png']);

        $this->withToken(self::TOKEN)
            ->post(route('relay.logos.store', $ipo), ['banner' => UploadedFile::fake()->create('x.pdf', 10)], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('banner');
    }

    public function test_logos_are_not_downloaded_when_the_host_relies_on_the_relay(): void
    {
        config(['ipodarbar.ipo_api.pull' => false]);
        Http::fake();
        $ipo = Ipo::factory()->create(['image_url' => 'https://img.test/banner.png']);

        $this->assertNull(app(LogoService::class)->ensure($ipo));
        Http::assertNothingSent();
    }

    public function test_stale_pages_lists_unread_pages_and_current_ipos_read_long_ago(): void
    {
        $unreadOld = Ipo::factory()->listed()->create(['slug' => 'unread-old-ipo', 'open_date' => now()->subYear()]);
        $staleCurrent = Ipo::factory()->open()->create(['slug' => 'stale-current-ipo', 'page_synced_at' => now()->subDay(), 'open_date' => now()->subYears(2)]);
        Ipo::factory()->open()->create(['page_synced_at' => now()->subHour()]);
        Ipo::factory()->listed()->create(['page_synced_at' => now()->subMonth()]);

        $this->withToken(self::TOKEN)
            ->getJson(route('relay.pages.stale', ['limit' => 5]))
            ->assertOk()
            ->assertExactJson([
                ['api_id' => $staleCurrent->api_id, 'url' => 'https://www.finowings.com/ipo/stale-current-ipo'],
                ['api_id' => $unreadOld->api_id, 'url' => 'https://www.finowings.com/ipo/unread-old-ipo'],
            ]);
    }

    public function test_a_relayed_page_fills_missing_details(): void
    {
        $ipo = Ipo::factory()->create(['lot_size' => null]);

        $this->withToken(self::TOKEN)
            ->post(route('relay.pages.store', $ipo), ['page' => $this->page()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertExactJson(['filled' => ['registrar', 'lot_size', 'face_value', 'lead_managers', 'financials']]);

        $ipo->refresh();
        $this->assertSame('Bigshare Services', $ipo->registrar);
        $this->assertSame(1600, $ipo->lot_size);
        $this->assertSame(10.0, $ipo->detail->face_value);
        $this->assertSame('Hem Securities Ltd.', $ipo->detail->lead_managers);
        $this->assertSame(46.76, $ipo->financials->first()->revenue);
        $this->assertNotNull($ipo->page_synced_at);
    }

    public function test_details_entered_by_an_editor_are_not_overwritten(): void
    {
        $ipo = Ipo::factory()->create(['registrar' => 'Cameo Corporate Services', 'lot_size' => 800]);
        $ipo->detail()->create(['face_value' => 2, 'lead_managers' => 'Axis Capital']);
        IpoFinancial::factory()->for($ipo)->create(['period' => 'FY25', 'revenue' => 10]);

        $this->withToken(self::TOKEN)
            ->post(route('relay.pages.store', $ipo), ['page' => $this->page()], ['Accept' => 'application/json'])
            ->assertExactJson(['filled' => []]);

        $ipo->refresh();
        $this->assertSame([800, 2.0, 'Axis Capital'], [$ipo->lot_size, $ipo->detail->face_value, $ipo->detail->lead_managers]);
        $this->assertSame(['FY25'], $ipo->financials->pluck('period')->all());
    }

    public function test_the_api_sync_keeps_a_lot_size_found_on_the_source_page(): void
    {
        $ipo = Ipo::factory()->create(['api_id' => 11, 'lot_size' => null]);
        $this->withToken(self::TOKEN)->post(route('relay.pages.store', $ipo), ['page' => $this->page()], ['Accept' => 'application/json']);

        $this->withToken(self::TOKEN)->postJson(route('relay.ipos'), ['data' => [$this->row(11, 'Alpha Tech IPO | Finowings')]])->assertOk();

        $this->assertSame(1600, $ipo->fresh()->lot_size);
    }

    private function page(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('page.html.gz', gzencode(<<<'HTML'
            <h2>Company Financials</h2><p>(Amount in Cr)</p>
            <table><tr><th>Period</th><th>31 Mar 2026</th></tr><tr><td>Total Income</td><td>46.76</td></tr></table>
            <h2>IPO Summary</h2>
            <table>
                <tr><td>Face Value</td><td>Rs. 10 per Share</td></tr>
                <tr><td>Lot Size</td><td>1600 Shares</td></tr>
                <tr><td>Registrar</td><td>Bigshare Services Pvt. Ltd.</td></tr>
            </table>
            <h2>IPO Lead Managers</h2><p>Hem Securities Ltd.</p>
            HTML));
    }

    /** A banner in the source template: a white card with a dark logo in the middle. */
    private function banner(): UploadedFile
    {
        $image = imagecreatetruecolor(640, 360);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 250, 150, 390, 220, imagecolorallocate($image, 20, 40, 120));

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent('banner.png', $bytes);
    }

    private function row(int $id, string $title): array
    {
        return [
            'id' => $id, 'title' => $title, 'type' => 'IPO', 'price' => '120', 'gmp' => '15', 'size' => '100.5',
            'description' => 'Price band ₹110-120/share, lists on NSE & BSE.',
            'external_link' => 'alpha-tech-ipo', 'is_published' => 'Yes',
            'open_date_iso' => '2026-09-23', 'close_date_iso' => '2026-09-25', 'listing_date_iso' => '2026-09-30',
        ];
    }
}
