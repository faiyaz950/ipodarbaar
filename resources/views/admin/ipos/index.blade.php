@extends('layouts.admin')

@section('title', 'IPOs')

@section('content')
<div class="admin-head">
    <div>
        <h1>IPOs</h1>
        <p class="muted">Edit any IPO. Changed API fields are locked so the sync won’t overwrite them.</p>
    </div>
    <a class="btn btn-gold btn-sm" href="{{ route('admin.ipos.bulk') }}"><x-icon name="layers" :size="14" /> Bulk edit / CSV</a>
</div>

<div class="card">
    <div class="filterbar">
        <div class="seg">
            @foreach (['active' => 'Not listed', 'listed' => 'Listed', 'edited' => 'Edited', 'all' => 'All'] as $key => $label)
                <a href="{{ route('admin.ipos.index', array_filter(['filter' => $key, 'q' => $q])) }}" class="{{ $filter === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="get" action="{{ route('admin.ipos.index') }}" class="input-icon">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <x-icon name="search" :size="16" />
            <input class="input" type="search" name="q" value="{{ $q }}" placeholder="Search by name…" aria-label="Search IPOs">
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>IPO</th><th>Status</th><th>Open – Close</th><th>Listing</th><th class="r">Price</th><th class="r">GMP</th><th>Added</th><th>Edits</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($ipos as $ipo)
                    <tr>
                        <td class="company">
                            <a href="{{ route('admin.ipos.edit', $ipo) }}"><b>{{ $ipo->name }}</b></a>
                            <div><span class="badge b-{{ $ipo->type }}" style="height:19px;font-size:10.5px">{{ $ipo->typeLabel() }}</span></div>
                        </td>
                        <td><span class="badge b-{{ $ipo->status() }}">{{ $ipo->statusLabel() }}</span></td>
                        <td>{{ $ipo->open_date?->format('j M') ?? '—' }} – {{ $ipo->close_date?->format('j M Y') ?? '—' }}</td>
                        <td>{{ $ipo->listing_date?->format('j M Y') ?? '—' }}</td>
                        <td class="r">{{ $ipo->priceBand() }}</td>
                        <td class="r">{{ $ipo->hasGmp() ? '₹'.\App\Models\Ipo::num($ipo->gmp) : '—' }}</td>
                        <td><span title="{{ $ipo->source_created_at?->format('j M Y, g:i A') }}">{{ $ipo->source_created_at?->diffForHumans() ?? '—' }}</span></td>
                        <td>
                            @if ($ipo->locked_fields)<span class="badge b-gold" title="{{ implode(', ', $ipo->locked_fields) }}">{{ count($ipo->locked_fields) }} locked</span>@endif
                            @if ($ipo->registrar)<span class="badge b-plain">RTA</span>@endif
                            @if ($ipo->hasSubscription())<span class="badge b-plain">Subs</span>@endif
                        </td>
                        <td class="r"><a class="btn btn-outline btn-sm" href="{{ route('admin.ipos.edit', $ipo) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="table-empty">No IPOs match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($ipos->hasPages())
        <div class="table-foot">{{ $ipos->links() }}</div>
    @endif
</div>
@endsection
