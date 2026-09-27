<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostToTelegramTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456:secret-bot-token';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 09:00:00', 'Asia/Kolkata'));
        config(['services.telegram.bot_token' => self::TOKEN, 'services.telegram.channel_id' => '@ipodarbaar']);
    }

    public function test_morning_digest_is_posted_to_the_channel_only_once_per_day(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Ipo::factory()->create(['name' => 'Opening Today Ltd', 'open_date' => today()->toDateString(), 'close_date' => today()->addDays(2)->toDateString()]);

        $this->artisan('darbaar:telegram', ['type' => 'digest'])->assertSuccessful();
        $this->artisan('darbaar:telegram', ['type' => 'digest'])->expectsOutputToContain('Already posted')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage')
            && $request['chat_id'] === '@ipodarbaar'
            && $request['parse_mode'] === 'HTML'
            && str_contains($request['text'], 'Opening today')
            && str_contains($request['text'], 'Opening Today Ltd'));
    }

    public function test_failed_post_is_released_for_retry_without_leaking_the_bot_token(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
        Ipo::factory()->create(['open_date' => today()->toDateString(), 'close_date' => today()->addDays(2)->toDateString()]);

        $this->artisan('darbaar:telegram', ['type' => 'digest'])
            ->doesntExpectOutputToContain(self::TOKEN)
            ->assertFailed();

        $this->assertFalse(NotificationLog::wasSent('telegram', 'digest:2026-09-24'));
    }

    public function test_nothing_is_posted_when_telegram_is_not_configured(): void
    {
        Http::fake();
        config(['services.telegram.bot_token' => null]);
        Ipo::factory()->create(['open_date' => today()->toDateString()]);

        $this->artisan('darbaar:telegram', ['type' => 'digest'])->assertSuccessful();

        Http::assertNothingSent();
    }
}
