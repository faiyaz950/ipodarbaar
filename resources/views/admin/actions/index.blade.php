@extends('layouts.admin')

@section('title', 'Buybacks, rights & NCDs')

@section('content')
<div class="admin-head">
    <div>
        <h1>Buybacks, rights issues &amp; NCDs</h1>
        <p class="muted">Offers you add here appear on the public Buyback, Rights Issue and NCD pages.</p>
    </div>
    <div class="btn-row">
        @foreach (\App\Models\CorporateAction::TYPES as $key => $meta)
            <a class="btn btn-gold btn-sm" href="{{ route('admin.actions.create', ['type' => $key]) }}"><x-icon name="plus" :size="14" /> {{ $meta['label'] }}</a>
        @endforeach
    </div>
</div>

<div class="card">
    <div class="filterbar">
        <div class="seg">
            <a href="{{ route('admin.actions.index') }}" class="{{ $type === null ? 'active' : '' }}">All</a>
            @foreach (\App\Models\CorporateAction::TYPES as $key => $meta)
                <a href="{{ route('admin.actions.index', ['type' => $key]) }}" class="{{ $type === $key ? 'active' : '' }}">{{ $meta['plural'] }}</a>
            @endforeach
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Company</th><th>Type</th><th>Status</th><th>Dates</th><th>Published</th><th></th></tr></thead>
            <tbody>
                @forelse ($actions as $action)
                    <tr>
                        <td><a href="{{ route('admin.actions.edit', $action) }}"><b>{{ $action->company }}</b></a></td>
                        <td>{{ $action->typeLabel() }}</td>
                        <td>{{ $action->statusLabel() }}</td>
                        <td>{{ $action->open_date?->format('j M') ?? '—' }} – {{ $action->close_date?->format('j M Y') ?? '—' }}</td>
                        <td>{{ $action->is_published ? 'Yes' : 'Hidden' }}</td>
                        <td class="r">
                            <a class="btn btn-outline btn-sm" href="{{ route('admin.actions.edit', $action) }}">Edit</a>
                            @if ($action->is_published)<a class="btn btn-outline btn-sm" href="{{ $action->url() }}" target="_blank" rel="noopener">View</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="table-empty">Nothing added yet. Use the buttons above to add a buyback, rights issue or NCD issue.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($actions->hasPages())
        <div class="table-foot">{{ $actions->links() }}</div>
    @endif
</div>
@endsection
