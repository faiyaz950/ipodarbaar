@extends('layouts.app')

@php
    use App\Models\Ipo;

    $year = ($action->open_date ?? $action->created_at)->year;
    $name = $action->company.' '.$meta['label'].' '.$year;
    $facts = array_filter([
        'Status' => $action->statusLabel(),
        'Offer opens' => $action->open_date?->format('D, j M Y'),
        'Offer closes' => $action->close_date?->format('D, j M Y'),
        'Record date' => $action->record_date?->format('D, j M Y'),
        $meta['price'] => $action->price ? '₹'.Ipo::num($action->price) : null,
        'Size' => $action->size_cr ? '₹'.Ipo::num($action->size_cr).' crore' : null,
        'Method' => $action->method,
        'Rights ratio' => $action->ratio,
        'Coupon' => $action->coupon,
        'Tenure' => $action->tenure,
        'Credit rating' => $action->rating,
        'Exchange' => $action->exchange,
    ]);
@endphp

@section('title', $name.': '.['buyback' => 'Price, Record Date & Dates', 'rights' => 'Price, Ratio & Record Date', 'ncd' => 'Coupon, Rating & Dates'][$action->type])
@section('description', \Illuminate\Support\Str::limit($name.': '.($action->summary() ?: 'offer details').'. '.($action->open_date ? 'Opens '.$action->open_date->format('j M Y').'.' : 'Dates awaited.'), 158))

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], [$meta['plural'], route('actions.'.$action->type)], [$action->company, $action->url()]]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ route('actions.'.$action->type) }}">{{ $meta['plural'] }}</a> <x-icon name="chevron-right" :size="13" />
            <span>{{ $action->company }}</span>
        </nav>
        <h1>{{ $name }}</h1>
        @if ($action->summary())<p class="lead">{{ $action->summary() }}.</p>@endif
        <span class="updated-line"><x-icon name="refresh" :size="14" /> Updated {{ $action->updated_at->format('j M Y') }}</span>
    </div>
</section>

<div class="page-body">
    <div class="container stack" style="max-width:960px">
        <div class="card">
            <div class="facts-grid cols-3">
                @foreach ($facts as $label => $value)
                    <div class="fact"><span>{{ $label }}</span><b>{{ $value }}</b></div>
                @endforeach
            </div>
        </div>

        @if ($action->details)
            <div class="card card-pad prose">
                <h2 class="card-title" style="margin-bottom:10px">About this {{ strtolower($meta['label']) }}</h2>
                @foreach (preg_split('/\R{2,}/', trim($action->details)) as $paragraph)
                    <p>{!! nl2br(e($paragraph)) !!}</p>
                @endforeach
            </div>
        @endif

        <div class="btn-row">
            @if ($action->source_url)
                <a class="btn btn-outline" href="{{ $action->source_url }}" target="_blank" rel="noopener nofollow"><x-icon name="external" :size="15" /> Offer document / announcement</a>
            @endif
            @if ($action->type === 'buyback')
                <a class="btn btn-gold" href="{{ route('calculators.show', 'buyback-acceptance-ratio') }}"><x-icon name="calculator" :size="15" /> Buyback acceptance calculator</a>
            @endif
        </div>

        @if ($more->isNotEmpty())
            <div class="card">
                <div class="card-head"><h2 class="card-title">More {{ strtolower($meta['plural']) }}</h2></div>
                @foreach ($more as $other)
                    <a class="admin-row" href="{{ $other->url() }}"><span>{{ $other->company }}<small class="muted">{{ $other->statusLabel() }}{{ $other->open_date ? ' · '.$other->open_date->format('j M Y') : '' }}</small></span><x-icon name="arrow-right" :size="14" /></a>
                @endforeach
            </div>
        @endif

        <div class="note info">
            <x-icon name="info" />
            <span>Details are compiled from company and stock exchange announcements; always check the offer letter before acting. Nothing here is investment advice.</span>
        </div>
    </div>
</div>
@endsection
