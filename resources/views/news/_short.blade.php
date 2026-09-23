<article class="short" data-id="{{ $item['id'] }}">
    <div class="short-card">
        <div class="short-media">
            @if ($item['image'])<img src="{{ $item['image'] }}" alt="" loading="{{ $loop->index < 2 ? 'eager' : 'lazy' }}" decoding="async">@endif
            <span class="cat-chip" style="--c: {{ $item['category']['color'] }}"><i></i>{{ $item['category']['name'] }}</span>
        </div>
        <div class="short-body">
            <h2><span data-lang-en>{{ $item['headline'] }}</span><span data-lang-hi>{{ $item['headline_hi'] }}</span></h2>
            <p class="sum"><span data-lang-en>{{ $item['summary'] }}</span><span data-lang-hi>{{ $item['summary_hi'] }}</span></p>
            @if (count($item['points']))
                <ul data-lang-en>@foreach (array_slice($item['points'], 0, 3) as $p)<li>{{ $p }}</li>@endforeach</ul>
            @endif
            @if (count($item['points_hi']))
                <ul data-lang-hi>@foreach (array_slice($item['points_hi'], 0, 3) as $p)<li>{{ $p }}</li>@endforeach</ul>
            @endif
        </div>
        <div class="short-foot">
            <span class="when">{{ $item['date_label'] }} · IPO Darbaar</span>
            <div class="acts">
                <button type="button" class="sq-btn" data-share="native" data-title="{{ $item['headline'] }}" data-url="{{ $item['url'] }}" aria-label="Share"><x-icon name="share" :size="16" /></button>
                <a class="btn btn-navy btn-sm" href="{{ $item['url'] }}">Read full <x-icon name="arrow-right" :size="14" /></a>
            </div>
        </div>
    </div>
</article>
