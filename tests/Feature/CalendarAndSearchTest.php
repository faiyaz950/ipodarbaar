<?php

namespace Tests\Feature;

use App\Models\Ipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarAndSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 24 Sep 2026.
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
        Http::fake(['courses.finowings.com/*' => Http::response(['success' => true, 'data' => [[
            'id' => 91, 'headline' => 'Alpha Tech IPO allotment date announced', 'news_detail' => '<p>Body</p>',
            'date' => '2026-09-23', 'created_at' => '2026-09-23 10:00:00', 'category' => ['id' => 9, 'name' => 'IPO', 'slug' => 'ipo'],
        ]], 'total' => 1, 'page' => 1, 'per_page' => 100, 'has_more' => false])]);
    }

    public function test_ipo_calendar_file_has_the_four_key_dates(): void
    {
        $ipo = Ipo::factory()->create([
            'name' => 'Alpha, Tech', 'slug' => 'alpha-tech-ipo',
            'open_date' => '2026-09-23', 'close_date' => '2026-09-25', 'listing_date' => '2026-09-30',
        ]);

        $response = $this->get(route('ipos.ics', $ipo))->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="alpha-tech-ipo-dates.ics"');
        $ics = $response->getContent();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $ics);
        $this->assertSame(4, substr_count($ics, 'BEGIN:VEVENT'));
        // Closing Friday 25 Sep → allotment Monday 28 Sep (T+1 working day).
        foreach (['DTSTART;VALUE=DATE:20260923', 'DTSTART;VALUE=DATE:20260925', 'DTSTART;VALUE=DATE:20260928', 'DTSTART;VALUE=DATE:20260930'] as $line) {
            $this->assertStringContainsString($line."\r\n", $ics);
        }
        $this->assertStringContainsString('SUMMARY:Alpha\, Tech IPO allotment status (tentative)', $ics);
        $this->assertStringContainsString('UID:alpha-tech-ipo-opens@', $ics);
        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
    }

    public function test_calendar_feed_can_be_narrowed_to_a_board_or_a_watchlist(): void
    {
        Ipo::factory()->open()->create(['name' => 'Big Board', 'slug' => 'big-board-ipo']);
        Ipo::factory()->open()->sme()->create(['name' => 'Small Shop', 'slug' => 'small-shop-ipo']);
        Ipo::factory()->create(['name' => 'Long Gone', 'slug' => 'long-gone-ipo', 'open_date' => '2025-01-06', 'close_date' => '2025-01-08', 'listing_date' => '2025-01-13']);

        $all = $this->get('/ipo-calendar.ics')->assertOk()->getContent();
        $this->assertStringContainsString('SUMMARY:Big Board IPO opens', $all);
        $this->assertStringContainsString('SUMMARY:Small Shop IPO opens', $all);
        $this->assertStringNotContainsString('Long Gone', $all);

        $sme = $this->get('/ipo-calendar.ics?type=sme')->getContent();
        $this->assertStringContainsString('X-WR-CALNAME:SME IPO calendar', $sme);
        $this->assertStringNotContainsString('Big Board', $sme);

        $watch = $this->get('/ipo-calendar.ics?ipos=big-board-ipo,Bad%20Slug')->getContent();
        $this->assertStringContainsString('X-WR-CALNAME:My IPO watchlist', $watch);
        $this->assertStringNotContainsString('Small Shop', $watch);
    }

    public function test_ipo_and_calendar_pages_offer_calendar_links(): void
    {
        $ipo = Ipo::factory()->upcoming()->create(['name' => 'Soon Co', 'slug' => 'soon-co-ipo']);

        $this->get($ipo->url())->assertOk()
            ->assertSee(route('ipos.ics', $ipo))
            ->assertSee('https://calendar.google.com/calendar/render?action=TEMPLATE&amp;text=Soon+Co+IPO+opens', false);

        $this->get('/ipo-calendar')->assertOk()
            ->assertSee('Subscribe to the IPO calendar')
            ->assertSee('webcal://localhost:8787/ipo-calendar.ics', false);
    }

    public function test_site_search_finds_ipos_pages_guides_calculators_and_news(): void
    {
        Ipo::factory()->open()->create(['name' => 'Alpha Tech', 'slug' => 'alpha-tech-ipo']);

        $this->get('/search?q=allotment')->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee(route('ipos.allotment'))
            ->assertSee(route('guides.show', 'how-to-check-ipo-allotment-status'))
            ->assertSee(route('calculators.show', 'ipo-allotment-chance'))
            ->assertSee('Alpha Tech IPO allotment date announced');

        $this->get('/search?q=alpha')->assertOk()->assertSee('alpha-tech-ipo" class="co-name"', false);

        $this->get('/search?q=zzqxw')->assertOk()->assertSee('Nothing found for “zzqxw”', false);
        $this->get('/search')->assertOk()->assertSee('Search IPO Darbaar');
    }

    public function test_header_search_goes_to_site_search(): void
    {
        $this->get('/about')->assertOk()->assertSee('<form class="search" action="'.route('search').'"', false);

        // The sitelinks search box schema lives on the home page.
        $this->get('/')->assertOk()->assertSee('"urlTemplate":"'.route('search').'?q={search_term_string}"', false);
    }
}
