<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonetizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
        config(['app.debug' => false]);
    }

    public function test_configured_ad_slots_render_adsense_units_with_a_label(): void
    {
        $ipo = Ipo::factory()->open()->create();
        app(Settings::class)->set([
            'ads.enabled' => true,
            'ads.client' => 'ca-pub-1234567890123456',
            'ads.slots.ipo_sidebar' => '9876543210',
        ]);

        $this->get($ipo->url())->assertOk()
            ->assertSee('pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890123456', false)
            ->assertSeeInOrder(['Advertisement', 'data-ad-slot="9876543210"'], false)
            ->assertDontSee('data-ad="ipo_after_gmp"', false);
    }

    public function test_no_ad_code_is_rendered_while_ads_are_switched_off(): void
    {
        $ipo = Ipo::factory()->open()->create();
        app(Settings::class)->set(['ads.enabled' => false, 'ads.client' => 'ca-pub-1234567890123456', 'ads.slots.ipo_sidebar' => '9876543210']);

        $this->get($ipo->url())->assertOk()
            ->assertDontSee('adsbygoogle', false)
            ->assertDontSee('data-ad=', false);
    }

    public function test_admin_can_hide_and_show_all_ads_with_one_click(): void
    {
        $ipo = Ipo::factory()->open()->create();
        $this->configureAds();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.settings.ads.toggle'))->assertRedirect(route('admin.settings'));
        $this->get($ipo->url())->assertOk()->assertDontSee('data-ad=', false)->assertDontSee('adsbygoogle', false);

        $this->actingAs($admin)->post(route('admin.settings.ads.toggle'))->assertRedirect();
        $this->get($ipo->url())->assertOk()->assertSee('data-ad="ipo_sidebar"', false)->assertSee('data-ad="ipo_after_gmp"', false);
    }

    public function test_admin_can_hide_a_single_ad_placement(): void
    {
        $ipo = Ipo::factory()->open()->create();
        $this->configureAds();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.settings.ads.toggle'), ['slot' => 'ipo_sidebar'])
            ->assertRedirect();

        $this->get($ipo->url())->assertOk()
            ->assertDontSee('data-ad="ipo_sidebar"', false)
            ->assertSee('data-ad="ipo_after_gmp"', false);
    }

    public function test_ad_toggle_rejects_guests_and_unknown_placements(): void
    {
        $this->configureAds();

        $this->post(route('admin.settings.ads.toggle'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.settings.ads.toggle'), ['slot' => 'popup'])
            ->assertSessionHasErrors('slot');

        $this->assertTrue(app(Settings::class)->enabled('ads.enabled'));
    }

    public function test_saving_the_settings_form_keeps_the_ads_switch(): void
    {
        $this->configureAds();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.settings.update'), ['ads_client' => 'ca-pub-1234567890123456'])
            ->assertRedirect();

        $this->assertTrue(app(Settings::class)->enabled('ads.enabled'));
    }

    public function test_broker_links_are_labelled_sponsored_and_not_followed(): void
    {
        $ipo = Ipo::factory()->open()->create();
        app(Settings::class)->set(['brokers' => [['name' => 'Zerodha', 'url' => 'https://zerodha.com/open?ref=darbaar', 'tagline' => 'Free delivery']]]);

        $this->get($ipo->url())->assertOk()
            ->assertSeeInOrder(['Sponsored', 'href="https://zerodha.com/open?ref=darbaar"', 'rel="sponsored nofollow noopener"', 'Zerodha'], false);
    }

    public function test_analytics_scripts_load_only_when_ids_are_configured(): void
    {
        $this->get('/about')->assertOk()
            ->assertSee('window.darbaarTrack', false)
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee('cloudflareinsights.com', false);

        app(Settings::class)->set(['analytics.ga4_id' => 'G-ABC1234567', 'analytics.cf_token' => str_repeat('a1', 16)]);

        $this->get('/about')->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-ABC1234567', false)
            ->assertSee('cloudflareinsights.com/beacon.min.js', false);
    }

    private function configureAds(): void
    {
        app(Settings::class)->set([
            'ads.enabled' => true,
            'ads.client' => 'ca-pub-1234567890123456',
            'ads.slots.ipo_sidebar' => '9876543210',
            'ads.slots.ipo_after_gmp' => '1234567890',
        ]);
    }
}
