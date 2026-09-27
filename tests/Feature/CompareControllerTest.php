<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\IpoDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompareControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
    }

    public function test_compare_page_lists_ipos_in_requested_order_and_highlights_the_best_values(): void
    {
        $alpha = Ipo::factory()->create(['name' => 'Alpha Tech', 'gmp' => 30]);
        $beta = Ipo::factory()->create(['name' => 'Beta Foods', 'gmp' => 6]);
        $gamma = Ipo::factory()->create(['name' => 'Gamma Steel', 'gmp' => 12]);
        IpoDetail::factory()->for($alpha)->create(['pe_post' => -8]);
        IpoDetail::factory()->for($beta)->create(['pe_post' => 22]);
        IpoDetail::factory()->for($gamma)->create(['pe_post' => 35]);

        $response = $this->get('/ipo-compare?ipos='.$beta->slug.','.$alpha->slug.','.$gamma->slug)->assertOk()
            ->assertSee('noindex, follow', false)
            ->assertSeeInOrder(['Remove Beta Foods', 'Remove Alpha Tech', 'Remove Gamma Steel']);

        // Highest GMP wins; for P/E the lowest positive value wins, a loss-making negative P/E never does.
        $response->assertSee('<td class="best">+₹30</td>', false)
            ->assertDontSee('<td class="best">+₹12</td>', false)
            ->assertSee('<td class="best">22x</td>', false)
            ->assertDontSee('<td class="best">-8x</td>', false);
    }

    public function test_compare_page_caps_selection_and_ignores_invalid_slugs(): void
    {
        $ipos = Ipo::factory()->count(4)->create();
        $query = $ipos->pluck('slug')->prepend('../etc/passwd')->implode(',');

        $response = $this->get('/ipo-compare?ipos='.urlencode($query))->assertOk();

        $ipos->take(3)->each(fn (Ipo $ipo) => $response->assertSee('Remove '.$ipo->name));
        $response->assertDontSee('Remove '.$ipos->last()->name)
            ->assertDontSee('Add another IPO');
    }

    public function test_compare_page_without_selection_shows_the_picker(): void
    {
        $this->get('/ipo-compare')->assertOk()
            ->assertSee('Pick IPOs to compare')
            ->assertSee('data-compare-url', false);
    }

    public function test_watchlist_links_to_compare_when_two_ipos_are_watched(): void
    {
        [$first, $second] = Ipo::factory()->count(2)->open()->create();

        $this->get('/watchlist/items?slugs='.$first->slug.','.$second->slug)->assertOk()
            ->assertSee('Compare 2');
    }
}
