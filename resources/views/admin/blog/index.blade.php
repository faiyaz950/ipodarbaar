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

<div class="card card-pad" style="margin-bottom:20px">
    <div class="card-title af-section" style="margin-bottom:6px"><x-icon name="refresh" :size="16" /> Automatic posts</div>
    <p class="muted" style="font-size:13.5px;margin-bottom:14px">Written from the IPO data with charts and company logos: an <b>IPO Today</b> update every weekday at 8:50 AM (skipped when nothing is happening) and a <b>weekly IPO calendar</b> every Sunday at 10 AM for the week ahead.</p>
    <form method="post" action="{{ route('admin.blog.automation') }}" class="btn-row" style="align-items:center;gap:18px">
        @csrf
        @method('PUT')
        <label class="af-check"><input type="checkbox" name="daily" value="1" @checked($auto['daily'])> Daily IPO Today post</label>
        <label class="af-check"><input type="checkbox" name="weekly" value="1" @checked($auto['weekly'])> Weekly IPO calendar</label>
        <label class="af-check"><input type="checkbox" name="publish" value="1" @checked($auto['publish'])> Publish automatically (untick to save as drafts for review)</label>
        <button type="submit" class="btn btn-outline btn-sm">Save</button>
    </form>
    <div class="btn-row" style="margin-top:14px">
        @foreach (\App\Services\Blog\AutoBlogPublisher::KINDS as $kind => $label)
            <form method="post" action="{{ route('admin.blog.automation.run') }}" onsubmit="this.querySelector('button').disabled=true">
                @csrf
                <input type="hidden" name="kind" value="{{ $kind }}">
                <button type="submit" class="btn btn-gold btn-sm"><x-icon name="zap" :size="14" /> Write the {{ strtolower($label) }} now</button>
            </form>
        @endforeach
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
