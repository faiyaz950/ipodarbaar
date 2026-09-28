@extends('layouts.admin')

@section('title', 'Bulk edit IPOs')

@section('content')
<div class="admin-head">
    <div>
        <h1>Bulk edit IPOs</h1>
        <p class="muted">Fill in what the IPO feed doesn't provide: lot size, listing price, registrar and subscription. Clear a box to remove its value.</p>
    </div>
    <a class="btn btn-outline btn-sm" href="{{ route('admin.ipos.index') }}"><x-icon name="arrow-left" :size="14" /> All IPOs</a>
</div>

@if ($errors->any())
    <div class="flash err" role="alert"><x-icon name="alert" :size="17" /> {{ $errors->first() }}</div>
@endif
@if (session('import_errors'))
    <div class="card card-pad" style="margin-bottom:20px">
        <b>Rows skipped during import</b>
        <ul class="muted" style="margin:8px 0 0;padding-left:18px">
            @foreach (session('import_errors') as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card card-pad" style="margin-bottom:20px">
    <h2 class="card-title" style="margin-bottom:8px"><x-icon name="download" :size="16" /> Update many IPOs from a spreadsheet</h2>
    <p class="muted" style="margin:0 0 12px">Download the CSV, fill it in Excel or Google Sheets and upload it back. Rows are matched by <code>slug</code>; empty cells leave the current value unchanged.</p>
    <div class="bulk-csv">
        <a class="btn btn-outline btn-sm" href="{{ route('admin.ipos.bulk.csv', ['filter' => $filter]) }}"><x-icon name="download" :size="14" /> Download CSV ({{ strtolower($filters[$filter]) }})</a>
        <form method="post" action="{{ route('admin.ipos.bulk.import') }}" enctype="multipart/form-data" class="bulk-upload">
            @csrf
            <input class="input" type="file" name="file" accept=".csv,text/csv" required aria-label="CSV file">
            <button type="submit" class="btn btn-gold btn-sm">Upload CSV</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="filterbar">
        <div class="seg">
            @foreach ($filters as $key => $label)
                <a href="{{ route('admin.ipos.bulk', array_filter(['filter' => $key, 'q' => $q])) }}" class="{{ $filter === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="get" action="{{ route('admin.ipos.bulk') }}" class="input-icon">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <x-icon name="search" :size="16" />
            <input class="input" type="search" name="q" value="{{ $q }}" placeholder="Search by name…" aria-label="Search IPOs">
        </form>
    </div>

    <form method="post" action="{{ route('admin.ipos.bulk.update') }}">
        @csrf
        @method('PUT')
        <datalist id="registrar-list">
            @foreach ($registrars as $registrar)
                <option value="{{ $registrar }}"></option>
            @endforeach
        </datalist>
        <div class="table-wrap">
            <table class="table bulk-table">
                <thead>
                    <tr>
                        <th>IPO</th>
                        <th>Lot size</th>
                        <th>Listing price (₹)</th>
                        <th>Registrar</th>
                        <th>Subscription total (x)</th>
                        <th>Retail (x)</th>
                        <th>NII (x)</th>
                        <th>QIB (x)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ipos as $ipo)
                        <tr>
                            <td class="company">
                                <a href="{{ route('admin.ipos.edit', $ipo) }}"><b>{{ $ipo->name }}</b></a>
                                <div class="muted" style="font-size:12px">{{ $ipo->typeLabel() }} · {{ $ipo->statusLabel() }} · {{ $ipo->open_date?->format('j M Y') ?? 'Dates awaited' }}</div>
                            </td>
                            <td><input class="input bulk-input" type="number" min="1" step="1" name="ipos[{{ $ipo->id }}][lot_size]" value="{{ $ipo->lot_size }}" aria-label="Lot size for {{ $ipo->name }}"></td>
                            <td><input class="input bulk-input" type="number" min="0.01" step="0.01" name="ipos[{{ $ipo->id }}][listing_price]" value="{{ $ipo->listing_price }}" aria-label="Listing price for {{ $ipo->name }}"></td>
                            <td><input class="input bulk-input wide" type="text" maxlength="60" list="registrar-list" name="ipos[{{ $ipo->id }}][registrar]" value="{{ $ipo->registrar }}" aria-label="Registrar for {{ $ipo->name }}"></td>
                            @foreach (['subscription_total', 'subscription_retail', 'subscription_nii', 'subscription_qib'] as $field)
                                <td><input class="input bulk-input" type="number" min="0" step="0.01" name="ipos[{{ $ipo->id }}][{{ $field }}]" value="{{ $ipo->{$field} }}" aria-label="{{ str_replace('_', ' ', ucfirst($field)) }} for {{ $ipo->name }}"></td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">No IPOs match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">
            <span class="muted">Changed lot sizes are locked so the next sync keeps them.</span>
            <button type="submit" class="btn btn-gold">Save changes</button>
        </div>
    </form>
    @if ($ipos->hasPages())
        <div class="table-foot">{{ $ipos->links() }}</div>
    @endif
</div>
@endsection
