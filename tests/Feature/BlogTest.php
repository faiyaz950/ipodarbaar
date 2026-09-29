<?php

namespace Tests\Feature;

use App\Models\BlogAuthor;
use App\Models\BlogPost;
use App\Models\Ipo;
use App\Models\User;
use App\Support\BlogContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private BlogAuthor $desk;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Kolkata'));
        // A fresh sync timestamp keeps the RefreshIpoData middleware from calling the API.
        cache()->forever('ipo:synced_at', now()->toIso8601String());

        // The migration creates the Research Desk byline.
        $this->desk = BlogAuthor::query()->where('slug', 'ipo-darbaar-research-desk')->firstOrFail();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makePost(array $attributes = []): BlogPost
    {
        return BlogPost::factory()->for($this->desk, 'author')->create($attributes);
    }

    public function test_blog_home_lists_live_posts_only(): void
    {
        $live = $this->makePost(['title' => 'SME IPOs in 2026 by the numbers']);
        $draft = BlogPost::factory()->draft()->for($this->desk, 'author')->create(['title' => 'Unfinished draft post']);
        $scheduled = BlogPost::factory()->scheduled()->for($this->desk, 'author')->create(['title' => 'Post for next week']);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('IPO Darbaar Blog')
            ->assertSee($live->title)
            ->assertDontSee($draft->title)
            ->assertDontSee($scheduled->title)
            ->assertSee('"@type":"Blog"', false)
            ->assertDontSee('noindex');
    }

    public function test_empty_blog_is_noindex(): void
    {
        $this->get('/blog')->assertOk()->assertSee('New posts are on the way')->assertSee('noindex, follow', false);
    }

    public function test_category_page_lists_that_category(): void
    {
        $review = $this->makePost(['category' => 'ipo-reviews', 'title' => 'Alpha Tech IPO review']);
        $trend = $this->makePost(['category' => 'trends', 'title' => 'Funds raised this year']);

        $this->get('/blog/ipo-reviews')
            ->assertOk()
            ->assertSee('<h1>IPO Reviews</h1>', false)
            ->assertSee($review->title)
            ->assertDontSee($trend->title);
    }

    public function test_post_page_shows_content_schema_and_toc(): void
    {
        $post = $this->makePost([
            'title' => 'How SME IPOs performed this year',
            'body' => '<h2>Funds raised</h2><p>Text</p><h2>Listing gains</h2><p>More</p><h2>What to watch</h2><p>End</p>',
            'takeaways' => "SMEs raised more\nListings were mixed",
        ]);

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('<h1>How SME IPOs performed this year</h1>', false)
            ->assertSee('Key takeaways')
            ->assertSee('SMEs raised more')
            ->assertSee('<h2 id="funds-raised">Funds raised</h2>', false)
            ->assertSee('href="#listing-gains"', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"name":"IPO Darbaar Research Desk"', false)
            ->assertSee('og:type" content="article"', false)
            ->assertSee(route('blog.author', $this->desk->slug), false);
    }

    public function test_drafts_and_scheduled_posts_are_hidden_from_visitors_but_previewable_by_admins(): void
    {
        $draft = BlogPost::factory()->draft()->for($this->desk, 'author')->create();
        $scheduled = BlogPost::factory()->scheduled()->for($this->desk, 'author')->create();

        $this->get('/blog/'.$draft->slug)->assertNotFound();
        $this->get('/blog/'.$scheduled->slug)->assertNotFound();

        $admin = $this->admin();
        $this->actingAs($admin)->get('/blog/'.$draft->slug)
            ->assertOk()->assertSee('Preview: this post is draft')->assertSee('noindex, nofollow', false);
        $this->actingAs($admin)->get('/blog/'.$scheduled->slug)
            ->assertOk()->assertSee('Preview: this post is scheduled');
    }

    public function test_scheduled_post_goes_live_at_its_time(): void
    {
        $post = BlogPost::factory()->for($this->desk, 'author')->create(['published_at' => now()->addHour()]);
        $this->get('/blog/'.$post->slug)->assertNotFound();

        Carbon::setTestNow(now()->addHours(2));
        $this->get('/blog/'.$post->slug)->assertOk();
    }

    public function test_tagged_ipos_get_live_cards_and_shortcodes_render(): void
    {
        $alpha = Ipo::factory()->create(['name' => 'Alpha Tech', 'slug' => 'alpha-tech-ipo', 'gmp' => 18, 'price' => 120]);
        $beta = Ipo::factory()->create(['name' => 'Beta Foods', 'slug' => 'beta-foods-ipo']);
        $post = $this->makePost(['body' => '<p>Alpha Tech opens this week.</p><p>[[ipo:alpha-tech-ipo]]</p><p>[[ipo:missing-ipo]]</p>']);
        $post->ipos()->sync([$alpha->id, $beta->id]);

        $html = $this->get('/blog/'.$post->slug)->assertOk()->getContent();

        // Alpha's card comes from the shortcode, Beta's from the "IPOs in this post" list.
        $this->assertSame(2, substr_count($html, 'class="blog-ipo-card"'));
        $this->assertStringContainsString('₹18 (15.0%)', $html);
        $this->assertStringContainsString('IPOs in this post', $html);
        $this->assertStringContainsString('Beta Foods IPO', $html);
        $this->assertStringNotContainsString('[[ipo:', $html);
        // The first mention links to the IPO page.
        $this->assertStringContainsString('href="'.$alpha->url().'"', $html);
    }

    public function test_card_code_is_not_swallowed_by_the_ipo_linker(): void
    {
        $ipo = Ipo::factory()->create(['name' => 'Orient Cables India', 'slug' => 'orient-cables-india-ipo']);
        $post = $this->makePost(['body' => '<p><a href="/ipo/orient-cables-india-ipo">Orient Cables India</a> closes today.</p><p>[[ipo:orient-cables-india-ipo]]</p>']);
        $post->ipos()->attach($ipo);

        $html = $this->get('/blog/'.$post->slug)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="blog-ipo-card"'));
        $this->assertStringNotContainsString('[[ipo:', $html);
        $this->assertStringNotContainsString('IPOs in this post', $html);
    }

    public function test_ipo_page_lists_posts_tagged_with_it(): void
    {
        $ipo = Ipo::factory()->create(['name' => 'Alpha Tech', 'slug' => 'alpha-tech-ipo']);
        $post = $this->makePost(['title' => 'Alpha Tech IPO review: should you look closer']);
        $post->ipos()->attach($ipo);
        $draft = BlogPost::factory()->draft()->for($this->desk, 'author')->create(['title' => 'Draft about Alpha Tech']);
        $draft->ipos()->attach($ipo);

        $this->get($ipo->url())
            ->assertOk()
            ->assertSee('Alpha Tech IPO on our blog')
            ->assertSee($post->title)
            ->assertDontSee($draft->title);
    }

    public function test_sanitizer_keeps_formatting_and_marks_external_links(): void
    {
        config(['app.url' => 'https://ipodarbaar.in']);

        $html = BlogContent::sanitize(
            '<h2 style="color:red" onclick="x()">Heading</h2><script>alert(1)</script><p>See <a href="https://www.sebi.gov.in/x">SEBI</a>, '
            .'<a href="/ipo-gmp">GMP</a>, <a href="https://ipodarbaar.in/ipo-guide">guide</a> and <a href="javascript:alert(1)">bad</a>.</p><img src=x onerror=alert(1)><p></p>'
        );

        $this->assertSame(
            '<h2>Heading</h2><p>See <a href="https://www.sebi.gov.in/x" target="_blank" rel="noopener nofollow">SEBI</a>, '
            .'<a href="/ipo-gmp">GMP</a>, <a href="https://ipodarbaar.in/ipo-guide">guide</a> and <a>bad</a>.</p>',
            $html
        );
    }

    public function test_author_page_and_rss_feed(): void
    {
        $post = $this->makePost(['title' => 'Weekly IPO wrap for the week']);
        BlogPost::factory()->draft()->for($this->desk, 'author')->create(['title' => 'Hidden draft']);

        $this->get('/blog/author/'.$this->desk->slug)
            ->assertOk()
            ->assertSee('IPO Darbaar Research Desk')
            ->assertSee($post->title)
            ->assertDontSee('Hidden draft')
            ->assertSee('"@type":"ProfilePage"', false);

        $this->get('/blog/feed.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('<title>Weekly IPO wrap for the week</title>', false)
            ->assertDontSee('Hidden draft');
    }

    public function test_sitemap_search_and_home_include_live_posts(): void
    {
        $post = $this->makePost(['title' => 'Mainboard IPO pipeline for October', 'category' => 'trends']);
        $draft = BlogPost::factory()->draft()->for($this->desk, 'author')->create(['title' => 'Mainboard draft thoughts']);

        $this->get('/sitemap.xml')->assertOk()->assertSee(route('sitemaps.show', 'blog'), false);
        $this->get('/sitemaps/blog.xml')
            ->assertOk()
            ->assertSee('<loc>'.route('blog.index').'</loc>', false)
            ->assertSee('<loc>'.route('blog.show', 'trends').'</loc>', false)
            ->assertSee('<loc>'.$post->url().'</loc>', false)
            ->assertDontSee($draft->slug);

        $this->get('/search?q=mainboard+pipeline')->assertOk()->assertSee($post->title)->assertDontSee($draft->title);
        $this->get('/')->assertOk()->assertSee('Latest from the Blog')->assertSee($post->title);
    }

    public function test_admin_can_create_schedule_and_update_a_post(): void
    {
        Storage::fake('uploads');
        $ipo = Ipo::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/blog')->assertOk()->assertSee('No posts yet');
        $this->actingAs($admin)->get('/admin/blog/create')->assertOk()->assertSee('+ IPO card');

        $response = $this->actingAs($admin)->post('/admin/blog', [
            'title' => 'IPO Reviews: A first look',
            'category' => 'explainers',
            'blog_author_id' => $this->desk->id,
            'excerpt' => 'Short summary.',
            'takeaways' => "One\nTwo",
            'body' => '<h2>Part one</h2><p>Hello <a href="https://example.com">there</a></p><script>bad()</script>',
            'status' => 'draft',
            'ipos' => [$ipo->id],
            'image' => UploadedFile::fake()->image('cover.jpg', 1200, 675),
        ]);

        $post = BlogPost::query()->sole();
        $response->assertRedirect(route('admin.blog.edit', $post));
        $this->assertSame('ipo-reviews-a-first-look', $post->slug);
        $this->assertSame('draft', $post->status);
        $this->assertNull($post->published_at);
        $this->assertStringNotContainsString('script', $post->body);
        $this->assertStringContainsString('rel="noopener nofollow"', $post->body);
        $this->assertSame([$ipo->id], $post->ipos->modelKeys());
        $this->assertNotNull($post->image_path);
        Storage::disk('uploads')->assertExists($post->image_path);

        $this->actingAs($admin)->get('/admin/blog/'.$post->id.'/edit')
            ->assertOk()
            ->assertSee('SEO checklist')
            ->assertSee('aim for at least 600');

        // Publishing without a time goes live now; a future time schedules it.
        $this->actingAs($admin)->put('/admin/blog/'.$post->id, [
            'title' => 'IPO Reviews: A first look', 'category' => 'explainers', 'blog_author_id' => $this->desk->id,
            'body' => '<p>Updated</p>', 'status' => 'published', 'published_at' => '2026-10-02T09:30',
        ])->assertRedirect()->assertSessionHas('status', fn (string $s): bool => str_contains($s, 'goes live on 2 Oct 2026'));
        $post->refresh();
        $this->assertSame('Scheduled', $post->statusLabel());
        $this->assertSame([], $post->ipos->modelKeys());

        $this->actingAs($admin)->put('/admin/blog/'.$post->id, [
            'title' => 'IPO Reviews: A first look', 'category' => 'explainers', 'blog_author_id' => $this->desk->id,
            'body' => '<p>Updated</p>', 'status' => 'published', 'published_at' => '', 'remove_image' => '1',
        ])->assertRedirect();
        $post->refresh();
        $this->assertTrue($post->isLive());
        $this->assertNull($post->image_path);
    }

    public function test_admin_validation_rejects_reserved_slugs_and_bad_categories(): void
    {
        $admin = $this->admin();
        $existing = $this->makePost(['slug' => 'taken-slug']);

        $this->actingAs($admin)->post('/admin/blog', [
            'title' => 'Anything', 'slug' => 'weekly-wrap', 'category' => 'nope', 'blog_author_id' => $this->desk->id,
            'body' => '<p>x</p>', 'status' => 'published',
        ])->assertSessionHasErrors(['slug', 'category']);

        $this->actingAs($admin)->post('/admin/blog', [
            'title' => 'Anything', 'slug' => $existing->slug, 'category' => 'trends', 'blog_author_id' => $this->desk->id,
            'body' => '<p>x</p>', 'status' => 'published',
        ])->assertSessionHasErrors('slug');

        // A title that matches a section name still gets a usable address.
        $this->assertSame('weekly-wrap-post', BlogPost::uniqueSlug('Weekly Wrap'));
        $this->assertSame(1, BlogPost::query()->count());
    }

    public function test_admin_can_delete_posts_and_manage_authors(): void
    {
        $admin = $this->admin();
        $post = $this->makePost();

        $this->actingAs($admin)->delete('/admin/blog/'.$post->id)->assertRedirect('/admin/blog');
        $this->assertModelMissing($post);

        $this->actingAs($admin)->get('/admin/blog/authors')->assertOk()->assertSee('IPO Darbaar Research Desk');
        $this->actingAs($admin)->post('/admin/blog/authors', ['name' => 'Guest Analyst', 'role' => 'Contributor'])->assertRedirect();
        $this->assertDatabaseHas('blog_authors', ['name' => 'Guest Analyst', 'slug' => 'guest-analyst']);

        $this->actingAs($admin)->put('/admin/blog/authors/'.$this->desk->id, ['name' => 'IPO Darbaar Research Desk', 'role' => 'Editors', 'bio' => 'New bio'])
            ->assertRedirect();
        $this->assertSame('New bio', $this->desk->fresh()->bio);
    }

    public function test_non_admins_cannot_reach_the_blog_admin(): void
    {
        $this->get('/admin/blog')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/blog/create')->assertForbidden();
    }
}
