@props(['ipo'])
<span {{ $attributes->merge(['class' => 'badge dot b-'.$ipo->status()]) }}>{{ $ipo->statusLabel() }}</span>
