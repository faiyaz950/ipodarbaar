<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Ipo;
use App\Models\User;
use App\Services\Blog\BlogImageService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutoBlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday morning, when the scheduler writes the daily post.
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:50:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
        Storage::fake('uploads');
    }

    private function closingMainboard(): Ipo
    {
        $ipo = Ipo::factory()->create([
            'name' => 'Vishal Nirmiti', 'slug' => 'vishal-nirmiti-ipo', 'type' => 'mainboard', 'exchange' => 'BSE, NSE',
            'price_min' => 208, 'price' => 220, 'gmp' => 20, 'lot_size' => 68, 'issue_size' => 178,
            'open_date' => '2026-09-30', 'close_date' => '2026-10-05', 'listing_date' => '2026-10-08',
            'registrar' => 'Bigshare Services',
            'about' => 'Vishal Nirmiti IPO is a Mainboard IPO by Vishal Nirmiti Limited, which is a builder of roads and bridges. More text.',
        ]);
        $ipo->detail()->create([
            'fresh_issue_cr' => 128, 'ofs_cr' => 50, 'promoter_holding_pre' => 100, 'promoter_holding_post' => 72.5,
            'objects' => "Funding working capital requirements. ~ Rs. 75 Cr.\nGeneral Corporate Purposes.",
            'roe' => 33.67, 'lead_managers' => 'Example Capital Ltd.',
        ]);
        foreach ([['FY24', '2024-03-31', 247.93, 3.45], ['FY25', '2025-03-31', 324.86, 23.64], ['FY26', '2026-03-31', 344.13, 24.98]] as [$period, $end, $revenue, $pat]) {
            $ipo->financials()->create(['period' => $period, 'period_end' => $end, 'revenue' => $revenue, 'pat' => $pat, 'net_worth' => 86.34, 'borrowings' => 87.42]);
        }

        return $ipo;
    }

    private function scene(): array
    {
        return [
            'closing' => $this->closingMainboard(),
            'sme' => Ipo::factory()->create([
                'name' => 'Eventions', 'slug' => 'eventions-ipo', 'type' => 'sme', 'price_min' => 112, 'price' => 118, 'lot_size' => 1200, 'gmp' => null,
                'issue_size' => 38.12, 'open_date' => '2026-09-30', 'close_date' => '2026-10-05', 'listing_date' => '2026-10-08',
            ]),
            'opening' => Ipo::factory()->create([
                'name' => 'R.K. Fashion Accessories', 'slug' => 'rk-fashion-accessories-ipo', 'type' => 'sme', 'price_min' => 77, 'price' => 82, 'lot_size' => 1600,
                'issue_size' => 34.99, 'gmp' => null, 'open_date' => '2026-10-05', 'close_date' => '2026-10-07', 'listing_date' => '2026-10-12',
            ]),
            'listing' => Ipo::factory()->create([
                'name' => 'Orient Cables India', 'slug' => 'orient-cables-india-ipo', 'price_min' => 258, 'price' => 272, 'gmp' => 111,
                'open_date' => '2026-09-25', 'close_date' => '2026-09-29', 'listing_date' => '2026-10-05',
            ]),
            // A bad row: bidding "open" for five weeks. It must be left out.
            'broken' => Ipo::factory()->create([
                'name' => 'Papadmalji Agro Foods', 'slug' => 'papadmalji-agro-foods-ipo', 'type' => 'sme',
                'open_date' => '2026-09-29', 'close_date' => '2026-11-01', 'listing_date' => '2026-11-07',
            ]),
        ];
    }

    public function test_daily_post_is_written_from_the_days_ipo_data(): void
    {
        $ipos = $this->scene();

        $this->artisan('blog:auto daily')->expectsOutputToContain('Published: '.route('blog.show', 'ipo-today-5-october-2026'))->assertSuccessful();

        $post = BlogPost::query()->where('slug', 'ipo-today-5-october-2026')->sole();
        $this->assertSame('daily', $post->category);
        $this->assertTrue($post->isLive());
        $this->assertSame('IPO Today (5 Oct 2026): 2 IPOs Closing, 1 Listing, 1 Opening', $post->title);
        $this->assertStringContainsString('Last day to apply: Vishal Nirmiti and Eventions', $post->takeaways);
        $this->assertGreaterThanOrEqual(600, str_word_count(strip_tags($post->body)));

        $body = $post->body;
        // Profile: business, issue structure, price, GMP, financial growth, use of money.
        $this->assertStringContainsString('<h3>Vishal Nirmiti IPO</h3>', $body);
        $this->assertStringContainsString('Vishal Nirmiti is a builder of roads and bridges.', $body);
        $this->assertStringContainsString('fresh issue of ₹128 Cr', $body);
        $this->assertStringContainsString('Promoter holding falls from 100% to 72.5%', $body);
        $this->assertStringContainsString('one lot of 68 shares costs ₹14,960', $body);
        $this->assertStringContainsString('premium (GMP) of ₹20, 9.1% above the upper price, which points to a listing around ₹240', $body);
        $this->assertStringContainsString('a compound annual growth of 17.8%', $body);
        $this->assertStringContainsString('<li>Funding working capital requirements: about ₹75 crore</li>', $body);
        $this->assertStringContainsString('Lead manager: Example Capital Ltd.', $body);
        // SME closing table, listing table with the GMP estimate, FAQs and the automation note.
        $this->assertStringContainsString('₹1,41,600 (1,200 sh.)', $body);
        $this->assertStringContainsString('₹383', $body);
        $this->assertStringContainsString('<h2>Frequently asked questions</h2>', $body);
        $this->assertStringContainsString('compiled automatically', $body);
        $this->assertStringNotContainsString('Papadmalji', $body);

        $this->assertEqualsCanonicalizing(
            [$ipos['closing']->id, $ipos['sme']->id, $ipos['opening']->id, $ipos['listing']->id],
            $post->ipos->modelKeys()
        );

        if (app(BlogImageService::class)->supported()) {
            $this->assertSame('blog/auto/ipo-today-5-october-2026-cover.webp', $post->image_path);
            Storage::disk('uploads')->assertExists($post->image_path);
            Storage::disk('uploads')->assertExists('blog/auto/ipo-today-5-october-2026-gmp.webp');
            $this->assertStringContainsString('<img src="/uploads/blog/auto/ipo-today-5-october-2026-gmp.webp"', $body);
        }

        $this->get($post->url())->assertOk()->assertSee('IPO Today (5 Oct 2026)')->assertSee('<h2 id="ipos-closing-today">', false);
    }

    public function test_daily_post_is_written_once_and_can_be_rebuilt(): void
    {
        $this->scene();
        $this->artisan('blog:auto daily')->assertSuccessful();
        $published = BlogPost::query()->sole()->published_at;

        Carbon::setTestNow(now()->addHour());
        $this->artisan('blog:auto daily')->expectsOutputToContain('Already written')->assertSuccessful();
        $this->artisan('blog:auto daily --force')->expectsOutputToContain('Rebuilt')->assertSuccessful();

        $this->assertSame(1, BlogPost::query()->count());
        $this->assertTrue(BlogPost::query()->sole()->published_at->eq($published));
    }

    public function test_no_daily_post_at_weekends_or_on_quiet_days(): void
    {
        $this->artisan('blog:auto daily')->expectsOutputToContain('No IPO events')->assertSuccessful();

        $this->scene();
        $this->artisan('blog:auto daily --date=2026-10-04')->expectsOutputToContain('No IPO events')->assertSuccessful();
        $this->assertSame(0, BlogPost::query()->count());
    }

    public function test_switches_and_draft_option_are_respected(): void
    {
        $this->scene();
        app(Settings::class)->set(['blog.auto.daily' => false]);
        $this->artisan('blog:auto daily')->expectsOutputToContain('switched off')->assertSuccessful();
        $this->assertSame(0, BlogPost::query()->count());

        app(Settings::class)->set(['blog.auto.daily' => true, 'blog.auto.publish' => false]);
        $this->artisan('blog:auto daily')->assertSuccessful();
        $this->assertSame('draft', BlogPost::query()->sole()->status);
        $this->get('/blog/ipo-today-5-october-2026')->assertNotFound();
    }

    public function test_weekly_calendar_on_sunday_covers_the_coming_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 10:00:00', 'Asia/Kolkata'));
        $this->scene();

        $this->artisan('blog:auto weekly')->assertSuccessful();

        $post = BlogPost::query()->where('slug', 'ipos-this-week-5-oct-11-oct-2026')->sole();
        $this->assertSame('weekly-wrap', $post->category);
        $this->assertSame('IPOs This Week (5 Oct – 11 Oct 2026): 3 IPOs Open, 3 Listings', $post->title);
        $this->assertStringContainsString('<h2>Mainboard IPOs this week</h2>', $post->body);
        $this->assertStringContainsString('<h3>Vishal Nirmiti IPO</h3>', $post->body);
        $this->assertStringContainsString('<h2>IPO listings this week</h2>', $post->body);
        $this->assertStringContainsString('<h2>Allotment and listing timetable</h2>', $post->body);
        $this->assertStringNotContainsString('Papadmalji', $post->body);
        if (app(BlogImageService::class)->supported()) {
            Storage::disk('uploads')->assertExists('blog/auto/ipos-this-week-5-oct-11-oct-2026-week.webp');
        }
    }

    public function test_admin_can_switch_automation_and_write_a_post_now(): void
    {
        $this->scene();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/blog')->assertOk()->assertSee('Automatic posts')->assertSee('Write the daily ipo update now');

        $this->actingAs($admin)->put('/admin/blog/automation', ['daily' => '1', 'publish' => '1'])->assertRedirect('/admin/blog');
        $this->assertFalse(app(Settings::class)->enabled('blog.auto.weekly'));
        $this->assertTrue(app(Settings::class)->enabled('blog.auto.daily'));

        $this->actingAs($admin)->post('/admin/blog/automation/run', ['kind' => 'daily'])
            ->assertRedirect('/admin/blog')->assertSessionHas('status', fn (string $s): bool => str_contains($s, 'ipo-today-5-october-2026'));
        $this->assertSame(1, BlogPost::query()->count());

        $this->actingAs(User::factory()->create())->post('/admin/blog/automation/run', ['kind' => 'daily'])->assertForbidden();
    }

    public function test_home_page_shows_only_the_latest_daily_post(): void
    {
        BlogPost::factory()->create(['category' => 'daily', 'title' => 'IPO Today old one', 'published_at' => now()->subDays(2)]);
        BlogPost::factory()->create(['category' => 'daily', 'title' => 'IPO Today newest', 'published_at' => now()->subHour()]);
        BlogPost::factory()->create(['category' => 'explainers', 'title' => 'Rights issue explained', 'published_at' => now()->subDays(3)]);

        $this->get('/')->assertOk()->assertSee('IPO Today newest')->assertSee('Rights issue explained')->assertDontSee('IPO Today old one');
    }

    public function test_scheduler_runs_the_daily_and_weekly_posts(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('blog:auto daily')
            ->expectsOutputToContain('blog:auto weekly')
            ->assertSuccessful();
    }
}
