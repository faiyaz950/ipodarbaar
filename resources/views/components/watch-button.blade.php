@props(['ipo', 'label' => false])
<button type="button" {{ $attributes->merge(['class' => 'watch-btn'.($label ? ' watch-btn-label' : '')]) }}
        data-watch="{{ $ipo->slug }}" aria-pressed="false" title="Add {{ $ipo->name }} to watchlist" aria-label="Add {{ $ipo->name }} to watchlist">
    <x-icon name="star" :size="$label ? 16 : 15" />
    @if ($label)<span data-watch-label>Watch</span>@endif
</button>
