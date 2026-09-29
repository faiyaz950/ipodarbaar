<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogAuthor;
use App\Models\BlogPost;
use App\Models\Ipo;
use App\Support\BlogContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Admin CRUD for blog posts: write, schedule, tag IPOs and upload a featured image.
 */
class BlogPostController extends Controller
{
    /** Featured images wider than this are scaled down on upload. */
    private const IMAGE_MAX_WIDTH = 1600;

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['draft', 'scheduled', 'published'], true) ? $request->query('status') : null;

        return view('admin.blog.index', [
            'posts' => BlogPost::query()
                ->with('author')
                ->when($status === 'draft', fn ($q) => $q->where('status', 'draft'))
                ->when($status === 'scheduled', fn ($q) => $q->where('status', 'published')->where('published_at', '>', now()))
                ->when($status === 'published', fn ($q) => $q->live())
                ->orderByRaw("status = 'draft' desc")->latest('published_at')->latest('id')
                ->paginate(30)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.form', $this->formData(new BlogPost([
            'category' => 'trends',
            'status' => 'draft',
            'blog_author_id' => BlogAuthor::query()->value('id'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $post = new BlogPost;
        $this->fill($post, $request);
        $post->save();
        $post->ipos()->sync($request->input('ipos', []));

        return redirect()->route('admin.blog.edit', $post)->with('status', $this->savedMessage($post));
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.blog.form', $this->formData($post->load('ipos')));
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $this->fill($post, $request);
        $post->save();
        $post->ipos()->sync($request->input('ipos', []));

        return redirect()->route('admin.blog.edit', $post)->with('status', $this->savedMessage($post));
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->deleteImage($post->image_path);
        $post->delete();

        return redirect()->route('admin.blog.index')->with('status', '“'.$post->title.'” deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(BlogPost $post): array
    {
        $selected = collect(old('ipos', $post->relationLoaded('ipos') ? $post->ipos->modelKeys() : []))->map(fn ($id): int => (int) $id)->all();

        return [
            'post' => $post,
            'authors' => BlogAuthor::query()->orderBy('name')->get(),
            // Recent and upcoming IPOs first; tagged ones are always included so they stay selected.
            'ipoOptions' => Ipo::query()
                ->where(fn ($q) => $q->whereIn('id', $selected)
                    ->orWhereNull('open_date')
                    ->orWhere('open_date', '>=', now()->subMonths(6)->toDateString()))
                ->orderByRaw('open_date is null desc')->orderByDesc('open_date')
                ->limit(400)->get(['id', 'name', 'slug', 'type', 'open_date']),
            'selectedIpos' => $selected,
            'warnings' => $post->exists ? $post->seoWarnings() : [],
        ];
    }

    private function fill(BlogPost $post, Request $request): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug')->ignore($post->id),
                Rule::notIn([...array_keys(BlogPost::CATEGORIES), 'author', 'feed', 'feed.xml'])],
            'category' => ['required', Rule::in(array_keys(BlogPost::CATEGORIES))],
            'blog_author_id' => ['required', 'integer', Rule::exists('blog_authors', 'id')],
            'excerpt' => ['nullable', 'string', 'max:320'],
            'takeaways' => ['nullable', 'string', 'max:2000'],
            'body' => ['required', 'string', 'max:150000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=600'],
            'image_alt' => ['nullable', 'string', 'max:190'],
            'remove_image' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:90'],
            'seo_description' => ['nullable', 'string', 'max:200'],
            'status' => ['required', Rule::in(array_keys(BlogPost::STATUSES))],
            'published_at' => ['nullable', 'date'],
            'ipos' => ['nullable', 'array', 'max:12'],
            'ipos.*' => ['integer', Rule::exists('ipos', 'id')],
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only.',
            'slug.not_in' => 'This address is used by a blog section; choose another.',
            'image.dimensions' => 'The image should be at least 600 pixels wide.',
        ]);

        $body = BlogContent::sanitize($data['body']);

        $post->fill([
            'title' => $data['title'],
            'category' => $data['category'],
            'blog_author_id' => $data['blog_author_id'],
            'excerpt' => $data['excerpt'] ?? null,
            'takeaways' => $data['takeaways'] ?? null,
            'body' => $body,
            'image_alt' => $data['image_alt'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'status' => $data['status'],
            'reading_minutes' => BlogPost::readingMinutes($body),
        ]);

        $post->slug = filled($data['slug'] ?? null) ? $data['slug'] : ($post->slug ?: BlogPost::uniqueSlug($data['title'], $post->id));

        // Publishing without a time means "now" (a live post keeps its date); a future time schedules the post.
        $post->published_at = filled($data['published_at'] ?? null)
            ? $data['published_at']
            : ($data['status'] === 'published' ? ($post->isLive() ? $post->published_at : now()) : null);

        if ($request->boolean('remove_image') || $request->hasFile('image')) {
            $this->deleteImage($post->image_path);
            $post->image_path = null;
        }
        if ($request->hasFile('image')) {
            $post->image_path = $this->storeImage($request->file('image'), $post->slug);
        }
    }

    /**
     * Saves the featured image under uploads/blog, scaled to at most 1600px wide as WebP when GD allows.
     */
    private function storeImage(UploadedFile $file, string $slug): string
    {
        $name = 'blog/'.Str::limit($slug, 80, '').'-'.now()->format('YmdHis');

        try {
            $source = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($file->getRealPath())) : false;
            if ($source !== false && function_exists('imagewebp')) {
                $width = imagesx($source);
                if ($width > self::IMAGE_MAX_WIDTH) {
                    $scaled = imagescale($source, self::IMAGE_MAX_WIDTH);
                    imagedestroy($source);
                    $source = $scaled;
                }
                ob_start();
                imagewebp($source, null, 82);
                $bytes = (string) ob_get_clean();
                imagedestroy($source);
                if ($bytes !== '') {
                    Storage::disk('uploads')->put($name.'.webp', $bytes);

                    return $name.'.webp';
                }
            }
        } catch (Throwable) {
            // Fall back to storing the upload unchanged.
        }

        return $file->storeAs('blog', basename($name).'.'.$file->extension(), 'uploads');
    }

    private function deleteImage(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'blog/')) {
            Storage::disk('uploads')->delete($path);
        }
    }

    private function savedMessage(BlogPost $post): string
    {
        return match ($post->statusLabel()) {
            'Published' => 'Saved. The post is live at '.$post->url(),
            'Scheduled' => 'Saved. The post goes live on '.$post->published_at->format('j M Y, g:i A').'.',
            default => 'Draft saved. Use Preview to see it; publish when ready.',
        };
    }
}
