@props(['item', 'feature' => false])
<a href="{{ $item['url'] }}" {{ $attributes->merge(['class' => 'news-card'.($feature ? ' feature' : '')]) }}>
    <div class="media">
        @if ($item['image'])
            <img src="{{ $item['image'] }}" alt="{{ $item['headline'] }}" loading="lazy" decoding="async">
        @endif
    </div>
    <div class="body">
        <span class="cat" style="--c: {{ $item['category']['color'] }}">{{ $item['category']['name'] }}</span>
        <h3>{{ $item['headline'] }}</h3>
        <p>{{ $item['summary'] }}</p>
        <div class="meta">
            <span>{{ $item['date_label'] }}</span>
            <span>·</span>
            <span>{{ $item['read_time'] }} min read</span>
        </div>
    </div>
</a>
