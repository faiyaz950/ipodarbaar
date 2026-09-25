<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\IpoGmpHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private Ipo $ipo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        // A fresh sync timestamp keeps the RefreshIpoData middleware from calling the API.
        cache()->forever('ipo:synced_at', now()->toIso8601String());

        $this->ipo = Ipo::create([
            'api_id' => 10, 'slug' => 'alpha-tech-ipo', 'name' => 'Alpha Tech', 'title' => 'Alpha Tech IPO',
            'type' => 'mainboard', 'price' => 120, 'price_min' => 110, 'gmp' => 15, 'lot_size' => 125,
            'open_date' => '2026-09-23', 'close_date' => '2026-09-25', 'listing_date' => '2026-09-30',
            'source_created_at' => '2026-09-20 00:00:00',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_guests_are_sent_to_login_and_non_admins_are_forbidden(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Log in');

        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_only_admin_accounts_can_log_in(): void
    {
        User::factory()->create(['email' => 'user@example.com', 'password' => 'secret-password']);
        $this->post('/admin/login', ['email' => 'user@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        User::factory()->create(['email' => 'admin@example.com', 'password' => 'secret-password', 'is_admin' => true]);
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertRedirect('/admin');
        $this->assertAuthenticated();
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin')->assertOk()->assertSee('Missing registrar')->assertSee('Alpha Tech');
        $this->get('/admin/ipos')->assertOk()->assertSee('Alpha Tech');
        $this->get('/admin/ipos?filter=all&q=alpha')->assertOk()->assertSee('Alpha Tech');
        $this->get('/admin/ipos/alpha-tech-ipo/edit')->assertOk()->assertSee('Save changes');
    }

    public function test_update_locks_changed_api_fields_and_records_gmp(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/ipos/alpha-tech-ipo', [
                'name' => 'Alpha Tech', 'type' => 'mainboard', 'price_min' => 110, 'price' => 120,
                'lot_size' => 150, 'gmp' => 22, 'open_date' => '2026-09-23', 'close_date' => '2026-09-25',
                'listing_date' => '2026-09-30', 'registrar' => 'KFin Technologies',
                'subscription_retail' => 3.5, 'subscription_total' => 12.25,
                'about' => "Alpha Tech makes widgets.\n\nIt is profitable.",
            ])
            ->assertRedirect('/admin/ipos/alpha-tech-ipo/edit');

        $ipo = $this->ipo->fresh();
        $this->assertEqualsCanonicalizing(['lot_size', 'gmp'], $ipo->locked_fields);
        $this->assertSame(150, $ipo->lot_size);
        $this->assertNotNull($ipo->subscription_updated_at);
        $this->assertSame(22.0, IpoGmpHistory::where('ipo_id', $ipo->id)->value('gmp'));

        $this->post('/admin/ipos/alpha-tech-ipo/unlock', ['field' => 'gmp'])->assertRedirect();
        $this->assertSame(['lot_size'], $ipo->fresh()->locked_fields);

        $this->post('/admin/ipos/alpha-tech-ipo/unlock', ['field' => 'about'])->assertStatus(422);
    }

    public function test_ipo_page_shows_editorial_data_and_gmp_trend(): void
    {
        $this->ipo->update([
            'registrar' => 'KFin Technologies', 'subscription_retail' => 3.5, 'subscription_total' => 12.25,
            'listing_price' => 150, 'about' => 'Alpha Tech makes widgets.',
        ]);
        IpoGmpHistory::insert([
            ['ipo_id' => $this->ipo->id, 'date' => '2026-09-22', 'gmp' => 10, 'price' => 120, 'created_at' => now(), 'updated_at' => now()],
            ['ipo_id' => $this->ipo->id, 'date' => '2026-09-23', 'gmp' => 15, 'price' => 120, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->get('/ipo/alpha-tech-ipo')->assertOk()
            ->assertSee('GMP Trend')
            ->assertSee('Subscription Status')
            ->assertSee('12.25x')
            ->assertSee('Listing Performance')
            ->assertSee('25.00%')
            ->assertSee('https://ipostatus.kfintech.com/', false)
            ->assertSee('Alpha Tech makes widgets.');
    }
}
