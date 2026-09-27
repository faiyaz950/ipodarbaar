<x-mail::message>
# {{ $digest['frequency'] === 'weekly' ? 'Your weekly IPO roundup' : 'Today in IPOs' }}

{{ \Illuminate\Support\Carbon::parse($digest['date'])->format('l, j F Y') }}

@foreach ($digest['sections'] as $section)
## {{ $section['title'] }}

<x-mail::table>
| IPO | Price | GMP |
|:----|:------|----:|
@foreach ($section['items'] as $item)
| [{{ $item['name'] }}]({{ $item['url'] }}) · {{ $item['type'] }}<br><small>{{ $item['note'] }}</small> | {{ $item['price'] }} | @if ($item['listing_gain_pct'] !== null){{ $item['listing_gain_pct'] >= 0 ? '+' : '' }}{{ number_format($item['listing_gain_pct'], 1) }}% listing @elseif ($item['gmp'] !== null){{ $item['gmp'] > 0 ? '+' : '' }}₹{{ \App\Models\Ipo::num($item['gmp']) }} ({{ number_format($item['gmp_pct'] ?? 0, 1) }}%)@if ($item['change']) {{ $item['change'] > 0 ? '▲' : '▼' }}{{ \App\Models\Ipo::num(abs($item['change'])) }}@endif @else — @endif |
@endforeach
</x-mail::table>

@endforeach
@if (! empty($digest['news']))
## Market news

@foreach ($digest['news'] as $news)
- [{{ $news['headline'] }}]({{ $news['url'] }}) · {{ $news['category'] }}
@endforeach

@endif
<x-mail::button :url="route('ipos.gmp')">
See live GMP for all IPOs
</x-mail::button>

<small>GMP is unofficial and indicative only; it is not investment advice. Allotment dates are tentative (T+3 timeline).</small>

<small>You're receiving this because you subscribed to the {{ $digest['frequency'] }} IPO Darbaar digest. [Unsubscribe]({{ $unsubscribeUrl }})</small>
</x-mail::message>
