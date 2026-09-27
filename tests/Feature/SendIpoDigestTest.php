<?php

namespace Tests\Feature;

use App\Jobs\SendDigestMail;
use App\Mail\IpoDigestMail;
use App\Models\Ipo;
use App\Models\Subscriber;
use App\Services\IpoDigestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendIpoDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 08:00:00', 'Asia/Kolkata'));
        Http::fake(['courses.finowings.com/*' => Http::response(['success' => true, 'data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'has_more' => false])]);
    }

    public function test_daily_digest_is_queued_once_for_confirmed_daily_subscribers_only(): void
    {
        Queue::fake();
        Ipo::factory()->open()->create();
        $reader = Subscriber::factory()->confirmed()->create();
        Subscriber::factory()->confirmed()->weekly()->create();
        Subscriber::factory()->create();
        Subscriber::factory()->unsubscribed()->create();

        $this->artisan('darbaar:digest', ['frequency' => 'daily'])->assertSuccessful();
        $this->artisan('darbaar:digest', ['frequency' => 'daily'])->expectsOutputToContain('already sent')->assertSuccessful();

        Queue::assertPushed(SendDigestMail::class, 1);
        Queue::assertPushed(SendDigestMail::class, fn (SendDigestMail $job): bool => $job->subscriberId === $reader->id);
    }

    public function test_digest_is_skipped_when_there_is_nothing_to_report(): void
    {
        Queue::fake();
        Ipo::factory()->listed()->create();
        Subscriber::factory()->confirmed()->create();

        $this->artisan('darbaar:digest', ['frequency' => 'daily'])->expectsOutputToContain('Nothing to report')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_digest_job_mails_the_subscriber_with_an_unsubscribe_link_and_skips_unsubscribed_readers(): void
    {
        Mail::fake();
        Ipo::factory()->open()->create();
        $digest = app(IpoDigestBuilder::class)->email('daily');
        $reader = Subscriber::factory()->confirmed()->create();
        $leaver = Subscriber::factory()->unsubscribed()->create();

        (new SendDigestMail($reader->id, $digest))->handle();
        (new SendDigestMail($leaver->id, $digest))->handle();

        Mail::assertSent(IpoDigestMail::class, 1);
        Mail::assertSent(IpoDigestMail::class, fn (IpoDigestMail $mail): bool => $mail->hasTo($reader->email));
        $this->assertNotNull($reader->fresh()->last_sent_at);
    }
}
