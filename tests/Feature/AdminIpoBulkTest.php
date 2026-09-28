<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminIpoBulkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_only_admins_can_use_the_bulk_editor(): void
    {
        $this->get(route('admin.ipos.bulk'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('admin.ipos.bulk'))->assertForbidden();
    }

    public function test_grid_saves_changed_values_and_locks_the_lot_size(): void
    {
        $alpha = Ipo::factory()->open()->create(['name' => 'Alpha Tech', 'lot_size' => 125, 'registrar' => null]);
        $beta = Ipo::factory()->upcoming()->create(['name' => 'Beta Foods', 'lot_size' => 60]);

        $this->actingAs($this->admin)->get(route('admin.ipos.bulk'))->assertOk()
            ->assertSee('name="ipos['.$alpha->id.'][lot_size]"', false)
            ->assertSee('Beta Foods');

        $this->actingAs($this->admin)->put(route('admin.ipos.bulk.update'), ['ipos' => [
            $alpha->id => ['lot_size' => '130', 'registrar' => 'KFin Technologies', 'subscription_total' => '12.5', 'listing_price' => ''],
            $beta->id => ['lot_size' => '60', 'registrar' => ''],
        ]])->assertRedirect()->assertSessionHas('status', 'Saved 1 IPO.');

        $alpha->refresh();
        $this->assertSame(130, $alpha->lot_size);
        $this->assertSame('KFin Technologies', $alpha->registrar);
        $this->assertEqualsWithDelta(12.5, $alpha->subscription_total, 0.001);
        $this->assertNotNull($alpha->subscription_updated_at);
        $this->assertContains('lot_size', $alpha->locked_fields);
        $this->assertNull($beta->fresh()->locked_fields);
    }

    public function test_grid_rejects_invalid_values(): void
    {
        $ipo = Ipo::factory()->open()->create(['name' => 'Alpha Tech']);

        $this->actingAs($this->admin)->from(route('admin.ipos.bulk'))
            ->put(route('admin.ipos.bulk.update'), ['ipos' => [$ipo->id => ['lot_size' => '-5']]])
            ->assertRedirect(route('admin.ipos.bulk'))
            ->assertSessionHasErrors('ipos.'.$ipo->id.'.lot_size');
    }

    public function test_csv_download_lists_current_values(): void
    {
        Ipo::factory()->open()->create(['name' => 'Alpha Tech', 'slug' => 'alpha-tech-ipo', 'lot_size' => 125]);

        $csv = $this->actingAs($this->admin)->get(route('admin.ipos.bulk.csv'))->assertOk()->streamedContent();

        $this->assertStringStartsWith('slug,name,status,lot_size,listing_price,registrar,subscription_total', $csv);
        $this->assertStringContainsString('alpha-tech-ipo,"Alpha Tech",', $csv);
    }

    public function test_csv_import_updates_matching_rows_and_reports_bad_ones(): void
    {
        $alpha = Ipo::factory()->listed()->create(['name' => 'Alpha Tech', 'slug' => 'alpha-tech-ipo', 'registrar' => 'Bigshare Services']);

        $csv = "\u{FEFF}slug,name,listing_price,registrar,lot_size\n"
            ."alpha-tech-ipo,Alpha Tech,150.5,,\n"
            ."missing-ipo,Nobody,100,,\n"
            ."alpha-tech-ipo,Alpha Tech,,,zero\n";

        $this->actingAs($this->admin)->from(route('admin.ipos.bulk'))
            ->post(route('admin.ipos.bulk.import'), ['file' => UploadedFile::fake()->createWithContent('ipos.csv', $csv)])
            ->assertRedirect(route('admin.ipos.bulk'))
            ->assertSessionHas('status', 'Imported: 1 IPO updated, 2 rows skipped.')
            ->assertSessionHas('import_errors', fn (array $errors): bool => str_contains($errors[0], 'Line 3') && str_contains($errors[1], 'Line 4 (Alpha Tech)'));

        $alpha->refresh();
        $this->assertEqualsWithDelta(150.5, $alpha->listing_price, 0.001);
        // An empty cell leaves the value alone.
        $this->assertSame('Bigshare Services', $alpha->registrar);
    }

    public function test_csv_import_requires_a_slug_column(): void
    {
        $this->actingAs($this->admin)->from(route('admin.ipos.bulk'))
            ->post(route('admin.ipos.bulk.import'), ['file' => UploadedFile::fake()->createWithContent('ipos.csv', "name,lot_size\nAlpha,10\n")])
            ->assertSessionHasErrors('file');
    }
}
