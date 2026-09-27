@extends('layouts.admin')

@section('title', 'News')

@section('content')
<div class="admin-head">
    <div>
        <h1>News</h1>
        <p class="muted">Stories come from the news API. Edit a headline, text, category or image, or hide a story. Untouched fields keep following the API.</p>
    </div>
</div>

<div class="card">
    <div class="filterbar">
        <div class="seg">
            <a href="{{ route('admin.news.index') }}" class="{{ $filter === 'latest' && ! $category ? 'active' : '' }}">Latest</a>
            @foreach ($categories as $cat)
                <a href="{{ route('admin.news.index', ['category' => $cat['slug']]) }}" class="{{ ($category['id'] ?? null) === $cat['id'] ? 'active' : '' }}">{{ $cat['name'] }}</a>
            @endforeach
            <a href="{{ route('admin.news.index', ['filter' => 'edited']) }}" class="{{ $filter === 'edited' ? 'active' : '' }}">Edited / hidden</a>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            @if ($filter === 'edited')
                <thead><tr><th>Story</th><th>Status</th><th>Edits</th><th>Last edited</th><th></th></tr></thead>
                <tbody>
                    @forelse ($items as $override)
                        <tr>
                            <td class="company"><a href="{{ route('admin.news.edit', $override->news_id) }}"><b>{{ $override->headline ?? 'Story #'.$override->news_id }}</b></a></td>
                            <td><span class="badge {{ $override->is_hidden ? 'b-closed' : 'b-open' }}">{{ $override->is_hidden ? 'Hidden' : 'Showing' }}</span></td>
                            <td>
                                @foreach ($override->changedLabels() as $label)<span class="badge b-gold">{{ $label }}</span> @endforeach
                            </td>
                            <td><span title="{{ $override->updated_at->format('j M Y, g:i A') }}">{{ $override->updated_at->diffForHumans() }}</span></td>
                            <td class="r"><a class="btn btn-outline btn-sm" href="{{ route('admin.news.edit', $override->news_id) }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">No stories edited or hidden yet.</td></tr>
                    @endforelse
                </tbody>
            @else
                <thead><tr><th>Story</th><th>Category</th><th>Published</th><th>Status</th><th>Edits</th><th></th></tr></thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="company">
                                <a href="{{ route('admin.news.edit', $item['id']) }}"><b>{{ $item['headline'] }}</b></a>
                            </td>
                            <td>{{ $item['category']['name'] }}</td>
                            <td><span title="{{ $item['date']?->format('j M Y, g:i A') }}">{{ $item['date_label'] ?: '—' }}</span></td>
                            <td><span class="badge {{ $item['is_hidden'] ? 'b-closed' : 'b-open' }}">{{ $item['is_hidden'] ? 'Hidden' : 'Showing' }}</span></td>
                            <td>
                                @foreach ($item['edits'] as $label)<span class="badge b-gold">{{ $label }}</span> @endforeach
                            </td>
                            <td class="r" style="white-space:nowrap">
                                <form method="post" action="{{ route('admin.news.visibility', $item['id']) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">{{ $item['is_hidden'] ? 'Show' : 'Hide' }}</button>
                                </form>
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.news.edit', $item['id']) }}">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">No stories right now. The news API may be unreachable.</td></tr>
                    @endforelse
                </tbody>
            @endif
        </table>
    </div>
    @if ($items->hasPages())
        <div class="table-foot">{{ $items->links() }}</div>
    @endif
</div>
@endsection
