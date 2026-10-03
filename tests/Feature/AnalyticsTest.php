<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Analytics\AnalyticsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Mobile Safari/537.36';

    private const MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
    }

    private function beacon(string $uri, array $data, string $ua = self::ANDROID, string $ip = '203.0.113.7'): TestResponse
    {
        return $this->call('POST', $uri, [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_USER_AGENT' => $ua, 'REMOTE_ADDR' => $ip], json_encode($data));
    }

    private function pageView(string $path, array $extra = [], string $ua = self::ANDROID, string $ip = '203.0.113.7'): TestResponse
    {
        return $this->beacon('/d/v', array_merge(['p' => $path, 't' => 'Live IPO GMP Today | IPO Darbaar', 'r' => 'https://www.google.com/', 'u' => ['', '', '']], $extra), $ua, $ip);
    }

    public function test_a_page_view_is_recorded_without_cookies(): void
    {
        $response = $this->pageView('/ipo-gmp')->assertNoContent();
        $this->assertSame([], $response->headers->getCookies());

        $row = DB::table('page_views')->sole();
        $this->assertSame('/ipo-gmp', $row->path);
        $this->assertSame('ipos.gmp', $row->route);
        $this->assertSame('GMP', $row->section);
        $this->assertSame('Google', $row->source);
        $this->assertEquals(1, $row->is_entry);
        $this->assertSame(['Mobile', 'Chrome', 'Android'], [$row->device, $row->browser, $row->os]);
        $this->assertSame(16, strlen($row->visitor));
        $this->assertSame('Live IPO GMP Today', DB::table('analytics_pages')->where('path', '/ipo-gmp')->value('title'));
    }

    public function test_reloads_bots_and_private_paths_are_not_counted(): void
    {
        $this->pageView('/ipo-gmp');
        $this->pageView('/ipo-gmp');                                   // reload within 30 seconds
        $this->pageView('/ipo-gmp', [], 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $this->pageView('/admin/analytics');
        $this->pageView('https://evil.example/');
        $this->pageView('/internal/ipo-push');
        $this->assertSame(1, DB::table('page_views')->count());

        Carbon::setTestNow(now()->addSeconds(31));
        $this->pageView('/ipo-gmp');
        $this->assertSame(2, DB::table('page_views')->count());
    }

    public function test_sources_sections_and_campaigns_are_classified(): void
    {
        $this->pageView('/blog', ['r' => 'https://t.me/ipodarbaar']);
        $this->pageView('/does-not-exist', ['r' => '']);
        $this->pageView('/ipo-gmp-accuracy', ['r' => 'http://localhost/ipo-gmp'], self::MAC);
        $this->pageView('/', ['r' => '', 'u' => ['newsletter', 'email', 'oct-digest']], self::MAC, '198.51.100.9');

        $rows = DB::table('page_views')->orderBy('id')->get();
        $this->assertSame(['Telegram', 'Blog'], [$rows[0]->source, $rows[0]->section]);
        $this->assertSame(['Direct', 'Not found'], [$rows[1]->source, $rows[1]->section]);
        $this->assertSame('Internal', $rows[2]->source);
        $this->assertEquals(0, $rows[2]->is_entry);
        $this->assertSame(['Desktop', 'Safari', 'macOS'], [$rows[2]->device, $rows[2]->browser, $rows[2]->os]);
        $this->assertSame(['Newsletter', 'oct-digest', 'Home'], [$rows[3]->source, $rows[3]->utm_campaign, $rows[3]->section]);
    }

    public function test_events_are_recorded(): void
    {
        $this->beacon('/d/e', ['n' => 'watchlist_add', 'l' => 'orient-cables-india-ipo', 'p' => '/ipo/orient-cables-india-ipo'])->assertNoContent();
        $this->beacon('/d/e', ['n' => 'Bad Name!', 'p' => '/']);

        $this->assertSame(1, DB::table('analytics_events')->count());
        $this->assertSame('orient-cables-india-ipo', DB::table('analytics_events')->value('label'));
    }

    public function test_finished_days_are_rolled_up_and_kept_after_raw_rows_expire(): void
    {
        foreach ([[2, '/ipo-gmp', 'a'], [2, '/ipo-gmp', 'b'], [1, '/blog', 'a'], [0, '/ipo-gmp', 'c']] as [$daysAgo, $path, $visitor]) {
            DB::table('page_views')->insert([
                'path' => $path, 'route' => null, 'section' => 'GMP', 'source' => 'Google', 'referrer_host' => 'google.com', 'is_entry' => true,
                'visitor' => str_pad($visitor, 16, '0'), 'device' => 'Mobile', 'browser' => 'Chrome', 'os' => 'Android', 'created_at' => now()->subDays($daysAgo),
            ]);
        }
        $report = app(AnalyticsReport::class);

        $top = collect($report->top('page', now()->subDays(6), now()))->keyBy('value');
        $this->assertSame(3, $top['/ipo-gmp']['views']);
        $this->assertSame(1, $top['/blog']['views']);
        $this->assertSame(['views' => 4, 'visitors' => 4], $report->totals(now()->subDays(6), now()));
        $this->assertSame(1, DB::table('analytics_daily')->where('dimension', 'total')->where('date', now()->subDays(2)->toDateString())->count());
        $this->assertSame(2, collect($report->series(now()->subDays(6), now()))->firstWhere('label', now()->subDays(2)->format('j M'))['views']);

        // 100 days later the raw rows are gone, but the daily totals still answer.
        Carbon::setTestNow(now()->addDays(100));
        $this->artisan('analytics:rollup')->expectsOutputToContain('removed 4 old page views')->assertSuccessful();
        $this->assertSame(0, DB::table('page_views')->count());
        $this->assertSame(['views' => 3, 'visitors' => 3], $report->totals(now()->subDays(103), now()->subDays(100)));
    }

    public function test_admin_sees_page_views_with_links(): void
    {
        $this->pageView('/ipo-gmp');
        $this->pageView('/blog', ['t' => 'IPO Blog | IPO Darbaar', 'r' => 'https://t.me/x'], self::MAC, '198.51.100.9');
        $this->beacon('/d/e', ['n' => 'share', 'l' => 'whatsapp', 'p' => '/ipo-gmp']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/analytics?range=today')->assertOk()
            ->assertSee('Analytics')
            ->assertSee('Live IPO GMP Today')
            ->assertSee('href="'.rtrim(config('app.url'), '/').'/ipo-gmp"', false)
            ->assertSee(route('admin.analytics.page', ['path' => '/ipo-gmp', 'range' => 'today']))
            ->assertSee('Telegram')
            ->assertSee('Share')
            ->assertSee('whatsapp (1)');

        $this->actingAs($admin)->get('/admin/analytics/page?path=%2Fipo-gmp&range=7d')->assertOk()->assertSee('Live IPO GMP Today')->assertSee('Google');

        $csv = $this->actingAs($admin)->get('/admin/analytics/export.csv?range=today')->assertOk()->streamedContent();
        $this->assertStringContainsString('/ipo-gmp,"Live IPO GMP Today",GMP,', $csv);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Today on the site');
        $this->actingAs(User::factory()->create())->get('/admin/analytics')->assertForbidden();
    }

    public function test_the_site_script_sends_views_and_admins_are_left_out(): void
    {
        $this->assertStringContainsString("beacon('/d/v'", (string) file_get_contents(public_path('assets/js/app.js')));
        $this->get('/robots.txt')->assertSee('Disallow: /d/');
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/analytics')->assertSee("localStorage.setItem('darbaar:no-track', '1')", false);
        $this->artisan('schedule:list')->expectsOutputToContain('analytics:rollup')->assertSuccessful();
    }
}
