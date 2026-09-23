@extends('layouts.app')

@php use App\Models\Ipo; @endphp

@section('title', 'IPO GMP Today: Live Grey Market Premium')
@section('description', 'Live IPO GMP (grey market premium) today for all open, upcoming and closed mainboard and SME IPOs, with estimated listing price and gain.')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>IPO GMP</span></nav>
        <h1>IPO GMP Today</h1>
        <p class="lead">Live grey market premium for every active IPO, with estimated listing price and expected gain. Updated {{ \App\Services\IpoSyncService::lastSyncedAt()?->diffForHumans() ?? 'regularly' }}.</p>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="grid-3">
            <div class="feature" style="display:flex; gap:14px; align-items:flex-start">
                <span class="ico ico-green" style="margin:0"><x-icon name="trending-up" :size="20" /></span>
                <div><h3>What is GMP?</h3><p>The premium at which IPO shares trade unofficially before listing, a gauge of market sentiment.</p></div>
            </div>
            <div class="feature" style="display:flex; gap:14px; align-items:flex-start">
                <span class="ico ico-blue" style="margin:0"><x-icon name="target" :size="20" /></span>
                <div><h3>Estimated listing</h3><p>Issue price + GMP gives an indicative listing price. GMP ÷ price gives the expected gain %.</p></div>
            </div>
            <div class="feature" style="display:flex; gap:14px; align-items:flex-start">
                <span class="ico ico-amber" style="margin:0"><x-icon name="alert" :size="20" /></span>
                <div><h3>Use with caution</h3><p>GMP is unregulated and volatile. Always read the RHP and consider fundamentals before applying.</p></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><span class="ico"><x-icon name="activity" :size="16" /></span> Live IPO GMP</div>
                    <div class="card-sub">{{ $active->count() }} active IPOs · open, upcoming & awaiting listing</div>
                </div>
                <div class="seg" data-type-filter="gmp-active">
                    <button type="button" class="active" data-value="all">All</button>
                    <button type="button" data-value="mainboard">Mainboard</button>
                    <button type="button" data-value="sme">SME</button>
                </div>
            </div>
            <div class="table-wrap" id="gmp-active">
                <table class="table">
                    <thead>
                        <tr>
                            <th>IPO</th>
                            <th class="r">Price</th>
                            <th class="r">GMP</th>
                            <th class="r">Est. Listing</th>
                            <th class="r">Est. Gain</th>
                            <th>Open – Close</th>
                            <th>Listing</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($active as $ipo)
                            @php $cls = ! $ipo->hasGmp() ? 'flat' : ($ipo->gmp > 0 ? 'up' : ($ipo->gmp < 0 ? 'down' : 'flat')); @endphp
                            <tr data-type="{{ $ipo->type }}">
                                <td class="company">
                                    <div class="co">
                                        <x-logo-tile :ipo="$ipo" />
                                        <div>
                                            <a href="{{ $ipo->url() }}" class="co-name">{{ $ipo->name }}</a>
                                            <div class="co-meta"><span class="badge b-{{ $ipo->type }}">{{ $ipo->typeLabel() }}</span>
                                                @if ($ipo->source_updated_at)<span>Updated {{ $ipo->source_updated_at->diffForHumans(null, true) }} ago</span>@endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="r">{{ $ipo->price ? '₹'.Ipo::num($ipo->price) : '—' }}</td>
                                <td class="r"><b class="{{ $cls }}">{{ $ipo->hasGmp() ? ($ipo->gmp > 0 ? '+' : '').'₹'.Ipo::num($ipo->gmp) : '—' }}</b></td>
                                <td class="r">{{ $ipo->estListingPrice() ? '₹'.Ipo::num($ipo->estListingPrice()) : '—' }}</td>
                                <td class="r">
                                    @if ($ipo->gmpPercent() !== null)
                                        <span class="gmp-pill {{ $cls }} {{ $cls }}">{{ $ipo->gmp > 0 ? '▲' : ($ipo->gmp < 0 ? '▼' : '') }} {{ number_format(abs($ipo->gmpPercent()), 2) }}%</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td class="dates">{{ $ipo->open_date ? $ipo->open_date->format('j M').' – '.($ipo->close_date ?? $ipo->open_date)->format('j M') : 'Awaited' }}</td>
                                <td>{{ $ipo->listing_date?->format('j M') ?? 'TBA' }}</td>
                                <td><x-status-badge :ipo="$ipo" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><div class="table-empty">No active IPOs right now.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($recent->isNotEmpty())
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><span class="ico"><x-icon name="check-circle" :size="16" /></span> Recently Listed: Last GMP</div>
                    <div class="card-sub">IPOs listed in the last 30 days with their final grey market premium</div>
                </div>
            </div>
            <x-ipo-table :ipos="$recent" status="listed" />
        </div>
        @endif
    </div>
</div>
@endsection
