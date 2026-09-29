@extends('layouts.admin')

@section('title', 'Blog')

@section('content')
<div class="admin-head">
    <div>
        <h1>Blog</h1>
        <p class="muted">Original posts by the Research Desk. Drafts and scheduled posts are visible only to admins.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-outline btn-sm" href="{{ route('admin.blog.authors') }}"><x-icon name="users" :size="14" /> Authors</a>
        <a class="btn btn-outline btn-sm" href="{{ route('blog.index') }}" target="_blank" rel="noopener"><x-icon name="external" :size="14" /> View blog</a>
        <a class="btn btn-gold btn-sm" href="{{ route('admin.blog.create') }}"><x-icon name="plus" :size="14" /> New post</a>
    </div>
</div>

<div class="card">
    <div class="filterbar">
        <div class="seg">
            <a href="{{ route('admin.blog.index') }}" class="{{ $status === null ? 'active' : '' }}">All</a>
            <a href="{{ route('admin.blog.index', ['status' => 'draft']) }}" class="{{ $status === 'draft' ? 'active' : '' }}">Drafts</a>
            <a href="{{ route('admin.blog.index', ['status' => 'scheduled']) }}" class="{{ $status === 'scheduled' ? 'active' : '' }}">Scheduled</a>
            <a href="{{ route('admin.blog.index', ['status' => 'published']) }}" class="{{ $status === 'published' ? 'active' : '' }}">Published</a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Title</th><th>Section</th><th>Status</th><th>Date</th><th>Author</th><th></th></tr></thead>
            <tbody>
                @forelse ($posts as $post)
                    @php $label = $post->statusLabel(); @endphp
                    <tr>
                        <td><a href="{{ route('admin.blog.edit', $post) }}"><b>{{ $post->title }}</b></a></td>
                        <td>{{ $post->categoryLabel() }}</td>
                        <td><span class="badge {{ ['Published' => 'b-open', 'Scheduled' => 'b-upcoming', 'Draft' => 'b-plain'][$label] }}">{{ $label }}</span></td>
                        <td>{{ $post->published_at?->format('j M Y, g:i A') ?? '—' }}</td>
                        <td>{{ $post->author?->name }}</td>
                        <td class="r">
                            <a class="btn btn-outline btn-sm" href="{{ route('admin.blog.edit', $post) }}">Edit</a>
                            <a class="btn btn-outline btn-sm" href="{{ $post->url() }}" target="_blank" rel="noopener">{{ $label === 'Published' ? 'View' : 'Preview' }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="table-empty">No posts yet. Click “New post” to write the first one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($posts->hasPages())
        <div class="table-foot">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
