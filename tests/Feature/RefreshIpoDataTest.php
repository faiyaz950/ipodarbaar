<?php

namespace Tests\Feature;

use App\Support\SchedulerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshIpoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        config(['ipodarbar.ipo_api.key' => 'test-key']);
        cache()->forever('ipo:synced_at', now()->subHour()->toIso8601String());
        Http::fake(['www.finowings.com/*' => Http::response(['status' => 'success', 'data' => [], 'pagination' => ['has_more' => false]])]);
    }

    public function test_page_request_does_not_sync_while_the_scheduler_is_running(): void
    {
        SchedulerHeartbeat::beat();

        $this->get('/ipo')->assertOk();

        Http::assertNothingSent();
    }

    public function test_page_request_syncs_stale_data_when_the_scheduler_has_stopped(): void
    {
        SchedulerHeartbeat::beat();
        $this->travel(20)->minutes();

        $this->get('/ipo')->assertOk();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'ipo-sync.php'));
    }
}
