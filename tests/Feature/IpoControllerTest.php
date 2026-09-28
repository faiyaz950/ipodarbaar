<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\IpoDetail;
use App\Models\IpoFinancial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IpoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
    }

    public function test_ipo_page_renders_company_details_financials_and_anchor_lockins(): void
    {
        $ipo = Ipo::factory()->open()->create();
        IpoDetail::factory()->for($ipo)->create(['anchor_amount_cr' => 75, 'anchor_date' => '2026-09-22']);
        IpoFinancial::factory()->for($ipo)->create(['period' => 'FY25', 'period_end' => '2025-03-31', 'revenue' => 600, 'pat' => -12]);
        IpoFinancial::factory()->for($ipo)->create(['period' => 'FY26', 'period_end' => '2026-03-31', 'revenue' => 800, 'pat' => 64]);

        $this->get($ipo->url())->assertOk()
            ->assertSeeInOrder(['Issue Structure', 'Fresh issue', '₹150 Cr', '(60%)', 'Offer for sale', '₹100 Cr'])
            ->assertSee('Axis Capital, ICICI Securities')
            ->assertSeeInOrder(['Objects of the '.$ipo->name.' IPO', 'Repayment of borrowings', 'General corporate purposes'])
            ->assertSeeInOrder([$ipo->name.' Financials', 'FY25', 'FY26', 'PAT margin', '-2%', '8%'])
            ->assertSee('class="pat neg"', false)
            ->assertSeeInOrder(['Key Performance Indicators', 'P/E (post issue)', '31.2x'])
            // Allotment is one working day after the 25 Sep close (Fri) → Mon 28 Sep; lock-ins +30 / +90 days.
            ->assertSeeInOrder(['Anchor Investors', '₹75 Cr', '28 Oct 2026', '27 Dec 2026'])
            ->assertSee('https://www.sebi.gov.in/filings/public-issues/example-rhp.pdf', false);
    }

    public function test_ipo_page_hides_company_cards_without_admin_details(): void
    {
        $ipo = Ipo::factory()->open()->create();

        $this->get($ipo->url())->assertOk()
            ->assertDontSee('Issue Structure')
            ->assertDontSee('id="financials"', false)
            ->assertDontSee('Anchor Investors');
    }

    public function test_ipo_page_structured_data_cannot_be_broken_out_of_by_the_company_name(): void
    {
        $ipo = Ipo::factory()->open()->create(['name' => 'Evil</script><script>alert(1)</script> Ltd']);

        $this->get($ipo->url())->assertOk()
            ->assertDontSee('</script><script>alert(1)', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_search_results_are_not_indexed(): void
    {
        $this->get('/ipo?q=alpha')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/ipo')->assertOk()->assertSee('<meta name="robots" content="index, follow, max-image-preview:large', false);
    }

    public function test_search_suggestions_include_the_slug_for_the_compare_picker(): void
    {
        $ipo = Ipo::factory()->create(['name' => 'Zeta Robotics', 'slug' => 'zeta-robotics-ipo']);

        $this->getJson('/ipo/search/suggest?q=zeta')->assertOk()->assertJsonPath('0.slug', $ipo->slug);
    }
}
