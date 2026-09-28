<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TriggerRelayTest extends TestCase
{
    public function test_full_run_is_dispatched_to_the_relay_workflow(): void
    {
        config(['ipodarbar.relay.token' => 'github_pat_test']);
        Http::fake(['api.github.com/*' => Http::response(null, 204)]);

        $this->artisan('relay:trigger', ['--full' => true])->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/faiyaz950/ipodarbaar/actions/workflows/ipo-relay.yml/dispatches'
            && $request->hasHeader('Authorization', 'Bearer github_pat_test')
            && $request['ref'] === 'main'
            && $request['inputs'] === ['full' => 'true']);
    }

    public function test_rejected_trigger_fails_the_command(): void
    {
        config(['ipodarbar.relay.token' => 'github_pat_expired']);
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);

        $this->artisan('relay:trigger')->expectsOutputToContain('HTTP 401')->assertFailed();
    }

    public function test_nothing_is_sent_without_a_token(): void
    {
        config(['ipodarbar.relay.token' => null]);
        Http::fake();

        $this->artisan('relay:trigger')->assertSuccessful();

        Http::assertNothingSent();
    }
}
