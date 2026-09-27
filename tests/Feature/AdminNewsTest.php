<?php

namespace Tests\Feature;

use App\Models\NewsOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminNewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
        cache()->forever('ipo:synced_at', now()->toIso8601String());

        Http::fake([
            'courses.finowings.com/api/market-news-list/*' => Http::response(['success' => true, 'data' => $this->newsRow(501)]),
            'courses.finowings.com/api/market-news-list*' => Http::response([
                'success' => true,
                'data' => [$this->newsRow(501), $this->newsRow(502)],
                'total' => 2, 'page' => 1, 'per_page' => 20, 'has_more' => false,
            ]),
        ]);
    }

    public function test_guests_cannot_manage_news(): void
    {
        $this->get('/admin/news')->assertRedirect('/admin/login');
        $this->put('/admin/news/501', ['headline' => 'Hacked'])->assertRedirect('/admin/login');

        $this->assertDatabaseCount('news_overrides', 0);
    }

    public function test_admin_edits_are_shown_on_the_site_and_only_changed_fields_are_stored(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/news')->assertOk()->assertSee('Markets rally on IPO demand 501');
        $this->get('/admin/news/501/edit')->assertOk()->assertSee('Save changes');

        $this->put('/admin/news/501', [
            'headline' => 'SEBI clears three new IPOs',
            'headline_hinglish' => 'IPO demand se market mein tezi 501',
            'news_detail' => "First paragraph written by the editor.\n\nSecond paragraph.",
            'news_detail_hinglish' => '',
            'category_id' => 9,
        ])->assertRedirect('/admin/news/501/edit')->assertSessionHasNoErrors();

        $override = NewsOverride::firstWhere('news_id', 501);
        $this->assertSame('SEBI clears three new IPOs', $override->headline);
        $this->assertNull($override->headline_hinglish);
        $this->assertNull($override->category_id);
        $this->assertSame('<p>First paragraph written by the editor.</p><p>Second paragraph.</p>', $override->news_detail);

        $this->get('/news')->assertOk()->assertSee('SEBI clears three new IPOs')->assertDontSee('Markets rally on IPO demand 501');
        $this->get('/news/501/sebi-clears-three-new-ipos')->assertOk()
            ->assertSee('SEBI clears three new IPOs')
            ->assertSee('First paragraph written by the editor.');
    }

    public function test_hidden_stories_disappear_from_the_site_until_shown_again(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/news/501/visibility')->assertRedirect();

        $this->get('/news')->assertOk()->assertDontSee('Markets rally on IPO demand 501')->assertSee('Markets rally on IPO demand 502');
        $this->get('/news/501/markets-rally-on-ipo-demand-501')->assertNotFound();
        $this->get('/admin/news')->assertOk()->assertSee('Markets rally on IPO demand 501')->assertSee('Hidden');

        $this->post('/admin/news/501/visibility')->assertRedirect();

        $this->assertDatabaseCount('news_overrides', 0);
        $this->get('/news')->assertOk()->assertSee('Markets rally on IPO demand 501');
    }

    public function test_uploaded_image_replaces_the_api_image_and_reset_removes_it(): void
    {
        Storage::fake(NewsOverride::DISK);
        $this->actingAs($this->admin());

        $this->put('/admin/news/501', [
            'headline' => 'Markets rally on IPO demand 501',
            'news_detail' => $this->newsRow(501)['news_detail'],
            'image' => UploadedFile::fake()->image('cover.jpg', 1200, 675),
        ])->assertSessionHasNoErrors();

        $path = NewsOverride::firstWhere('news_id', 501)->image_path;
        Storage::disk(NewsOverride::DISK)->assertExists($path);
        $this->get('/news/501/markets-rally-on-ipo-demand-501')->assertOk()->assertSee('uploads/'.$path);

        $this->delete('/admin/news/501')->assertRedirect('/admin/news/501/edit');

        Storage::disk(NewsOverride::DISK)->assertMissing($path);
        $this->assertDatabaseCount('news_overrides', 0);
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        Storage::fake(NewsOverride::DISK);
        $this->actingAs($this->admin());

        $this->put('/admin/news/501', [
            'headline' => 'Markets rally on IPO demand 501',
            'news_detail' => '<p>Body</p>',
            'image' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('news_overrides', 0);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function newsRow(int $id): array
    {
        return [
            'id' => $id,
            'headline' => "Markets rally on IPO demand {$id}",
            'headline_hinglish' => "IPO demand se market mein tezi {$id}",
            'news_detail' => '<p>Indian markets rallied strongly today as investors lined up for new listings across sectors.</p>',
            'news_detail_hinglish' => '<p>Aaj Indian markets mein zabardast tezi dikhi kyunki investors naye listings ke liye line mein the.</p>',
            'date' => '2026-09-23',
            'image' => 'https://courses.finowings.com/storage/market_news/a.avif',
            'image_banner' => 'https://courses.finowings.com/storage/market_news/a.avif',
            'category_id' => 9,
        ];
    }
}
