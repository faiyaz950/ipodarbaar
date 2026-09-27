<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Services\IpoSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpoPushControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'relay-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ipodarbar.ipo_api.push_token' => self::TOKEN]);
    }

    public function test_relayed_rows_are_imported_and_mark_the_sync_time(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson(route('ipo.push'), ['data' => [$this->row(11, 'Alpha Tech IPO: Date & GMP | Finowings'), ['id' => 0]]])
            ->assertOk()
            ->assertExactJson(['imported' => 2]);

        $this->assertDatabaseHas('ipos', ['api_id' => 11, 'name' => 'Alpha Tech', 'price' => 120]);
        $this->assertDatabaseCount('ipos', 1);
        $this->assertNotNull(IpoSyncService::lastSyncedAt());
    }

    public function test_a_wrong_or_missing_token_is_hidden_behind_404(): void
    {
        $payload = ['data' => [$this->row(11, 'Alpha Tech IPO | Finowings')]];

        $this->withToken('wrong')->postJson(route('ipo.push'), $payload)->assertNotFound();
        $this->postJson(route('ipo.push'), $payload)->assertNotFound();

        $this->assertDatabaseCount('ipos', 0);
    }

    public function test_endpoint_is_disabled_until_a_token_is_configured(): void
    {
        config(['ipodarbar.ipo_api.push_token' => null]);

        $this->withToken('')->postJson(route('ipo.push'), ['data' => []])->assertNotFound();
    }

    public function test_payload_must_contain_a_data_array(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson(route('ipo.push'), ['rows' => []])
            ->assertJsonValidationErrors('data');

        $this->assertSame(0, Ipo::count());
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
