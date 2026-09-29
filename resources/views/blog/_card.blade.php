{{-- A post in a list. $feature makes it the large lead card. --}}
@php $feature = $feature ?? false; @endphp
<a href="{{ $post->url() }}" class="blog-card {{ $feature ? 'is-feature' : '' }}">
    <div class="blog-media cat-{{ $post->category }}">
        @if ($post->imageUrl())
            <img src="{{ $post->imageUrl() }}" alt="{{ $post->image_alt ?: $post->title }}" loading="{{ $feature ? 'eager' : 'lazy' }}" decoding="async">
        @else
            <span class="blog-media-label">{{ $post->categoryLabel() }}</span>
        @endif
    </div>
    <div class="blog-body">
        <span class="blog-cat">{{ $post->categoryLabel() }}</span>
        <h3>{{ $post->title }}</h3>
        @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
        <span class="blog-meta">{{ $post->published_at?->format('j M Y') }} · {{ $post->reading_minutes }} min read</span>
    </div>
</a>
