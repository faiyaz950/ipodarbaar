<?php

namespace App\Http\Controllers;

use App\Models\BlogAuthor;
use App\Models\BlogPost;
use App\Support\BlogContent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The public blog: latest posts, category lists, posts, author pages and an RSS feed.
 */
class BlogController extends Controller
{
    private const PER_PAGE = 12;

    public function index(): View
    {
        return view('blog.index', [
            'posts' => BlogPost::query()->live()->with('author')->latest('published_at')->paginate(self::PER_PAGE),
            'category' => null,
            'counts' => $this->categoryCounts(),
        ]);
    }

    /**
     * /blog/{slug}: a category list when the slug is a category key, otherwise a post.
     */
    public function show(Request $request, string $slug): View
    {
        if (array_key_exists($slug, BlogPost::CATEGORIES)) {
            return view('blog.index', [
                'posts' => BlogPost::query()->live()->where('category', $slug)->with('author')->latest('published_at')->paginate(self::PER_PAGE),
                'category' => $slug,
                'counts' => $this->categoryCounts(),
            ]);
        }

        $post = BlogPost::query()->where('slug', $slug)->with(['author', 'ipos'])->firstOrFail();
        // Drafts and scheduled posts can be previewed by admins only.
        abort_unless($post->isLive() || $request->user()?->is_admin, 404);

        $related = BlogPost::query()->live()->whereKeyNot($post->id)
            ->where(fn ($q) => $q->where('category', $post->category)
                ->orWhereHas('ipos', fn ($i) => $i->whereIn('ipos.id', $post->ipos->modelKeys())))
            ->latest('published_at')->limit(3)->get();

        return view('blog.show', ['post' => $post, 'content' => BlogContent::render($post), 'related' => $related]);
    }

    public function author(BlogAuthor $author): View
    {
        return view('blog.author', [
            'author' => $author,
            'posts' => $author->posts()->live()->latest('published_at')->paginate(self::PER_PAGE),
        ]);
    }

    public function feed(): Response
    {
        $posts = BlogPost::query()->live()->with('author')->latest('published_at')->limit(20)->get();

        return response()->view('blog.feed', ['posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /** @return array<string, int> */
    private function categoryCounts(): array
    {
        return BlogPost::query()->live()->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category')->all();
    }
}
