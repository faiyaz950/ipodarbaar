<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogAuthor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin editing of blog bylines and their profile text.
 */
class BlogAuthorController extends Controller
{
    public function index(): View
    {
        return view('admin.blog.authors', [
            'authors' => BlogAuthor::query()->withCount('posts')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $base = Str::slug($data['name']) ?: 'author';
        $slug = $base;
        for ($i = 2; BlogAuthor::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        BlogAuthor::create([...$data, 'slug' => $slug]);

        return redirect()->route('admin.blog.authors')->with('status', 'Author added.');
    }

    public function update(Request $request, BlogAuthor $author): RedirectResponse
    {
        $author->update($this->validated($request, $author));

        return redirect()->route('admin.blog.authors')->with('status', 'Author saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BlogAuthor $author = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('blog_authors', 'name')->ignore($author?->id)],
            'role' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
