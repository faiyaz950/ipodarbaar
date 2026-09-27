<?php

namespace App\Services;

use App\Models\NewsOverride;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NewsService
{
    /**
     * Latest news, optionally filtered by category.
     *
     * @return array{items: array<int, array>, total:int, page:int, per_page:int, has_more:bool}
     */
    public function latest(int $perPage = 20, int $page = 1, ?int $categoryId = null, bool $withHidden = false): array
    {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $query = array_filter(['page' => $page, 'per_page' => $perPage, 'category_id' => $categoryId]);

        try {
            $payload = $this->cached('news:list:'.md5(json_encode($query)), fn () => $this->request('', $query));
        } catch (Throwable $e) {
            Log::warning('News API failed: '.$e->getMessage());

            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'has_more' => false];
        }

        $rows = array_values(array_filter($payload['data'] ?? [], fn ($n) => is_array($n) && ! empty($n['id'])));
        $overrides = $this->overridesFor(array_column($rows, 'id'));
        $items = [];
        foreach ($rows as $n) {
            $override = $overrides->get((int) $n['id']);
            if ($override?->is_hidden && ! $withHidden) {
                continue;
            }
            $items[] = $this->normalize($n, $override);
        }

        return [
            'items' => $items,
            'total' => (int) ($payload['total'] ?? 0),
            'page' => (int) ($payload['page'] ?? $page),
            'per_page' => (int) ($payload['per_page'] ?? $perPage),
            'has_more' => (bool) ($payload['has_more'] ?? false),
        ];
    }

    public function paginate(int $perPage, int $page, ?int $categoryId, string $path, array $query = []): LengthAwarePaginator
    {
        $result = $this->latest($perPage, $page, $categoryId);

        return new LengthAwarePaginator($result['items'], $result['total'], $perPage, $page, [
            'path' => $path,
            'query' => $query,
        ]);
    }

    public function find(int $id, bool $withHidden = false): ?array
    {
        $data = $this->original($id);
        if (! $data) {
            return null;
        }

        $override = NewsOverride::firstWhere('news_id', (int) $data['id']);
        if ($override?->is_hidden && ! $withHidden) {
            return null;
        }

        return $this->normalize($data, $override);
    }

    /**
     * The raw API row for a news item, without admin edits.
     *
     * @return array<string, mixed>|null
     */
    public function original(int $id): ?array
    {
        try {
            $payload = $this->cached("news:item:{$id}", fn () => $this->request('/'.$id));
        } catch (Throwable $e) {
            return null;
        }

        $data = $payload['data'] ?? null;

        return is_array($data) && ! empty($data['id']) ? $data : null;
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, NewsOverride>
     */
    protected function overridesFor(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return NewsOverride::whereIn('news_id', array_map('intval', $ids))->get()->keyBy('news_id');
    }

    /** @return array<int, array{id:int, name:string, slug:string, color:string}> */
    public function categories(): array
    {
        return config('ipodarbar.news_categories', []);
    }

    public function categoryBySlug(?string $slug): ?array
    {
        return collect($this->categories())->firstWhere('slug', $slug);
    }

    public function categoryById(?int $id): ?array
    {
        return collect($this->categories())->firstWhere('id', $id);
    }

    /**
     * Stale-while-revalidate cache; failed requests are never cached.
     */
    protected function cached(string $key, callable $callback): array
    {
        return Cache::flexible($key, [
            (int) config('ipodarbar.news_api.fresh_for', 300),
            (int) config('ipodarbar.news_api.stale_for', 3600),
        ], $callback);
    }

    protected function request(string $path, array $query = []): array
    {
        $response = Http::timeout(15)
            ->retry(2, 500, throw: false)
            ->acceptJson()
            ->get(rtrim(config('ipodarbar.news_api.url'), '/').$path, $query);

        $response->throw();
        $json = $response->json();

        if (! is_array($json) || ! ($json['success'] ?? false)) {
            throw new RuntimeException('Unexpected news API response');
        }

        return $json;
    }

    public function normalize(array $n, ?NewsOverride $override = null): array
    {
        if ($override) {
            foreach (NewsOverride::FIELDS as $field) {
                if ($override->{$field} !== null) {
                    $n[$field] = $override->{$field};
                }
            }
            if ($imageUrl = $override->imageUrl()) {
                $n['image'] = $n['image_banner'] = $imageUrl;
            }
        }

        $category = $this->categoryById((int) ($n['category_id'] ?? 0))
            ?? (! empty($n['category']['name']) ? [
                'id' => (int) $n['category']['id'],
                'name' => $n['category']['name'],
                'slug' => $n['category']['slug'] ?? Str::slug($n['category']['name']),
                'color' => '#2F5BEA',
            ] : ['id' => 0, 'name' => 'Markets', 'slug' => 'markets', 'color' => '#2F5BEA']);

        $headline = trim(html_entity_decode((string) ($n['headline'] ?? ''), ENT_QUOTES));
        $headlineHi = trim(html_entity_decode((string) ($n['headline_hinglish'] ?? ''), ENT_QUOTES)) ?: $headline;
        $html = self::sanitize((string) ($n['news_detail'] ?? ''));
        $htmlHi = self::sanitize((string) ($n['news_detail_hinglish'] ?? '')) ?: $html;
        $id = (int) $n['id'];
        $slug = Str::slug($headline) ?: 'news';

        $date = null;
        try {
            $date = Carbon::parse($n['created_at'] ?? $n['date'] ?? null);
        } catch (Throwable) {
            $date = null;
        }

        $words = str_word_count(strip_tags($html));

        return [
            'id' => $id,
            'slug' => $slug,
            'url' => route('news.show', ['id' => $id, 'slug' => $slug]),
            'headline' => $headline,
            'headline_hi' => $headlineHi,
            'html' => $html,
            'html_hi' => $htmlHi,
            'summary' => self::summary($html),
            'summary_hi' => self::summary($htmlHi),
            'points' => self::points($html),
            'points_hi' => self::points($htmlHi),
            'date' => $date,
            'date_label' => $date ? self::dateLabel($date) : '',
            'image' => ($n['image'] ?? null) ?: ($n['image_banner'] ?? null),
            'banner' => ($n['image_banner'] ?? null) ?: ($n['image'] ?? null),
            'category' => $category,
            'read_time' => max(1, (int) ceil($words / 200)),
            'is_hidden' => (bool) $override?->is_hidden,
            'edits' => $override?->changedLabels() ?? [],
        ];
    }

    /** JSON-safe shape for the Shorts feed. */
    public static function forFeed(array $item): array
    {
        return [
            'id' => $item['id'],
            'url' => $item['url'],
            'headline' => $item['headline'],
            'headline_hi' => $item['headline_hi'],
            'summary' => $item['summary'],
            'summary_hi' => $item['summary_hi'],
            'points' => $item['points'],
            'points_hi' => $item['points_hi'],
            'date_label' => $item['date_label'],
            'image' => $item['image'],
            'category' => $item['category'],
        ];
    }

    public static function dateLabel(Carbon $date): string
    {
        if ($date->gt(now()->subDay())) {
            return $date->diffForHumans();
        }

        return $date->format($date->isCurrentYear() ? 'j M' : 'j M Y');
    }

    /** Allow-list HTML from the API: basic formatting only, no attributes except safe links. */
    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html);
        $html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><u><h2><h3><h4><blockquote><a><table><thead><tbody><tr><th><td>');

        $html = preg_replace_callback('/<(\/?)([a-z0-9]+)([^>]*)>/i', function ($m) {
            $tag = strtolower($m[2]);
            if ($m[1] === '/') {
                return "</{$tag}>";
            }
            if ($tag === 'a') {
                if (preg_match('/href\s*=\s*(["\'])(https?:\/\/[^"\']+)\1/i', $m[3], $href)) {
                    return '<a href="'.e($href[2]).'" target="_blank" rel="noopener nofollow">';
                }

                return '<a>';
            }

            return $tag === 'br' ? '<br>' : "<{$tag}>";
        }, $html);

        // Drop spacer paragraphs like <p><br></p> or &nbsp; only.
        $html = preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#i', '', $html);

        return trim($html);
    }

    public static function summary(string $html, int $limit = 300): string
    {
        preg_match_all('#<p>(.*?)</p>#is', $html, $m);
        foreach ($m[1] ?? [] as $p) {
            $text = self::plain($p);
            if (mb_strlen($text) > 40) {
                return Str::limit($text, $limit);
            }
        }

        return Str::limit(self::plain($html), $limit);
    }

    /**
     * Extractive key-point summary: editor-written bullets first, then the lead
     * sentence of each paragraph, then remaining sentences, kept in reading order.
     *
     * @return array<int, string>
     */
    public static function points(string $html, int $max = 5): array
    {
        preg_match_all('#<(p|li|blockquote)>(.*?)</\1>#is', $html, $blocks, PREG_SET_ORDER);
        if ($blocks === [] && trim($html) !== '') {
            $blocks = [['', 'p', $html]];
        }

        /** @var array<int, array{text: string, tier: int, pos: int}> $candidates */
        $candidates = [];
        $seen = [];
        foreach ($blocks as [, $tag, $inner]) {
            $isBullet = strtolower($tag) === 'li';
            $sentences = $isBullet ? [self::plain($inner)] : self::sentences(self::plain($inner));
            foreach ($sentences as $index => $sentence) {
                $sentence = trim(preg_replace('/^[^\p{L}\p{N}₹$"“‘(]+/u', '', $sentence));
                $key = mb_strtolower($sentence);
                $isDateline = (bool) preg_match('/^[\p{L}\/ ,.]+\s[—–-]\s\p{L}+ \d{4}$/u', $sentence);
                if ($sentence === '' || isset($seen[$key]) || str_ends_with($sentence, '?')
                    || (! $isBullet && (mb_strlen($sentence) < 15 || $isDateline))) {
                    continue;
                }
                $seen[$key] = true;
                $candidates[] = [
                    'text' => Str::limit($sentence, 160),
                    'tier' => $isBullet ? 0 : ($index === 0 ? 1 : 2),
                    'pos' => count($candidates),
                ];
            }
        }

        return collect($candidates)
            ->sortBy([['tier', 'asc'], ['pos', 'asc']])
            ->take($max)
            ->sortBy('pos')
            ->pluck('text')
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    protected static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=["“‘(]?[\p{Lu}₹])/u', $text) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    public static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</li>'], [' ', ' ', ' '], $html)), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $text)));
    }
}
