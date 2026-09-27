<?php

namespace Tests\Feature;

use App\Mail\SubscriptionConfirmMail;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
        Mail::fake();
    }

    public function test_subscribing_stores_a_pending_subscriber_and_emails_a_confirmation_link(): void
    {
        $this->postJson(route('subscribe'), ['email' => ' Reader@Example.com ', 'frequency' => 'weekly'])
            ->assertOk()
            ->assertJsonPath('message', 'Almost done! Check your inbox and tap the link to confirm.');

        $subscriber = Subscriber::sole();
        $this->assertSame('reader@example.com', $subscriber->email);
        $this->assertSame('weekly', $subscriber->frequency);
        $this->assertFalse($subscriber->isReceiving());
        Mail::assertQueued(SubscriptionConfirmMail::class, fn (SubscriptionConfirmMail $mail): bool => $mail->hasTo('reader@example.com'));
    }

    public function test_honeypot_submissions_are_not_stored(): void
    {
        $this->postJson(route('subscribe'), ['email' => 'bot@example.com', 'website' => 'https://spam.example'])->assertOk();

        $this->assertDatabaseCount('subscribers', 0);
        Mail::assertNothingSent();
    }

    public function test_subscribing_rejects_an_invalid_email(): void
    {
        $this->postJson(route('subscribe'), ['email' => 'not-an-email'])->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('subscribers', 0);
    }

    public function test_repeat_sign_up_within_the_cooldown_does_not_resend_the_confirmation(): void
    {
        $this->postJson(route('subscribe'), ['email' => 'reader@example.com'])->assertOk();
        $this->postJson(route('subscribe'), ['email' => 'reader@example.com'])->assertOk();

        Mail::assertQueuedCount(1);
    }

    public function test_signed_confirm_link_starts_the_subscription(): void
    {
        $subscriber = Subscriber::factory()->create();

        $this->get($subscriber->confirmUrl())->assertOk();

        $this->assertTrue($subscriber->fresh()->isReceiving());
    }

    public function test_confirm_link_is_rejected_when_tampered_or_expired(): void
    {
        $subscriber = Subscriber::factory()->create();
        $url = $subscriber->confirmUrl();

        $this->get($url.'x')->assertForbidden();
        $this->travel(Subscriber::CONFIRM_HOURS + 1)->hours();
        $this->get($url)->assertForbidden();

        $this->assertFalse($subscriber->fresh()->isReceiving());
    }

    public function test_one_click_unsubscribe_stops_the_digest(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create();

        $this->post($subscriber->unsubscribeUrl())->assertOk();

        $this->assertFalse($subscriber->fresh()->isReceiving());
    }
}
