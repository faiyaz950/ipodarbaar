@extends('layouts.app')

@php
    use App\Models\CorporateAction;
    use App\Models\Ipo;

    $year = now()->year;
    $listUrl = route('actions.'.$type);
    $count = $open->count() + $upcoming->count() + $closed->count();
    $copy = [
        'buyback' => [
            'title' => 'Share Buyback '.$year.': Open & Upcoming Buyback Offers',
            'h1' => 'Share Buybacks '.$year,
            'lead' => 'Open and upcoming share buyback offers by listed Indian companies, with buyback price, size, record date and offer dates.',
            'about' => [
                ['How a tender-offer buyback works', 'The company offers to buy back shares from shareholders at a fixed price, usually above the market price. Shareholders on the record date can tender their shares during the offer period, and the company accepts them in proportion (the acceptance ratio). Shares that are not accepted are returned to the demat account.'],
                ['Small shareholders get a reserved share', '15% of a tender-offer buyback is reserved for small shareholders, those whose holding in the company is worth up to ₹2 lakh on the record date. Their acceptance ratio is often higher than for large shareholders.'],
                ['Tax on buybacks', 'For buybacks from 1 October 2024, the entire amount received is taxed as dividend income at your slab rate, and the cost of the shares bought back is treated as a capital loss. Work out the numbers with the buyback acceptance ratio calculator.'],
            ],
            'faqs' => [
                ['Who can take part in a share buyback?', 'Shareholders who hold the shares on the record date. Trades settle the next working day (T+1), so you need to buy at least one trading day before the record date.'],
                ['What is the acceptance ratio in a buyback?', 'The share of tendered shares that the company accepts. It depends on how many shares are tendered in each category and is announced after the offer closes.'],
                ['How do I tender shares in a buyback?', 'Through your broker during the offer period. Most brokers have a buyback or corporate actions section; you choose the quantity and confirm with your depository TPIN or OTP.'],
            ],
        ],
        'rights' => [
            'title' => 'Rights Issue '.$year.': Open & Upcoming Rights Issues in India',
            'h1' => 'Rights Issues '.$year,
            'lead' => 'Open and upcoming rights issues by listed Indian companies, with the issue price, rights ratio, record date and offer dates.',
            'about' => [
                ['What is a rights issue?', 'A rights issue lets existing shareholders buy new shares of the company, usually at a discount to the market price, in proportion to what they already hold. A ratio of 1:5 means one new share for every five held on the record date.'],
                ['Rights entitlements', 'Eligible shareholders receive rights entitlements (REs) in their demat account. You can apply for the shares with them, sell (renounce) them on the stock exchange during the renunciation period, or let them lapse. Unused entitlements lapse when the issue closes.'],
                ['Should you apply?', 'Compare the rights price with the market price, look at why the company is raising money, and remember that not applying dilutes your stake. This page lists offers; it does not recommend them.'],
            ],
            'faqs' => [
                ['Who is eligible for a rights issue?', 'Shareholders whose names are on the company\'s records on the record date.'],
                ['What happens if I don\'t apply in a rights issue?', 'Your rights entitlements lapse when the issue closes and your percentage holding in the company falls, because new shares are issued to others.'],
                ['How do I apply for a rights issue?', 'Through ASBA in your bank\'s net banking, or through your broker if it supports rights issues, using the rights entitlements credited to your demat account.'],
            ],
        ],
        'ncd' => [
            'title' => 'NCD Issues '.$year.': Open & Upcoming NCD Public Issues',
            'h1' => 'NCD Public Issues '.$year,
            'lead' => 'Open and upcoming public issues of non-convertible debentures (NCDs), with coupon rate, tenure, credit rating, issue size and dates.',
            'about' => [
                ['What are NCDs?', 'Non-convertible debentures are bonds issued by companies to borrow money. They pay a fixed interest (coupon) for a set tenure and cannot be converted into shares. Public NCD issues are listed on the stock exchange after allotment.'],
                ['Check the credit rating', 'The credit rating from agencies such as CRISIL, ICRA, CARE or India Ratings indicates how likely the company is to pay interest and repay on time. Higher coupons usually come with lower ratings and more risk.'],
                ['Tax on NCD interest', 'Interest from NCDs is added to your income and taxed at your slab rate. Gains from selling listed NCDs on the exchange are taxed as capital gains.'],
            ],
            'faqs' => [
                ['Are NCDs safe?', 'They are not risk-free. If the company defaults, you may lose interest or principal. Secured NCDs are backed by company assets, but recovery can still take time. Check the rating and the company\'s finances.'],
                ['What is the minimum investment in an NCD issue?', 'It is set by each issue and stated in the offer document; many public NCD issues have a face value of ₹1,000 and a minimum application of ₹10,000.'],
                ['Can I sell NCDs before maturity?', 'Yes, listed NCDs can be sold on the stock exchange, but trading volumes are often low, so you may have to accept a lower price.'],
            ],
        ],
    ][$type];
