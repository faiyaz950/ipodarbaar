<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Services\NewsService;
use App\Support\IpoLinker;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(private NewsService $news) {}

    public function index(Request $request, ?string $categorySlug = null)
    {
        $page = max(1, (int) $request->query('page', 1));

        // Category pages moved from /news?category=ipo to /ipo-news.
        if ($categorySlug === null && ($legacy = $this->news->categoryBySlug($request->query('category')))) {
            return redirect()->route('news.category', ['categorySlug' => $legacy['slug']] + ($page > 1 ? ['page' => $page] : []), 301);
        }

        $category = $categorySlug !== null ? $this->news->categoryBySlug($categorySlug) : null;
        abort_if($categorySlug !== null && ! $category, 404);

        $path = $category ? route('news.category', $category['slug']) : route('news.index');
        $paginator = $this->news->paginate(18, $page, $category['id'] ?? null, $path);

        return view('news.index', [
            'items' => $paginator,
            'category' => $category,
            'categories' => $this->news->categories(),
        ]);
    }

    public function show(int $id, ?string $slug = null)
    {
        $item = $this->news->find($id);
        abort_if(! $item, 404);

        if ($slug !== $item['slug']) {
            return redirect($item['url'], 301);
        }

        $related = collect($this->news->latest(7, 1, $item['category']['id'] ?: null)['items'])
            ->reject(fn ($n) => $n['id'] === $item['id'])
            ->take(5)
            ->values();

        // Link IPO names in the story to their IPO pages (internal links help readers and search engines).
        $candidates = Ipo::query()
            ->where(fn ($q) => $q->active()->orWhere(fn ($l) => $l->listed()->whereDate('listing_date', '>=', now()->subDays(90)->toDateString())))
            ->get(['id', 'name', 'slug', 'type', 'open_date', 'close_date', 'listing_date', 'price', 'price_min', 'gmp', 'source_created_at', 'image_url']);
        $linked = IpoLinker::link($item['html'], $candidates);
        $item['html'] = $linked['html'];

        return view('news.show', ['item' => $item, 'related' => $related, 'mentionedIpos' => $linked['ipos']]);
    }

    public function shorts(Request $request)
    {
        $category = $this->news->categoryBySlug($request->query('category'));
        $result = $this->news->latest(12, 1, $category['id'] ?? null);

        return view('news.shorts', [
            'items' => $result['items'],
            'hasMore' => $result['has_more'],
            'category' => $category,
            'categories' => $this->news->categories(),
            'startId' => (int) $request->query('id', 0),
        ]);
    }

    public function feed(Request $request)
    {
        $category = $this->news->categoryBySlug($request->query('category'));
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->news->latest(12, $page, $category['id'] ?? null);

        return response()->json([
            'items' => array_map([NewsService::class, 'forFeed'], $result['items']),
            'has_more' => $result['has_more'],
            'page' => $result['page'],
        ]);
    }
}
