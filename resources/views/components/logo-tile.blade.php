@props(['ipo', 'size' => null])
<span {{ $attributes->merge(['class' => 'logo-tile'.($size ? ' '.$size : '')]) }}>
    <span class="mono" style="background: linear-gradient(135deg, hsl({{ $ipo->hue() }} 55% 42%), hsl({{ ($ipo->hue() + 40) % 360 }} 60% 30%))">{{ $ipo->initials() }}</span>
    @if ($ipo->logoUrl())
        <img src="{{ $ipo->logoUrl() }}" alt="{{ $ipo->name }} logo" loading="lazy" decoding="async" onerror="this.remove()">
    @endif
</span>