@endphp

@section('title', $copy['title'])
@section('description', \Illuminate\Support\Str::limit($copy['lead'].($count ? ' '.$open->count().' open, '.$upcoming->count().' upcoming.' : ''), 158))
@if ($count === 0)
    @section('robots', 'noindex, follow')
@endif

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], [$meta['plural'], $listUrl]]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>{{ $meta['plural'] }}</span></nav>
        <h1>{{ $copy['h1'] }}</h1>
        <p class="lead">{{ $copy['lead'] }}</p>
        <div class="hub-links" style="margin-top:12px">
            @foreach (CorporateAction::TYPES as $key => $info)
                <a class="chip {{ $key === $type ? 'active' : '' }}" href="{{ route('actions.'.$key) }}">{{ $info['plural'] }}</a>
            @endforeach
        </div>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        @foreach (['open' => $open, 'upcoming' => $upcoming, 'closed' => $closed] as $status => $items)
            @continue($status === 'closed' && $items->isEmpty())
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title" id="{{ $status }}"><span class="ico"><x-icon :name="['open' => 'zap', 'upcoming' => 'calendar', 'closed' => 'check-circle'][$status]" :size="16" /></span> {{ ['open' => 'Open '.$meta['plural'], 'upcoming' => 'Upcoming '.$meta['plural'], 'closed' => 'Recently Closed'][$status] }}</h2>
                </div>
                <div class="table-wrap">
                    <table class="table ipo-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Offer dates</th>
                                <th class="r">{{ $meta['price'] }}</th>
                                <th class="r">Size</th>
                                <th>{{ ['buyback' => 'Method', 'rights' => 'Ratio', 'ncd' => 'Coupon'][$type] }}</th>
                                <th>Record date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $action)
                                <tr>
                                    <td class="company"><a class="co-name" href="{{ $action->url() }}">{{ $action->company }}</a>@if ($type === 'ncd' && $action->rating)<div class="co-meta"><span>{{ $action->rating }}</span></div>@endif</td>
                                    <td class="dates" data-label="Offer dates">{{ $action->open_date ? $action->open_date->format('j M').' – '.($action->close_date?->format('j M Y') ?? 'TBA') : 'Dates awaited' }}</td>
                                    <td class="r" data-label="{{ $meta['price'] }}">{{ $action->price ? '₹'.Ipo::num($action->price) : '—' }}</td>
                                    <td class="r" data-label="Size">{{ $action->size_cr ? '₹'.Ipo::num($action->size_cr).' Cr' : '—' }}</td>
                                    <td data-label="{{ ['buyback' => 'Method', 'rights' => 'Ratio', 'ncd' => 'Coupon'][$type] }}">{{ ['buyback' => $action->method, 'rights' => $action->ratio, 'ncd' => $action->coupon][$type] ?: '—' }}</td>
                                    <td data-label="Record date">{{ $action->record_date?->format('j M Y') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="table-empty">No {{ strtolower($status === 'open' ? 'open' : 'upcoming') }} {{ strtolower($meta['plural']) }} right now.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="card card-pad seo-copy">
            @foreach ($copy['about'] as [$heading, $text])
                <div>
                    <h2>{{ $heading }}</h2>
                    <p>{{ $text }}</p>
                </div>
            @endforeach
            @if ($type === 'buyback')
                <p><a class="link" href="{{ route('calculators.show', 'buyback-acceptance-ratio') }}">Buyback acceptance ratio calculator</a></p>
            @endif
        </div>

        <x-faq :faqs="$copy['faqs']" :title="$meta['plural'].': FAQs'" />

        <div class="note info">
            <x-icon name="info" />
            <span>Details are compiled from company and stock exchange announcements; always check the offer letter before acting. Nothing here is investment advice.</span>
        </div>
    </div>
</div>
@endsection
