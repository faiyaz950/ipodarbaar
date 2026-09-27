<?php

namespace Tests\Feature;

use App\Models\Ipo;
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
