{{-- Live IPO card inside a blog post: the figures are read from the database on every view. --}}
@php use App\Models\Ipo; @endphp
<aside class="blog-ipo-card">
    <div class="blog-ipo-head">
        <x-logo-tile :ipo="$ipo" />
        <div>
            <a href="{{ $ipo->url() }}" class="blog-ipo-name">{{ $ipo->name }} IPO</a>
            <div class="co-meta"><span class="badge b-{{ $ipo->type }}">{{ $ipo->typeLabel() }}</span> <x-status-badge :ipo="$ipo" /></div>
        </div>
    </div>
    <dl class="blog-ipo-facts">
        <div><dt>Price band</dt><dd>{{ $ipo->priceBand() }}</dd></div>
        <div><dt>GMP</dt><dd class="{{ $ipo->hasGmp() ? ($ipo->gmp > 0 ? 'up' : ($ipo->gmp < 0 ? 'down' : '')) : '' }}">{{ $ipo->hasGmp() ? ($ipo->gmp >= 0 ? '₹' : '-₹').Ipo::num(abs($ipo->gmp)).($ipo->gmpPercent() !== null ? ' ('.number_format($ipo->gmpPercent(), 1).'%)' : '') : '—' }}</dd></div>
        <div><dt>Dates</dt><dd>{{ $ipo->open_date ? $ipo->open_date->format('j M').' – '.($ipo->close_date ?? $ipo->open_date)->format('j M') : 'Awaited' }}</dd></div>
        <div><dt>Listing</dt><dd>{{ $ipo->listing_date?->format('j M Y') ?? 'TBA' }}</dd></div>
    </dl>
    <a class="blog-ipo-link" href="{{ $ipo->url() }}">Live GMP, subscription &amp; allotment <x-icon name="arrow-right" :size="14" /></a>
</aside>
