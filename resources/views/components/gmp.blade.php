@props(['ipo'])
@if ($ipo->hasGmp())
    @php $cls = $ipo->gmp > 0 ? 'up' : ($ipo->gmp < 0 ? 'down' : 'flat'); @endphp
    <span class="gmp {{ $cls }}">
        <span>{{ $ipo->gmp > 0 ? '+' : '' }}₹{{ \App\Models\Ipo::num($ipo->gmp) }}</span>
        @if ($ipo->gmpPercent() !== null)
            <small class="{{ $cls }}">{{ $ipo->gmp > 0 ? '▲' : ($ipo->gmp < 0 ? '▼' : '') }} {{ number_format(abs($ipo->gmpPercent()), 1) }}%</small>
        @endif
    </span>
@else
    <span class="muted">—</span>
@endif
