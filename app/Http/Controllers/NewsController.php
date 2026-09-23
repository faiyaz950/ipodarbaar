<?php

namespace App\Http\Controllers;

use App\Services\NewsService;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(private NewsService $news) {}

    public function index(Request $request)
    {
        $category = $this->news->categoryBySlug($request->query('category'));
        $page = max(1, (int) $request->query('page', 1));

        $paginator = $this->news->paginate(18, $page, $category['id'] ?? null, route('news.index'), array_filter([
            'category' => $category['slug'] ?? null,
        ]));

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

        return view('news.show', ['item' => $item, 'related' => $related]);
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
