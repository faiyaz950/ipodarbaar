{{-- Sponsored broker links managed in Admin → Settings → Monetization. --}}
@php $brokers = array_values(array_filter((array) app(\App\Support\Settings::class)->get('brokers', []), fn ($b) => filled($b['name'] ?? null) && filled($b['url'] ?? null))); @endphp
@if ($brokers)
    <div {{ $attributes->merge(['class' => 'card widget broker-cta']) }}>
        <div class="card-head">
            <div class="card-title"><span class="ico"><x-icon name="briefcase" :size="16" /></span> Apply via a broker</div>
            <span class="badge b-plain">Sponsored</span>
        </div>
        @foreach ($brokers as $broker)
            <a class="list-link" href="{{ $broker['url'] }}" target="_blank" rel="sponsored nofollow noopener" data-track="broker_click" data-track-label="{{ $broker['name'] }}">
                <span style="min-width:0">
                    <span class="t">{{ $broker['name'] }}</span>
                    @if (filled($broker['tagline'] ?? null))<span class="m">{{ $broker['tagline'] }}</span>@endif
                </span>
                <x-icon name="external" :size="15" />
            </a>
        @endforeach
    </div>
@endif
