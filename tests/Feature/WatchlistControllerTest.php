<?php

namespace Tests\Feature;

use App\Models\Ipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WatchlistControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
    }

    public function test_items_render_only_the_requested_ipos_with_their_upcoming_dates(): void
    {
        $watched = Ipo::factory()->open()->create(['name' => 'Watched Tech']);
        $other = Ipo::factory()->open()->create(['name' => 'Other Foods']);

        $this->get('/watchlist/items?slugs='.$watched->slug.',BAD%20SLUG,../x')->assertOk()
            ->assertSee('Watching 1 IPO')
            ->assertSee('Coming up in the next 14 days')
            ->assertSeeInOrder(['Watched Tech', 'IPO Closes', 'Tomorrow'])
            ->assertDontSee($other->url(), false);
    }

    public function test_items_show_the_empty_state_without_valid_slugs(): void
    {
        $this->get('/watchlist/items?slugs=')->assertOk()->assertSee('No IPOs in your watchlist yet');
    }
}
