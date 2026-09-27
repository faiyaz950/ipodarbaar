<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsOverride;
use App\Services\NewsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function __construct(private NewsService $news) {}

    public function index(Request $request): View
    {
        $filter = $request->query('filter') === 'edited' ? 'edited' : 'latest';
        $page = max(1, (int) $request->query('page', 1));

        if ($filter === 'edited') {
            $items = NewsOverride::latest('updated_at')->paginate(30)->withQueryString();
        } else {
            $category = $this->news->categoryBySlug($request->query('category'));
            $result = $this->news->latest(20, $page, $category['id'] ?? null, withHidden: true);
            $items = new LengthAwarePaginator($result['items'], $result['total'], 20, $page, [
                'path' => route('admin.news.index'),
                'query' => array_filter(['category' => $category['slug'] ?? null]),
            ]);
        }

        return view('admin.news.index', [
            'items' => $items,
            'filter' => $filter,
            'category' => $category ?? null,
            'categories' => $this->news->categories(),
        ]);
    }

    public function edit(int $id): View
    {
        $original = $this->news->original($id);
        abort_if(! $original, 404);

        $override = NewsOverride::firstWhere('news_id', $id) ?? new NewsOverride(['news_id' => $id]);

        return view('admin.news.edit', [
            'item' => $this->news->normalize($original, $override),
            'original' => $this->news->normalize($original),
            'override' => $override,
            'form' => [
                'headline' => $override->headline ?? self::text($original['headline'] ?? ''),
                'headline_hinglish' => $override->headline_hinglish ?? self::text($original['headline_hinglish'] ?? ''),
                'news_detail' => $override->news_detail ?? (string) ($original['news_detail'] ?? ''),
                'news_detail_hinglish' => $override->news_detail_hinglish ?? (string) ($original['news_detail_hinglish'] ?? ''),
                'category_id' => $override->category_id ?? (int) ($original['category_id'] ?? $original['category']['id'] ?? 0),
            ],
            'categories' => $this->news->categories(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $original = $this->news->original($id);
        abort_if(! $original, 404);

        $data = $request->validate([
            'headline' => ['required', 'string', 'max:300'],
            'headline_hinglish' => ['nullable', 'string', 'max:300'],
            'news_detail' => ['required', 'string', 'max:60000'],
            'news_detail_hinglish' => ['nullable', 'string', 'max:60000'],
            'category_id' => ['nullable', 'integer', Rule::in(array_column($this->news->categories(), 'id'))],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_image' => ['boolean'],
            'is_hidden' => ['boolean'],
        ]);

        $override = NewsOverride::firstOrNew(['news_id' => $id]);

        // Only differences from the API are stored, so untouched fields keep following the source.
        $override->headline = self::changed(self::text($data['headline']), self::text($original['headline'] ?? ''));
        $override->headline_hinglish = self::changed(self::text($data['headline_hinglish'] ?? ''), self::text($original['headline_hinglish'] ?? ''));
        $override->news_detail = self::changed(self::html($data['news_detail']), (string) ($original['news_detail'] ?? ''));
        $override->news_detail_hinglish = self::changed(self::html($data['news_detail_hinglish'] ?? ''), (string) ($original['news_detail_hinglish'] ?? ''));

        $originalCategory = (int) ($original['category_id'] ?? $original['category']['id'] ?? 0);
        $category = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $override->category_id = $category && $category !== $originalCategory ? $category : null;
        $override->is_hidden = $request->boolean('is_hidden');

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            $override->deleteImage();
        }
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $override->image_path = $file->storeAs('news', $id.'-'.Str::lower(Str::random(8)).'.'.$file->extension(), NewsOverride::DISK);
        }

        if ($override->isEmpty()) {
            $override->exists && $override->delete();

            return redirect()->route('admin.news.edit', $id)->with('status', 'Saved. This story matches the API again.');
        }

        $override->save();

        return redirect()->route('admin.news.edit', $id)->with('status', $override->is_hidden ? 'Saved. This story is hidden on the site.' : 'Saved. Your edits are live on the site.');
    }

    public function toggleHidden(int $id): RedirectResponse
    {
        $override = NewsOverride::firstOrNew(['news_id' => $id]);
        $override->is_hidden = ! $override->is_hidden;

        if ($override->isEmpty()) {
            $override->exists && $override->delete();
        } else {
            $override->save();
        }

        return back()->with('status', $override->is_hidden ? 'Story hidden from the site.' : 'Story is showing on the site again.');
    }

    public function reset(int $id): RedirectResponse
    {
        $override = NewsOverride::firstWhere('news_id', $id);
        if ($override) {
            $override->deleteImage();
            $override->delete();
        }

        return redirect()->route('admin.news.edit', $id)->with('status', 'All edits removed. The story shows the API version again.');
    }

    /** Null when the admin value matches the API value (whitespace-insensitive) or is blank. */
    private static function changed(string $value, string $original): ?string
    {
        $squash = fn (string $s) => preg_replace('/\s+/u', ' ', trim($s));

        return $value === '' || $squash($value) === $squash($original) ? null : $value;
    }

    private static function text(string $value): string
    {
        return trim(html_entity_decode($value, ENT_QUOTES));
    }

    /** Plain text (no tags) becomes paragraphs split on blank lines. */
    private static function html(string $value): string
    {
        $value = trim(str_replace("\r\n", "\n", $value));
        if ($value === '' || preg_match('/<[a-z][^>]*>/i', $value)) {
            return $value;
        }

        return collect(preg_split('/\n\s*\n/', $value))
            ->map(fn (string $p) => '<p>'.nl2br(e(trim($p)), false).'</p>')
            ->implode('');
    }
}
