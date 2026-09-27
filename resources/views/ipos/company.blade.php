@php
    use App\Models\Ipo;
    use App\Models\IpoFinancial;
    $detail = $ipo->detail;
    $financials = $ipo->financials;
    $cr = fn (?float $value): string => $value === null ? '—' : '₹'.Ipo::num($value).' Cr';
    $pct = fn (?float $value): string => $value === null ? '—' : Ipo::num($value).'%';
    $allotment = collect($timeline)->firstWhere('label', 'Basis of Allotment')['date'] ?? null;
@endphp

@if ($detail?->hasIssueStructure())
    @php
        $issueTotal = ($detail->fresh_issue_cr ?? 0) + ($detail->ofs_cr ?? 0);
        $freshShare = $issueTotal > 0 ? round(($detail->fresh_issue_cr ?? 0) / $issueTotal * 100, 1) : null;
        $quotas = array_filter(['Retail' => $detail->retail_quota, 'NII / HNI' => $detail->nii_quota, 'QIB' => $detail->qib_quota], fn ($v) => $v !== null);
    @endphp
    <div class="card">
        <div class="card-head"><div class="card-title"><span class="ico"><x-icon name="pie" :size="16" /></span> Issue Structure</div></div>
        @if ($freshShare !== null)
            <div class="split">
                <div class="split-bar" role="img" aria-label="Fresh issue {{ $freshShare }}%, offer for sale {{ 100 - $freshShare }}%">
                    @if ($freshShare > 0)<i class="fresh" style="width: {{ $freshShare }}%"></i>@endif
                    @if ($freshShare < 100)<i class="ofs" style="width: {{ 100 - $freshShare }}%"></i>@endif
                </div>
                <div class="split-legend">
                    <span><i class="fresh"></i> Fresh issue <b>{{ $cr($detail->fresh_issue_cr) }}</b> <small>({{ Ipo::num($freshShare, 1) }}%)</small></span>
                    <span><i class="ofs"></i> Offer for sale <b>{{ $cr($detail->ofs_cr) }}</b> <small>({{ Ipo::num(100 - $freshShare, 1) }}%)</small></span>
                </div>
            </div>
        @endif
        <div class="facts-grid">
            <div class="fact"><span>Face value</span><b>{{ $detail->face_value !== null ? '₹'.Ipo::num($detail->face_value).' / share' : '—' }}</b></div>
            <div class="fact"><span>Market cap (post issue)</span><b>{{ $cr($detail->market_cap_cr) }}</b></div>
            <div class="fact"><span>Promoter holding (pre)</span><b>{{ $pct($detail->promoter_holding_pre) }}</b></div>
            <div class="fact"><span>Promoter holding (post)</span><b>{{ $pct($detail->promoter_holding_post) }}</b></div>
            @foreach ($quotas as $label => $quota)
                <div class="fact"><span>{{ $label }} quota</span><b>{{ $pct($quota) }} <small>of issue</small></b></div>
            @endforeach
        </div>
        @if (filled($detail->promoters) || $detail->leadManagersList() || filled($detail->market_maker))
            <dl class="deflist">
                @if (filled($detail->promoters))<div><dt>Promoters</dt><dd>{{ $detail->promoters }}</dd></div>@endif
                @if ($detail->leadManagersList())<div><dt>Book running lead managers</dt><dd>{{ implode(', ', $detail->leadManagersList()) }}</dd></div>@endif
                @if (filled($detail->market_maker))<div><dt>Market maker</dt><dd>{{ $detail->market_maker }}</dd></div>@endif
            </dl>
        @endif
    </div>
@endif

@if ($detail && $detail->objectsList())
    <div class="card card-pad">
        <div class="card-title" style="margin-bottom:12px"><span class="ico"><x-icon name="target" :size="16" /></span> Objects of the Issue</div>
        <ol class="objects">
            @foreach ($detail->objectsList() as $object)
                <li>{{ $object }}</li>
            @endforeach
        </ol>
    </div>
@endif

