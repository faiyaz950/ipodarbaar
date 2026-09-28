@props(['ipos', 'status' => null, 'empty' => 'No IPOs to show right now.'])
<div class="table-wrap">
    <table class="table ipo-table">
        <thead>
            <tr>
                <th>Company</th>
                <th>{{ $status === 'listed' ? 'Listed On' : 'Subscription' }}</th>
                <th class="r">Price Band</th>
                <th class="r">Issue Size</th>
                <th class="r">GMP · Est. Listing</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ipos as $ipo)
                @php
                    $countdown = $ipo->status() !== 'listed' ? $ipo->countdown() : null;
                    $cls = ! $ipo->hasGmp() ? 'flat' : ($ipo->gmp > 0 ? 'up' : ($ipo->gmp < 0 ? 'down' : 'flat'));
                @endphp
                <tr data-type="{{ $ipo->type }}">
                    <td class="company">
                        <div class="co">
                            <x-logo-tile :ipo="$ipo" />
                            <div style="min-width:0">
                                <span class="co-title"><a href="{{ $ipo->url() }}" class="co-name">{{ $ipo->name }}</a> <x-watch-button :ipo="$ipo" /></span>
                                <div class="co-meta">
                                    <span class="badge b-{{ $ipo->type }}">{{ $ipo->typeLabel() }}</span>
                                    <x-status-badge :ipo="$ipo" />
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="dates" data-label="{{ $status === 'listed' || $ipo->status() === 'listed' ? 'Listed on' : 'Subscription' }}">
                        @if ($status === 'listed' || $ipo->status() === 'listed')
                            {{ $ipo->listing_date?->format('j M Y') ?? '—' }}
                            <small>{{ $ipo->open_date ? 'Opened '.$ipo->open_date->format('j M') : '' }}</small>
                        @elseif ($ipo->open_date)
                            {{ $ipo->open_date->format('j M') }} – {{ ($ipo->close_date ?? $ipo->open_date)->format('j M') }}
                            @if ($countdown)
                                <small class="countdown {{ str_contains($countdown, 'today') ? 'hot' : '' }}">{{ $countdown }}</small>
                            @else
                                <small>Lists {{ $ipo->listing_date?->format('j M') ?? 'TBA' }}</small>
                            @endif
                            @if ($ipo->subscription_total !== null)
                                <small>Subscribed {{ number_format($ipo->subscription_total, 2) }}x</small>
                            @endif
                        @else
                            <span class="muted">Dates awaited</span>
                        @endif
                    </td>
                    <td class="r" data-label="Price band">
                        {{ $ipo->priceBand() }}
                        @if ($ipo->lot_size)
                            <small class="muted cell-sub">Lot {{ number_format($ipo->lot_size) }}@if ($minimum = $ipo->lotTable()[0]['amount'] ?? null) · min ₹{{ \App\Models\Ipo::num($minimum) }}@endif</small>
                        @endif
                    </td>
                    <td class="r" data-label="Issue size">{{ $ipo->issue_size ? '₹'.\App\Models\Ipo::num($ipo->issue_size).' Cr' : '—' }}</td>
                    <td class="r" data-label="GMP">
                        @if ($ipo->hasGmp())
                            <span class="gmp {{ $cls }}">
                                <span>{{ $ipo->gmp > 0 ? '+' : '' }}₹{{ \App\Models\Ipo::num($ipo->gmp) }}
                                    @if ($ipo->gmpPercent() !== null)<span class="gmp-pill {{ $cls }}" style="margin-left:4px">{{ $ipo->gmp > 0 ? '▲' : ($ipo->gmp < 0 ? '▼' : '') }} {{ number_format(abs($ipo->gmpPercent()), 1) }}%</span>@endif
                                </span>
                                @if ($ipo->estListingPrice())<small class="muted" style="font-weight:500">Est. ₹{{ \App\Models\Ipo::num($ipo->estListingPrice()) }}</small>@endif
                            </span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="table-empty">
                            <div class="ico"><x-icon name="hourglass" :size="22" /></div>
                            {{ $empty }}
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
