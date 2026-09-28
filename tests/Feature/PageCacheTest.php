<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\IpoVote;
use App\Models\User;
use App\Support\PageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageCacheTest extends TestCase
{
    use RefreshDatabase;

    private Ipo $ipo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        // A fresh sync timestamp keeps the RefreshIpoData middleware from calling the API.
        cache()->forever('ipo:synced_at', now()->toIso8601String());
        config(['ipodarbar.page_cache.enabled' => true, 'ipodarbar.page_cache.store' => 'array']);

        Http::fake(['courses.finowings.com/*' => Http::response([
            'success' => true, 'data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'has_more' => false,
        ])]);

        $this->ipo = Ipo::factory()->open()->create(['name' => 'Alpha Tech', 'slug' => 'alpha-tech-ipo', 'gmp' => 15]);
    }

    public function test_pages_are_cached_and_each_visitor_gets_their_own_csrf_token(): void
    {
        $first = $this->get('/ipo-gmp')->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        $firstToken = session()->token();
        $first->assertSee('<meta name="csrf-token" content="'.$firstToken.'">', false);

        // Data changes without a flush: the cached page is served as it was.
        $this->ipo->update(['gmp' => 99]);

        $this->flushSession();
        $hit = $this->get('/ipo-gmp')->assertOk()->assertHeader('X-Page-Cache', 'HIT')->assertSee('+₹15');
        $secondToken = session()->token();

        $this->assertNotSame($firstToken, $secondToken);
        $hit->assertSee('<meta name="csrf-token" content="'.$secondToken.'">', false)
            ->assertDontSee($firstToken, false)
            ->assertDontSee(PageCache::TOKEN_PLACEHOLDER, false);

        $stored = PageCache::get('http://localhost:8787/ipo-gmp');
        $this->assertStringContainsString(PageCache::TOKEN_PLACEHOLDER, $stored);
        $this->assertStringNotContainsString($firstToken, $stored);
    }

    public function test_searches_filters_and_signed_in_users_are_never_cached(): void
    {
        $this->get('/ipo?q=alpha')->assertOk()->assertHeaderMissing('X-Page-Cache');
        $this->get('/sme-ipo?status=open')->assertOk()->assertHeaderMissing('X-Page-Cache');
        $this->get('/ipo/search/suggest?q=alpha')->assertOk()->assertHeaderMissing('X-Page-Cache');

        $this->get('/current-ipo?page=2')->assertOk()->assertHeader('X-Page-Cache', 'MISS');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/')->assertOk()->assertHeaderMissing('X-Page-Cache');
    }

    public function test_flashed_messages_bypass_the_cache(): void
    {
        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');

        $this->withSession(['_flash' => ['old' => ['subscribe_status'], 'new' => []], 'subscribe_status' => 'Check your inbox to confirm.'])
            ->get('/')->assertOk()
            ->assertHeaderMissing('X-Page-Cache')
            ->assertSee('Check your inbox to confirm.');
    }

    public function test_admin_changes_flush_the_cache(): void
    {
        $this->get('/ipo-gmp')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/ipo-gmp')->assertHeader('X-Page-Cache', 'HIT');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.settings.update'), [])
            ->assertRedirect(route('admin.settings'));

        // A new anonymous visitor (the admin's session still holds the "Settings saved" flash).
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get('/ipo-gmp')->assertHeader('X-Page-Cache', 'MISS');
    }

    public function test_a_sync_flushes_the_cache(): void
    {
        $this->get('/ipo-gmp')->assertHeader('X-Page-Cache', 'MISS');

        config(['ipodarbar.ipo_api.key' => 'test-key']);
        Http::fake(['www.finowings.com/*' => Http::response([
            'status' => 'success', 'data' => [],
            'pagination' => ['limit' => 50, 'offset' => 0, 'total' => 0, 'has_more' => false],
        ])]);
        $this->artisan('ipo:sync')->assertSuccessful();

        $this->get('/ipo-gmp')->assertHeader('X-Page-Cache', 'MISS');
    }

    public function test_ipo_pages_carry_no_personal_vote_and_a_vote_refreshes_the_page(): void
    {
        $page = $this->get($this->ipo->url())->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        $page->assertDontSee('poll-row mine', false)->assertCookieMissing(IpoVote::COOKIE);

        $this->postJson(route('ipos.vote', $this->ipo), ['choice' => 'apply'])->assertOk();

        // The vote drops this IPO's cached page, so the next visitor sees the new count.
        $this->get($this->ipo->url())->assertHeader('X-Page-Cache', 'MISS')->assertSee('1 vote');
    }
}
