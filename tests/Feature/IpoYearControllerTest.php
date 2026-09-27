<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IpoYearControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
    }

    public function test_year_page_reports_funds_raised_and_listing_gains(): void
    {
        $this->listedIpo('Winner Labs', '2025-03-10', 250, listingPrice: 180);
        $this->listedIpo('Flat Metals', '2025-03-20', 100, listingPrice: 126);
        $this->listedIpo('Loser Foods', '2025-07-05', 50, listingPrice: 96);
        $this->listedIpo('Awaited Infra', '2025-11-02', 400, listingPrice: null);
        $this->listedIpo('Other Year', '2024-05-01', 999, listingPrice: 240);

        // Gains on a ₹120 band: +50%, +5%, -20% → average 11.67%, median 5%, 2 of 3 at a premium.
        $this->get('/ipo/2025')->assertOk()
            ->assertSeeInOrder(['Total IPOs', '4', 'Funds raised', '₹800 Cr'])
            ->assertSeeInOrder(['Average listing gain', '+11.67%', 'Median listing gain', '+5.00%', 'Listed at a premium', '66.7%'])
            ->assertSeeInOrder(['Top listing gains', 'Winner Labs', 'Flat Metals', 'Loser Foods'])
            ->assertSeeInOrder(['Weakest listings', 'Loser Foods'])
            ->assertSeeInOrder(['Largest issues', 'Awaited Infra'])
            ->assertDontSee('Other Year IPO');
    }

    public function test_year_page_returns_404_for_a_year_without_ipos(): void
    {
        $this->listedIpo('Winner Labs', '2025-03-10', 250, listingPrice: 180);

        $this->get('/ipo/2019')->assertNotFound();
    }

    public function test_report_card_links_every_year_with_ipos(): void
    {
        $this->listedIpo('Winner Labs', '2025-03-10', 250, listingPrice: 180);
        $this->listedIpo('Other Year', '2024-05-01', 100, listingPrice: 240);

        $this->get('/ipo-report-card')->assertOk()
            ->assertSeeInOrder([route('ipos.year', 2025), route('ipos.year', 2024)]);
        $this->get('/sitemaps/pages.xml')->assertOk()->assertSee(route('ipos.year', 2024));
    }

    public function test_saving_listing_price_in_admin_refreshes_cached_stats(): void
    {
        $ipo = $this->listedIpo('Winner Labs', '2025-03-10', 250, listingPrice: null);
        $this->get('/ipo/2025')->assertOk()->assertDontSee('+50.00%');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.ipos.update', $ipo), [
                'name' => $ipo->name, 'type' => 'mainboard', 'price' => 120, 'listing_price' => 180,
            ])->assertRedirect();

        $this->get('/ipo/2025')->assertOk()->assertSee('+50.00%');
    }

    private function listedIpo(string $name, string $openDate, float $issueSize, ?float $listingPrice): Ipo
    {
        $open = Carbon::parse($openDate);

        return Ipo::factory()->create([
            'name' => $name,
            'open_date' => $open->toDateString(),
            'close_date' => $open->copy()->addDays(2)->toDateString(),
            'listing_date' => $open->copy()->addDays(5)->toDateString(),
            'issue_size' => $issueSize,
            'listing_price' => $listingPrice,
        ]);
    }
}
