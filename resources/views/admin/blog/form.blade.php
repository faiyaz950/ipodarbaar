@extends('layouts.admin')

@php
    use App\Models\BlogPost;
    $label = $post->exists ? $post->statusLabel() : 'Draft';
@endphp

@section('title', $post->exists ? 'Edit post' : 'New post')

@section('content')
<style>
    .rte-long .rte-body { min-height: 420px; max-height: 72vh; font-size: 16px; }
    .rte-long .rte-body h2 { font-size: 22px; }
    .rte-long .rte-body:empty::before { content: 'Start writing. Use H2 for each main section.'; }
    .blog-ipo-list { max-height: 320px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px; padding: 6px 10px; }
    .blog-ipo-list label { display: flex; gap: 8px; align-items: center; padding: 5px 0; font-size: 13.5px; }
    .blog-ipo-list label small { color: var(--muted); margin-left: auto; white-space: nowrap; }
    .blog-count { font-weight: 500; color: var(--muted); margin-left: auto; font-size: 12px; }
    .blog-count.over { color: var(--red); }
    .blog-checklist { margin: 0; padding-left: 1.1em; font-size: 13.5px; color: var(--text-2); display: grid; gap: 6px; }
    .blog-serp { border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; margin-top: 14px; font-family: Arial, sans-serif; }
    .blog-serp .u { font-size: 12.5px; color: #4d5156; }
    .blog-serp .t { font-size: 18px; color: #1a0dab; margin: 2px 0; line-height: 1.3; }
    .blog-serp .d { font-size: 13.5px; color: #4d5156; line-height: 1.5; }
</style>

<div class="admin-head">
    <div>
        <a class="link-gold" href="{{ route('admin.blog.index') }}"><x-icon name="arrow-left" :size="14" /> All posts</a>
        <h1>{{ $post->exists ? $post->title : 'New post' }}</h1>
        <p class="muted">
            <span class="badge {{ ['Published' => 'b-open', 'Scheduled' => 'b-upcoming', 'Draft' => 'b-plain'][$label] }}">{{ $label }}</span>
            @if ($post->exists) {{ $post->reading_minutes }} min read · last saved {{ $post->updated_at->diffForHumans() }} @endif
        </p>
    </div>
    @if ($post->exists)
        <div class="admin-actions">
            <a class="btn btn-outline btn-sm" href="{{ $post->url() }}" target="_blank" rel="noopener"><x-icon name="external" :size="15" /> {{ $label === 'Published' ? 'View post' : 'Preview' }}</a>
        </div>
    @endif
</div>

@if ($errors->any())
    <div class="flash err" role="alert"><x-icon name="alert" :size="17" /> Please fix the highlighted fields.</div>
@endif

<form method="post" action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}" enctype="multipart/form-data" id="blog-form">
    @csrf
    @if ($post->exists) @method('PUT') @endif

    <div class="layout">
        <div class="stack">
            <div class="card card-pad">
                <div class="card-title af-section">Post</div>
                <label class="af">
                    <span class="af-label">Title <span class="blog-count" data-count-for="title" data-limit="65"></span></span>
                    <input class="input" type="text" name="title" value="{{ old('title', $post->title) }}" required maxlength="190" data-count>
                    <span class="af-hint">Put the main search phrase near the start, e.g. “Tata Capital IPO Review: …”.</span>
                    @error('title')<span class="af-error">{{ $message }}</span>@enderror
                </label>
                <div class="af-grid" style="margin-top:16px">
                    <label class="af">
                        <span class="af-label">Section</span>
                        <select class="input" name="category">
                            @foreach (BlogPost::CATEGORIES as $key => $meta)
                                <option value="{{ $key }}" @selected(old('category', $post->category) === $key)>{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                        @error('category')<span class="af-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="af">
                        <span class="af-label">Author</span>
                        <select class="input" name="blog_author_id">
                            @foreach ($authors as $author)
                                <option value="{{ $author->id }}" @selected((int) old('blog_author_id', $post->blog_author_id) === $author->id)>{{ $author->name }}</option>
                            @endforeach
                        </select>
                        @error('blog_author_id')<span class="af-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="af">
                        <span class="af-label">Web address (optional)</span>
                        <input class="input" type="text" name="slug" value="{{ old('slug', $post->slug) }}" maxlength="190" placeholder="made from the title">
                        <span class="af-hint">/blog/<b>{{ old('slug', $post->slug) ?: 'your-post-title' }}</b>. Avoid changing it after publishing.</span>
                        @error('slug')<span class="af-error">{{ $message }}</span>@enderror
                    </label>
                </div>
            </div>

            <div class="card card-pad">
                <div class="card-title af-section">Summary &amp; key takeaways</div>
                <label class="af">
                    <span class="af-label">Summary <span class="blog-count" data-count-for="excerpt" data-limit="160"></span></span>
                    <textarea class="input" name="excerpt" rows="3" maxlength="320" data-count>{{ old('excerpt', $post->excerpt) }}</textarea>
                    <span class="af-hint">One or two sentences shown under the title, on cards and in Google (if no SEO description is set).</span>
                    @error('excerpt')<span class="af-error">{{ $message }}</span>@enderror
                </label>
                <label class="af" style="margin-top:16px">
                    <span class="af-label">Key takeaways (one per line)</span>
                    <textarea class="input" name="takeaways" rows="4" maxlength="2000" placeholder="Issue size is ₹1,200 crore, fully fresh issue&#10;Price band ₹300–₹315; lot of 47 shares">{{ old('takeaways', $post->takeaways) }}</textarea>
                    <span class="af-hint">3–4 short points shown in a box at the top of the post.</span>
                    @error('takeaways')<span class="af-error">{{ $message }}</span>@enderror
                </label>
            </div>

            <div class="card card-pad">
                <div class="card-title af-section">Post text</div>
                <x-rich-editor name="body" label="Text" :value="$post->body" long
                    hint="Use H2 for each main section (they form the table of contents) and H3 inside them. “+ IPO card” adds a live card with the IPO's price band, GMP and dates. Link to IPO pages, guides and calculators on this site." />
            </div>

            <div class="card card-pad">
                <div class="card-title af-section">Featured image</div>
                @if ($post->imageUrl())
                    <img src="{{ $post->imageUrl() }}" alt="" style="max-width:100%;max-height:220px;border-radius:12px;margin-bottom:12px;object-fit:cover">
                @endif
                <div class="af-grid">
                    <label class="af">
                        <span class="af-label">{{ $post->image_path ? 'Replace image' : 'Upload image' }}</span>
                        <input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                        <span class="af-hint">JPG, PNG or WebP up to 5 MB, at least 600px wide. 1200×675 (16:9) works best; it is also the share image.</span>
                        @error('image')<span class="af-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="af">
                        <span class="af-label">Image description (alt text)</span>
                        <input class="input" type="text" name="image_alt" value="{{ old('image_alt', $post->image_alt) }}" maxlength="190" placeholder="What the image shows">
                        @error('image_alt')<span class="af-error">{{ $message }}</span>@enderror
                    </label>
                </div>
                @if ($post->image_path)
                    <label class="af-check" style="margin-top:10px"><input type="checkbox" name="remove_image" value="1"> Remove the image</label>
                @endif
            </div>

            <div class="card card-pad">
                <div class="card-title af-section">Google &amp; sharing (optional)</div>
                <div class="af-grid">
                    <label class="af">
                        <span class="af-label">SEO title <span class="blog-count" data-count-for="seo_title" data-limit="60"></span></span>
                        <input class="input" type="text" name="seo_title" value="{{ old('seo_title', $post->seo_title) }}" maxlength="90" data-count placeholder="Uses the post title if empty">
                        @error('seo_title')<span class="af-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="af">
                        <span class="af-label">SEO description <span class="blog-count" data-count-for="seo_description" data-limit="160"></span></span>
                        <textarea class="input" name="seo_description" rows="2" maxlength="200" data-count placeholder="Uses the summary if empty">{{ old('seo_description', $post->seo_description) }}</textarea>
                        @error('seo_description')<span class="af-error">{{ $message }}</span>@enderror
                    </label>
                </div>
                <div class="blog-serp" aria-hidden="true">
                    <div class="u">ipodarbaar.in › blog › {{ $post->slug ?: '…' }}</div>
                    <div class="t" data-serp-title>{{ $post->metaTitle() ?: 'Post title' }}</div>
                    <div class="d" data-serp-desc>{{ \Illuminate\Support\Str::limit($post->metaDescription(), 160) ?: 'Summary of the post…' }}</div>
                </div>
            </div>
        </div>

        <aside class="sidebar stack">
            <div class="card card-pad">
                <div class="card-title af-section">Publish</div>
                <label class="af">
                    <span class="af-label">Status</span>
                    <select class="input" name="status">
                        @foreach (BlogPost::STATUSES as $key => $name)
                            <option value="{{ $key }}" @selected(old('status', $post->status) === $key)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('status')<span class="af-error">{{ $message }}</span>@enderror
                </label>
                <label class="af" style="margin-top:14px">
                    <span class="af-label">Publish time (IST)</span>
                    <input class="input" type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
                    <span class="af-hint">Leave empty to publish now. A future time schedules the post.</span>
                    @error('published_at')<span class="af-error">{{ $message }}</span>@enderror
                </label>
                <div class="btn-row" style="margin-top:16px">
                    <button type="submit" class="btn btn-gold">Save</button>
                    @if ($post->exists)
                        <a class="btn btn-outline" href="{{ $post->url() }}" target="_blank" rel="noopener">{{ $label === 'Published' ? 'View' : 'Preview' }}</a>
                    @endif
                </div>
            </div>

            @if ($post->exists)
                <div class="card card-pad">
                    <div class="card-title af-section">SEO checklist</div>
                    @if ($warnings === [])
                        <p class="muted" style="margin:0;font-size:13.5px"><x-icon name="check-circle" :size="15" /> Looks good.</p>
                    @else
                        <ul class="blog-checklist">
                            @foreach ($warnings as $warning)<li>{{ $warning }}</li>@endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="card card-pad">
                <div class="card-title af-section">IPOs in this post</div>
                <input class="input" type="search" placeholder="Filter IPOs…" data-ipo-filter style="margin-bottom:10px">
                <div class="blog-ipo-list">
                    @foreach ($ipoOptions as $ipo)
                        <label data-ipo-name="{{ strtolower($ipo->name) }}">
                            <input type="checkbox" name="ipos[]" value="{{ $ipo->id }}" @checked(in_array($ipo->id, $selectedIpos, true))>
                            <span>{{ $ipo->name }}</span>
                            <small>{{ $ipo->type === 'sme' ? 'SME' : 'Main' }}{{ $ipo->open_date ? ' · '.$ipo->open_date->format('M Y') : '' }}</small>
                        </label>
                    @endforeach
                </div>
                <span class="af-hint" style="display:block;margin-top:8px">Tagged IPOs get a live card at the end of the post, are linked on first mention, and the post shows on their IPO pages.</span>
                @error('ipos')<span class="af-error">{{ $message }}</span>@enderror
            </div>
        </aside>
    </div>
</form>

@if ($post->exists)
    <form method="post" action="{{ route('admin.blog.destroy', $post) }}" style="margin-top:24px" onsubmit="return confirm('Delete this post? This cannot be undone.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline btn-sm">Delete post</button>
    </form>
@endif

<script>
(function () {
    function count(input) {
        var badge = document.querySelector('[data-count-for="' + input.name + '"]');
        if (!badge) { return; }
        var len = input.value.length, limit = +badge.getAttribute('data-limit');
        badge.textContent = len + ' / ' + limit;
        badge.classList.toggle('over', len > limit);
    }
    function serp() {
        var f = document.getElementById('blog-form');
        var t = f.seo_title.value.trim() || f.title.value.trim() || 'Post title';
        var d = f.seo_description.value.trim() || f.excerpt.value.trim() || 'Summary of the post…';
        document.querySelector('[data-serp-title]').textContent = t.length > 62 ? t.slice(0, 60) + '…' : t;
        document.querySelector('[data-serp-desc]').textContent = d.length > 160 ? d.slice(0, 158) + '…' : d;
    }
    document.querySelectorAll('[data-count]').forEach(function (el) {
        count(el);
        el.addEventListener('input', function () { count(el); serp(); });
    });
    var filter = document.querySelector('[data-ipo-filter]');
    filter.addEventListener('input', function () {
        var q = filter.value.trim().toLowerCase();
        document.querySelectorAll('[data-ipo-name]').forEach(function (row) {
            row.hidden = q !== '' && row.getAttribute('data-ipo-name').indexOf(q) === -1 && !row.querySelector('input').checked;
        });
    });
})();
</script>
<script src="{{ asset('assets/js/admin-editor.js') }}?v={{ filemtime(public_path('assets/js/admin-editor.js')) }}" defer></script>
@endsection