@if ($financials->isNotEmpty())
    @php
        $chartMax = max(1, $financials->max(fn (IpoFinancial $f) => max(abs($f->revenue ?? 0), abs($f->pat ?? 0))));
        $bar = fn (?float $value): float => $value === null ? 0 : round(abs($value) / $chartMax * 100, 1);
    @endphp
    <div class="card">
        <div class="card-head">
            <div class="card-title"><span class="ico"><x-icon name="bar-chart" :size="16" /></span> Company Financials</div>
            <span class="card-sub">Restated, ₹ crore</span>
        </div>
        <div class="fin-chart" role="img" aria-label="Revenue and profit after tax by period">
            @foreach ($financials as $row)
                <div class="fin-col">
                    <div class="fin-bars">
                        <i class="rev" style="height: {{ $bar($row->revenue) }}%" title="Revenue {{ $cr($row->revenue) }}"></i>
                        <i class="pat {{ ($row->pat ?? 0) < 0 ? 'neg' : '' }}" style="height: {{ $bar($row->pat) }}%" title="PAT {{ $cr($row->pat) }}"></i>
                    </div>
                    <span class="fin-label">{{ $row->period }}</span>
                </div>
            @endforeach
        </div>
        <div class="fin-legend"><span><i class="rev"></i> Revenue</span><span><i class="pat"></i> Profit after tax</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Period ended</th>
                        @foreach ($financials as $row)<th class="r">{{ $row->period }}@if ($row->period_end)<small class="muted" style="display:block;font-weight:500">{{ $row->period_end->format('j M Y') }}</small>@endif</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach (IpoFinancial::METRICS as $field => $label)
                        @continue($financials->every(fn (IpoFinancial $f) => $f->{$field} === null))
                        <tr>
                            <td><b>{{ $label }}</b></td>
                            @foreach ($financials as $row)<td class="r">{{ $row->{$field} === null ? '—' : Ipo::num($row->{$field}) }}</td>@endforeach
                        </tr>
                    @endforeach
                    <tr>
                        <td><b>PAT margin</b></td>
                        @foreach ($financials as $row)<td class="r">{{ $pct($row->patMargin()) }}</td>@endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif

@if ($detail?->hasKpis())
    <div class="card">
        <div class="card-head"><div class="card-title"><span class="ico"><x-icon name="gauge" :size="16" /></span> Key Performance Indicators</div></div>
        <div class="facts-grid">
            @foreach (['pe_pre' => 'P/E (pre issue)', 'pe_post' => 'P/E (post issue)', 'eps' => 'EPS', 'roe' => 'ROE', 'roce' => 'ROCE', 'ronw' => 'RoNW', 'debt_equity' => 'Debt / Equity'] as $field => $label)
                @continue($detail->{$field} === null)
                <div class="fact"><span>{{ $label }}</span><b>{{ match ($field) {
                    'eps' => '₹'.Ipo::num($detail->eps),
                    'roe', 'roce', 'ronw' => $pct($detail->{$field}),
                    default => Ipo::num($detail->{$field}).($field === 'debt_equity' ? '' : 'x'),
                } }}</b></div>
            @endforeach
        </div>
    </div>
@endif

@if ($detail && ($detail->anchor_amount_cr !== null || $detail->anchor_date))
    @php $lockins = $detail->anchorLockinDates($allotment); @endphp
    <div class="card">
        <div class="card-head"><div class="card-title"><span class="ico"><x-icon name="landmark" :size="16" /></span> Anchor Investors</div></div>
        <div class="facts-grid">
            <div class="fact"><span>Anchor amount</span><b>{{ $cr($detail->anchor_amount_cr) }}</b></div>
            <div class="fact"><span>Anchor bid date</span><b>{{ $detail->anchor_date?->format('D, j M Y') ?? '—' }}</b></div>
            <div class="fact"><span>50% lock-in ends</span><b>{{ isset($lockins[0]) ? $lockins[0]->format('j M Y') : '—' }}</b></div>
            <div class="fact"><span>Remaining lock-in ends</span><b>{{ isset($lockins[1]) ? $lockins[1]->format('j M Y') : '—' }}</b></div>
        </div>
        @if ($lockins)
            <p class="muted" style="font-size:13px;padding:14px 22px">Lock-in dates are counted from the {{ $allotment->format('j M') }} allotment date (30 and 90 days) and are indicative.</p>
        @endif
    </div>
@endif

@if ($detail?->hasDocuments())
    <div class="card card-pad">
        <div class="card-title" style="margin-bottom:12px"><span class="ico"><x-icon name="file-text" :size="16" /></span> Documents & Links</div>
        <div class="allot-links">
            @foreach (['rhp_url' => 'Red Herring Prospectus (RHP)', 'drhp_url' => 'Draft RHP (DRHP)', 'website_url' => 'Company website'] as $field => $label)
                @if (filled($detail->{$field}))
                    <a class="btn btn-outline btn-sm" href="{{ $detail->{$field} }}" target="_blank" rel="noopener nofollow">{{ $label }} <x-icon name="external" :size="14" /></a>
                @endif
            @endforeach
        </div>
    </div>
@endif
