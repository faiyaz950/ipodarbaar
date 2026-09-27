@extends('layouts.admin')

@section('title', 'Company details · '.$ipo->name)

@section('content')
<div class="admin-head">
    <div>
        <a class="link-gold" href="{{ route('admin.ipos.index') }}"><x-icon name="arrow-left" :size="14" /> All IPOs</a>
        <h1>{{ $ipo->name }}</h1>
        <p class="muted">Offer-document details. These are never overwritten by the API sync.</p>
    </div>
    <div class="admin-actions">
        <a class="btn btn-outline btn-sm" href="{{ $ipo->url() }}" target="_blank" rel="noopener"><x-icon name="external" :size="15" /> View page</a>
    </div>
</div>

@include('admin.ipos.tabs', ['ipo' => $ipo])

@if ($errors->any())
    <div class="flash err" role="alert"><x-icon name="alert" :size="17" /> Please fix the highlighted fields.</div>
@endif

<form method="post" action="{{ route('admin.ipos.details.update', $ipo) }}" class="stack">
    @csrf
    @method('PUT')

    <div class="card card-pad">
        <div class="card-title af-section">Issue structure</div>
        <div class="af-grid">
            <x-admin-input name="fresh_issue_cr" label="Fresh issue (₹ crore)" :value="$detail->fresh_issue_cr" min="0" />
            <x-admin-input name="ofs_cr" label="Offer for sale (₹ crore)" :value="$detail->ofs_cr" min="0" />
            <x-admin-input name="face_value" label="Face value (₹/share)" :value="$detail->face_value" min="0" />
            <x-admin-input name="market_cap_cr" label="Market cap at upper band (₹ crore)" :value="$detail->market_cap_cr" min="0" />
            <x-admin-input name="retail_quota" label="Retail quota (%)" :value="$detail->retail_quota" min="0" max="100" />
            <x-admin-input name="nii_quota" label="NII quota (%)" :value="$detail->nii_quota" min="0" max="100" />
            <x-admin-input name="qib_quota" label="QIB quota (%)" :value="$detail->qib_quota" min="0" max="100" />
            <x-admin-input name="market_maker" label="Market maker (SME)" type="text" :value="$detail->market_maker" maxlength="120" />
            <x-admin-input name="promoter_holding_pre" label="Promoter holding pre-issue (%)" :value="$detail->promoter_holding_pre" min="0" max="100" />
            <x-admin-input name="promoter_holding_post" label="Promoter holding post-issue (%)" :value="$detail->promoter_holding_post" min="0" max="100" />
        </div>
        <div class="af-grid" style="margin-top:16px">
            <label class="af">
                <span class="af-label">Promoters</span>
                <textarea class="input" name="promoters" rows="3" maxlength="1000" placeholder="Names of the promoters">{{ old('promoters', $detail->promoters) }}</textarea>
                @error('promoters')<span class="af-error">{{ $message }}</span>@enderror
            </label>
            <label class="af">
                <span class="af-label">Book running lead managers (one per line)</span>
                <textarea class="input" name="lead_managers" rows="3" maxlength="1000">{{ old('lead_managers', $detail->lead_managers) }}</textarea>
                @error('lead_managers')<span class="af-error">{{ $message }}</span>@enderror
            </label>
        </div>
    </div>

    <div class="card card-pad">
        <div class="card-title af-section">Objects of the issue</div>
        <label class="af">
            <span class="af-label">What the money will be used for (one item per line)</span>
            <textarea class="input" name="objects" rows="5" maxlength="3000" placeholder="Repayment of borrowings&#10;Capital expenditure for new plant&#10;General corporate purposes">{{ old('objects', $detail->objects) }}</textarea>
            @error('objects')<span class="af-error">{{ $message }}</span>@enderror
        </label>
    </div>

    <div class="card card-pad">
        <div class="card-title af-section">Financials (₹ crore, restated)</div>
        <p class="af-hint" style="margin-bottom:12px">Oldest to newest, e.g. FY24, FY25, FY26 and the latest stub period. Leave unused rows empty.</p>
        <div class="table-wrap">
            <table class="table fin-form">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Period end</th>
                        @foreach (\App\Models\IpoFinancial::METRICS as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($financials as $i => $row)
                        <tr>
                            <td><input class="input" type="text" name="financials[{{ $i }}][period]" value="{{ old('financials.'.$i.'.period', $row['period'] ?? '') }}" maxlength="20" placeholder="FY26"></td>
                            <td><input class="input" type="date" name="financials[{{ $i }}][period_end]" value="{{ old('financials.'.$i.'.period_end', $row['period_end'] ?? '') }}"></td>
                            @foreach (array_keys(\App\Models\IpoFinancial::METRICS) as $metric)
                                <td><input class="input" type="number" step="any" name="financials[{{ $i }}][{{ $metric }}]" value="{{ old('financials.'.$i.'.'.$metric, $row[$metric] ?? '') }}"></td>
                            @endforeach
                        </tr>
                        @foreach (['period', 'period_end'] as $field)
                            @error('financials.'.$i.'.'.$field)<tr><td colspan="7"><span class="af-error">{{ $message }}</span></td></tr>@enderror
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-pad">
        <div class="card-title af-section">Valuation & KPIs</div>
        <div class="af-grid">
            <x-admin-input name="pe_pre" label="P/E pre-issue" :value="$detail->pe_pre" />
            <x-admin-input name="pe_post" label="P/E post-issue" :value="$detail->pe_post" />
            <x-admin-input name="eps" label="EPS (₹)" :value="$detail->eps" />
            <x-admin-input name="roe" label="ROE (%)" :value="$detail->roe" />
            <x-admin-input name="roce" label="ROCE (%)" :value="$detail->roce" />
            <x-admin-input name="ronw" label="RoNW (%)" :value="$detail->ronw" />
            <x-admin-input name="debt_equity" label="Debt / equity" :value="$detail->debt_equity" />
        </div>
    </div>

    <div class="card card-pad">
        <div class="card-title af-section">Anchor investors & documents</div>
        <div class="af-grid">
            <x-admin-input name="anchor_amount_cr" label="Raised from anchors (₹ crore)" :value="$detail->anchor_amount_cr" min="0" />
            <x-admin-input name="anchor_date" label="Anchor bid date" type="date" :value="$detail->anchor_date" />
            <x-admin-input name="rhp_url" label="RHP link (https)" type="url" :value="$detail->rhp_url" placeholder="https://…" />
            <x-admin-input name="drhp_url" label="DRHP link (https)" type="url" :value="$detail->drhp_url" placeholder="https://…" />
            <x-admin-input name="website_url" label="Company website" type="url" :value="$detail->website_url" placeholder="https://…" />
        </div>
    </div>

    <div class="admin-save">
        <button type="submit" class="btn btn-gold">Save company details</button>
        <a href="{{ route('admin.ipos.edit', $ipo) }}" class="btn btn-outline">Back to listing data</a>
    </div>
</form>
@endsection
