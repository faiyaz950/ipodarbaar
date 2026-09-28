<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectToCanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://ipodarbaar.in',
            'ipodarbar.redirect_hosts' => ['www.ipodarbaar.in', 'ipodarbaar.old.test'],
        ]);
    }

    public function test_old_hosts_redirect_permanently_to_the_same_path_on_the_canonical_domain(): void
    {
        $this->get('https://ipodarbaar.old.test/ipos?status=open')
            ->assertStatus(301)
            ->assertRedirect('https://ipodarbaar.in/ipos?status=open');

        $this->get('http://www.ipodarbaar.in/')->assertRedirect('https://ipodarbaar.in/');
    }

    public function test_the_canonical_host_is_served_normally(): void
    {
        $this->get('https://ipodarbaar.in/')->assertOk();
    }

    public function test_posts_on_an_old_host_are_not_redirected(): void
    {
        $this->post('https://ipodarbaar.old.test/internal/ipo-push')->assertNotFound();
    }
}
