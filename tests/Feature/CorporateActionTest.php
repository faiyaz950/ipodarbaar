<?php

namespace Tests\Feature;

use App\Models\CorporateAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CorporateActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
        Http::fake(['courses.finowings.com/*' => Http::response(['success' => true, 'data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'has_more' => false])]);
    }

    public function test_empty_lists_render_but_are_not_indexed(): void
    {
        foreach (['/buyback', '/rights-issue', '/ncd'] as $path) {
            $this->get($path)->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
        }
    }

    public function test_buyback_list_groups_open_upcoming_and_recently_closed_offers(): void
    {
        CorporateAction::factory()->create(['company' => 'Open Motors']);
        CorporateAction::factory()->upcoming()->create(['company' => 'Soon Steel']);
        CorporateAction::factory()->closed()->create(['company' => 'Done Pharma']);
        CorporateAction::factory()->create(['company' => 'Hidden Labs', 'is_published' => false]);
        CorporateAction::factory()->ncd()->create(['company' => 'Bond Finance']);

        $this->get('/buyback')->assertOk()
            ->assertSee('<title>Share Buyback 2026: Open &amp; Upcoming Buyback Offers | IPO Darbaar</title>', false)
            ->assertSee('<meta name="robots" content="index, follow', false)
            ->assertSeeInOrder(['Open Share Buybacks', 'Open Motors', 'Upcoming Share Buybacks', 'Soon Steel', 'Recently Closed', 'Done Pharma'])
            ->assertDontSee('Hidden Labs')
            ->assertDontSee('Bond Finance')
            ->assertSee('"@type":"FAQPage"', false);

        $this->get('/ncd')->assertOk()->assertSee('Bond Finance')->assertSee('CRISIL AA-/Stable');
    }

    public function test_offer_page_shows_its_details_and_rejects_the_wrong_list_or_hidden_offers(): void
    {
        $offer = CorporateAction::factory()->rights()->create(['company' => 'Rights Co', 'details' => "First paragraph.\n\nSecond paragraph.", 'source_url' => 'https://www.nseindia.com/offer.pdf']);
        $hidden = CorporateAction::factory()->create(['company' => 'Hidden Labs', 'is_published' => false]);

        $this->get($offer->url())->assertOk()
            ->assertSee('<title>Rights Co Rights Issue 2026: Price, Ratio &amp; Record Date | IPO Darbaar</title>', false)
            ->assertSeeInOrder(['Rights issue price', '₹250', 'Rights ratio', '1:5'])
            ->assertSee('<p>First paragraph.</p>', false)
            ->assertSee('<p>Second paragraph.</p>', false)
            ->assertSee('https://www.nseindia.com/offer.pdf', false);

        $this->get('/buyback/'.$offer->slug)->assertNotFound();
        $this->get($hidden->url())->assertNotFound();
    }

    public function test_admins_can_add_edit_and_delete_offers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.actions.create', ['type' => 'rights']))->assertOk()->assertSee('Add rights issue');

        $this->actingAs($admin)->post(route('admin.actions.store'), [
            'type' => 'buyback', 'company' => 'Tata Widgets', 'open_date' => '2026-10-05', 'close_date' => '2026-10-09',
            'price' => '1500', 'size_cr' => '900', 'method' => 'Tender offer', 'is_published' => '1',
        ])->assertRedirect();

        $offer = CorporateAction::query()->where('company', 'Tata Widgets')->firstOrFail();
        $this->assertSame('tata-widgets-buyback-2026', $offer->slug);
        $this->get($offer->url())->assertOk();

        // A second offer by the same company in the same year gets its own address.
        $this->actingAs($admin)->post(route('admin.actions.store'), ['type' => 'buyback', 'company' => 'Tata Widgets', 'open_date' => '2026-12-01', 'is_published' => '1']);
        $this->assertDatabaseHas('corporate_actions', ['slug' => 'tata-widgets-buyback-2026-2']);

        $this->actingAs($admin)->put(route('admin.actions.update', $offer), ['type' => 'buyback', 'company' => 'Tata Widgets', 'price' => '1600'])
            ->assertRedirect(route('admin.actions.edit', $offer));
        $this->assertEqualsWithDelta(1600, $offer->fresh()->price, 0.01);
        $this->assertFalse($offer->fresh()->is_published);

        $this->actingAs($admin)->delete(route('admin.actions.destroy', $offer))->assertRedirect();
        $this->assertModelMissing($offer);
    }

    public function test_admin_form_validates_the_rights_ratio_and_links(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.actions.store'), ['type' => 'rights', 'company' => 'Ratio Co', 'ratio' => 'one in five', 'source_url' => 'http://insecure.example'])
            ->assertSessionHasErrors(['ratio', 'source_url']);

        $this->actingAs(User::factory()->create())->get(route('admin.actions.index'))->assertForbidden();
    }

    public function test_sitemap_lists_published_offers(): void
    {
        $offer = CorporateAction::factory()->create(['company' => 'Open Motors']);
        CorporateAction::factory()->create(['company' => 'Hidden Labs', 'is_published' => false]);

        $this->get('/sitemaps/pages.xml')->assertOk()
            ->assertSee(route('actions.buyback'))
            ->assertSee($offer->url())
            ->assertDontSee('hidden-labs')
            ->assertDontSee(route('actions.ncd'));
    }
}
